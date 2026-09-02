<?php

namespace Intranet\Modules\Schulkantine\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Intranet\Modules\Schulkantine\Models\Category;
use Intranet\Modules\Schulkantine\Models\ChildCategoryPermission;
use Intranet\Modules\Schulkantine\Models\CustomerGroup;
use Intranet\Modules\Schulkantine\Models\Menu;
use Intranet\Modules\Schulkantine\Models\Order;
use Intranet\Modules\Schulkantine\Models\Season;
use Intranet\Modules\Schulkantine\Models\Subscription;
use Intranet\Modules\Schulkantine\Support\DeadlineService;
use Intranet\Modules\Schulkantine\Support\OgsAttendance;
use Intranet\Modules\Schulkantine\Support\ReleaseService;

/**
 * Vorbestellung für JEDEN eingeloggten Nutzer – für sich selbst und seine
 * Kinder. Zwei Modi je nach Kundengruppe des Essers:
 *  - Menü-Auswahl (Schüler/Sonstige): pro Kategorie max. 1 Gericht/Tag.
 *  - Ja/Nein (OGS): nur „isst heute". OGS läuft über ein Saison-Abo (isst
 *    standardmäßig alle Öffnungstage), Eltern verwalten nur Abbestellungen.
 *
 * Schranken: die Woche muss freigegeben sein (ReleaseService) UND die jeweilige
 * Frist eingehalten werden (DeadlineService).
 */
class OrderController
{
    /** Kurzlabels der ISO-Wochentage für Statusmeldungen. */
    private const WEEKDAY_KURZ = [1 => 'Mo', 2 => 'Di', 3 => 'Mi', 4 => 'Do', 5 => 'Fr', 6 => 'Sa', 7 => 'So'];

    public function index(Request $request)
    {
        $user = $request->user();

        $season = Season::where('is_active', true)->first();

        if (! $season) {
            return view('schulkantine::orders.index', ['season' => null]);
        }

        // Für wen darf bestellt werden: der Nutzer selbst + seine Kinder.
        $eaters = $this->eatersFor($user);
        $groups = CustomerGroup::all()->keyBy('role_id');

        // OGS-Esser bekommen (falls noch nicht vorhanden) ihr Saison-Abo –
        // „isst standardmäßig an allen Öffnungstagen".
        foreach ($eaters as $eater) {
            $group = CustomerGroup::forUser($eater, $groups);
            if ($group && $group->ordering_mode === CustomerGroup::MODE_JA_NEIN) {
                Subscription::firstOrCreate(['season_id' => $season->id, 'user_id' => $eater->id]);
            }
        }

        // Woche bestimmen (wie im Speiseplan: heute → nächster Öffnungstag).
        $weekStart = $this->resolveWeekStart($request, $season);
        $weekEnd = $weekStart->copy()->addDays(6);

        $release = new ReleaseService;
        $deadline = new DeadlineService;
        $weekReleased = $release->isWeekReleased($season, $weekStart);

        // GANZE Woche darstellen: alle Kantinen-Wochentage (auch geschlossene →
        // markiert, mit Grund). Für offene Tage zusätzlich die Fristen.
        $openingWeekdays = $season->opening_weekdays ?: [1, 2, 3, 4, 5];
        $closedByDate = $season->closedDays()
            ->whereBetween('date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->get()
            ->keyBy(fn ($c) => $c->date->toDateString());

        $days = [];
        for ($d = $weekStart->copy(); $d->lte($weekEnd); $d->addDay()) {
            if (! in_array($d->dayOfWeekIso, $openingWeekdays, true)) {
                continue;
            }
            $open = $season->isOpenOn($d);
            $reason = null;
            if (! $open) {
                if ($d->lt($season->start_date) || $d->gt($season->end_date)) {
                    $reason = 'außerhalb der Saison';
                } elseif ($closed = $closedByDate->get($d->toDateString())) {
                    $reason = $closed->reason;
                } else {
                    $reason = 'geschlossen';
                }
            }
            $days[] = [
                'date' => $d->copy(),
                'open' => $open,
                'reason' => $reason,
                'canOrder' => $open ? $deadline->canOrder($season, $d) : false,
                'canCancel' => $open ? $deadline->canCancel($season, $d) : false,
                'orderDeadline' => $open ? $deadline->orderDeadline($season, $d) : null,
            ];
        }

        // Tagesangebot der Woche (mit Allergenen/Diäten für die Warnungen).
        $plan = [];
        // Vollständig zusammengestellte Menüs der Woche je Tag (bestellbar).
        $menuDaysByDate = collect();
        if ($weekReleased) {
            $menus = Menu::where('season_id', $season->id)
                ->whereBetween('date', [$weekStart->toDateString(), $weekEnd->toDateString()])
                ->with(['dish.category', 'dish.allergens', 'dish.additives', 'dish.unsuitableDiets'])
                ->orderBy('sort_order')->orderBy('id')
                ->get();
            foreach ($menus as $m) {
                $plan[$m->date->toDateString()][] = $m;
            }

            $menuDaysByDate = \Intranet\Modules\Schulkantine\Models\MenuDay::where('season_id', $season->id)
                ->whereBetween('date', [$weekStart->toDateString(), $weekEnd->toDateString()])
                ->with(['slots.dish.allergens', 'slots.dish.unsuitableDiets', 'slots.category'])
                ->orderBy('id')
                ->get()
                // Nur Menüs, deren Slots alle ein Gericht haben (sonst nicht bestellbar).
                ->filter(fn ($md) => $md->slots->isNotEmpty() && $md->slots->every(fn ($s) => $s->dish_id !== null))
                ->groupBy(fn ($md) => $md->date->toDateString());
        }

        // Bestehende Bestellungen dieser Esser in dieser Woche.
        $eaterIds = $eaters->pluck('id');
        $orders = Order::whereIn('user_id', $eaterIds)
            ->where('season_id', $season->id)
            ->whereBetween('date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->get();

        // Menü-Auswahl je (Esser, Tag, Kategorie) → dish_id.
        $selected = [];
        // OGS-Abbestellungen je (Esser, Tag): storniert = isst NICHT.
        $ogsCancelled = [];
        // OGS-Einzelbestellungen (ohne Abo) je (Esser, Tag): bestellt = isst.
        $ogsOrdered = [];
        // Tages-Summe je (Esser, Tag) aus den aktiven Bestellungen (Preis-Snapshot).
        $dayTotals = [];
        // Bestellte Menüs je (Esser, Tag): menu_day_id → true.
        $orderedMenus = [];
        foreach ($orders as $o) {
            $dateStr = $o->date->toDateString();
            if ($o->menu_day_id) {
                // Menü-Bestellung (Slot). Zählt zur Tages-Summe, aber NICHT zur
                // à-la-carte-Auswahl (sonst erschienen die Menü-Gerichte als einzeln
                // gewählt). Das Menü selbst wird als Ganzes markiert.
                if ($o->isActive()) {
                    $orderedMenus[$o->user_id][$dateStr][$o->menu_day_id] = true;
                    $dayTotals[$o->user_id][$dateStr] = ($dayTotals[$o->user_id][$dateStr] ?? 0) + (float) $o->price_snapshot;
                }
            } elseif ($o->category_id) {
                if ($o->isActive()) {
                    $selected[$o->user_id][$dateStr][$o->category_id] = $o->dish_id;
                    $dayTotals[$o->user_id][$dateStr] = ($dayTotals[$o->user_id][$dateStr] ?? 0) + (float) $o->price_snapshot;
                }
            } else { // OGS (keine Kategorie, kein Menü)
                if ($o->isCancelled()) {
                    $ogsCancelled[$o->user_id][$dateStr] = true;
                } elseif ($o->isActive()) {
                    $ogsOrdered[$o->user_id][$dateStr] = true;
                }
            }
        }

        // Offener Gesamtbetrag des angezeigten Monats (Haushalt = Nutzer + Kinder).
        // „offen" = alle aktiven Bestellungen; die Abrechnung erfolgt extern (Phase 5).
        // Monat am DONNERSTAG der Woche verankern (wie ISO-Wochen), damit eine
        // Woche, die über den Monatswechsel reicht, dem Monat zugeordnet wird, in
        // dem die meisten Öffnungstage liegen – sonst zeigt der Kopf einen anderen
        // Monat als die sichtbaren Bestellungen.
        $monthAnchor = $weekStart->copy()->addDays(3);
        $monthStart = $monthAnchor->copy()->startOfMonth();
        $monthEnd = $monthAnchor->copy()->endOfMonth();
        // Offener Betrag JE PERSON (für die Aufschlüsselung im Esser-Kopf) aus den
        // Menü-Bestellungen (Preis-Snapshot). OGS-Kosten kommen weiter unten dazu;
        // der Haushalts-Gesamtwert (oben rechts) wird danach als Summe gebildet.
        $monthByUser = Order::whereIn('user_id', $eaterIds)
            ->where('season_id', $season->id)
            ->where('status', Order::STATUS_ORDERED)
            ->whereBetween('date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->selectRaw('user_id, SUM(price_snapshot) as total')
            ->groupBy('user_id')
            ->pluck('total', 'user_id')
            ->map(fn ($v) => (float) $v)
            ->all();

        // Abos je Esser (inkl. Standardtage-Muster) für die OGS-Teilnahme.
        $subs = Subscription::whereIn('user_id', $eaterIds)
            ->where('season_id', $season->id)
            ->get()->keyBy('user_id');
        $subscribed = $subs->filter(fn ($s) => $s->active)->keys()->flip();

        // Esser aufbereiten (Gruppe, Modus, Sonderkost-IDs).
        $eaterData = $eaters->map(function (User $eater) use ($groups, $subs, $openingWeekdays) {
            $group = CustomerGroup::forUser($eater, $groups);

            return [
                'user' => $eater,
                'group' => $group,
                'mode' => $group?->ordering_mode,
                'allergenIds' => $eater->kantineAllergens->pluck('id')->all(),
                'dietIds' => $eater->kantineDiets->pluck('id')->all(),
                // Kategorien, die dieser Esser NICHT vorbestellen darf (Eltern-Freigabe).
                'blockedCats' => ChildCategoryPermission::where('user_id', $eater->id)
                    ->where('may_preorder', false)->pluck('category_id')->all(),
                // OGS-Abo: aktiv? und an welchen Wochentagen (leer/kein Muster = alle Öffnungstage).
                'aboActive' => (bool) ($subs[$eater->id]->active ?? false),
                'aboWeekdays' => ($subs[$eater->id]->weekdays ?? null) ?: $openingWeekdays,
            ];
        });

        // OGS-Kosten in die Monatssummen einrechnen: OGS-Kinder wählen keine
        // Gerichte (kein Preis-Snapshot), ihr offener Betrag = teilgenommene
        // Öffnungstage des Monats × Saison-Fixpreis (Season::ogs_price).
        //  - Mit Abo:  alle Öffnungstage minus Abbestellungen (storniert).
        //  - Ohne Abo: nur explizit angehakte (bestellte) Tage.
        $ogsPrice = (float) ($season->ogs_price ?? 0);
        if ($ogsPrice > 0) {
            $ogsEaterIds = $eaterData->filter(fn ($e) => $e['mode'] === CustomerGroup::MODE_JA_NEIN)->pluck('user.id');

            if ($ogsEaterIds->isNotEmpty()) {
                $openMonthDays = [];
                for ($d = $monthStart->copy(); $d->lte($monthEnd); $d->addDay()) {
                    if ($season->isOpenOn($d)) {
                        $openMonthDays[$d->toDateString()] = true;
                    }
                }
                $ogsMonth = Order::whereIn('user_id', $ogsEaterIds)
                    ->where('season_id', $season->id)
                    ->whereNull('category_id')
                    ->whereBetween('date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                    ->get(['user_id', 'date', 'status']);

                foreach ($ogsEaterIds as $uid) {
                    $rows = $ogsMonth->where('user_id', $uid);
                    $attended = count(OgsAttendance::attendedDates($subs->get($uid), $openMonthDays, $rows));
                    $monthByUser[$uid] = ($monthByUser[$uid] ?? 0) + $attended * $ogsPrice;
                }
            }
        }

        // Haushalts-Gesamtwert (oben rechts) = Summe aller Personen (inkl. OGS).
        $monthTotal = array_sum($monthByUser);

        return view('schulkantine::orders.index', [
            'season' => $season,
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'days' => $days,
            'plan' => $plan,
            'eaters' => $eaterData,
            'weekReleased' => $weekReleased,
            'selected' => $selected,
            'menuDaysByDate' => $menuDaysByDate,
            'orderedMenus' => $orderedMenus,
            'ogsCancelled' => $ogsCancelled,
            'ogsOrdered' => $ogsOrdered,
            'subscribed' => $subscribed,
            'dayTotals' => $dayTotals,
            'monthTotal' => $monthTotal,
            'monthByUser' => $monthByUser,
            'ogsPrice' => $ogsPrice,
            'openingWeekdays' => $openingWeekdays,
            'monthStart' => $monthStart,
            'prevWeek' => $weekStart->copy()->subWeek()->toDateString(),
            'nextWeek' => $weekStart->copy()->addWeek()->toDateString(),
            'canPrev' => $weekStart->copy()->subWeek()->endOfWeek(Carbon::SUNDAY)->gte($season->start_date),
            'canNext' => $weekStart->copy()->addWeek()->lte($season->end_date),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'eater_id' => ['required', 'integer'],
            'date' => ['required', 'date'],
            // Menü-Modus:
            'category_id' => ['nullable', 'integer', 'exists:kantine_categories,id'],
            'dish_id' => ['nullable', 'integer', 'exists:kantine_dishes,id'],
            // Menü (Bündel) als Ganzes:
            'menu_day_id' => ['nullable', 'integer', 'exists:kantine_menu_days,id'],
            // OGS ja/nein:
            'attend' => ['nullable', 'in:0,1'],
        ]);

        $season = Season::where('is_active', true)->firstOrFail();
        $eater = User::findOrFail($data['eater_id']);

        // Berechtigung: nur für sich selbst oder ein eigenes Kind bestellen.
        abort_unless($this->mayOrderFor($user, $eater), 403, 'Du darfst für diese Person nicht bestellen.');

        $date = Carbon::parse($data['date'])->startOfDay();
        abort_unless($season->isOpenOn($date), 422, 'An diesem Tag hat die Kantine nicht geöffnet.');

        $release = new ReleaseService;
        if (! $release->isWeekReleased($season, $date)) {
            return back()->withErrors(['bestellung' => 'Diese Woche ist noch nicht zum Bestellen freigegeben.']);
        }

        $group = CustomerGroup::forUser($eater);
        $mode = $group?->ordering_mode;
        $deadline = new DeadlineService;

        // Ganzes Menü bestellen/abbestellen (nicht für OGS-Esser).
        if (! empty($data['menu_day_id']) && $mode !== CustomerGroup::MODE_JA_NEIN) {
            return $this->handleMenuDay($season, $eater, $date, $deadline, (int) $data['menu_day_id'], $data['attend'] ?? '1');
        }

        if ($mode === CustomerGroup::MODE_JA_NEIN) {
            return $this->handleOgs($request, $season, $eater, $date, $deadline, $data['attend'] ?? '1');
        }

        return $this->handleMenu($request, $season, $eater, $date, $deadline, $data);
    }

    /**
     * Ein ganzes Menü bestellen (attend=1) oder abbestellen (attend=0). Ein Menü
     * wird in seine gefüllten Slots zerlegt: je Slot eine normale Gericht-Bestellung
     * (Kategorie + Gericht), alle mit derselben menu_day_id gruppiert. Der Festpreis
     * des Menüs wird auf die Gerichte verteilt (Summe = Menü-Preis).
     *
     * Beim Bestellen werden vorherige Bestellungen des Essers an dem Tag verdrängt
     * (à-la-carte wie andere Menüs) – ein Esser bekommt an einem Tag ein Menü ODER
     * einzelne Gerichte, nicht beides doppelt.
     */
    private function handleMenuDay(Season $season, User $eater, Carbon $date, DeadlineService $deadline, int $menuDayId, string $attend)
    {
        $menuDay = \Intranet\Modules\Schulkantine\Models\MenuDay::with('slots.dish')
            ->where('season_id', $season->id)
            ->whereDate('date', $date->toDateString())
            ->find($menuDayId);

        abort_if(! $menuDay, 422, 'Dieses Menü steht an dem Tag nicht zur Verfügung.');

        // Bereits für dieses Menü an dem Tag bestellt?
        $existing = Order::where('season_id', $season->id)
            ->where('user_id', $eater->id)
            ->whereDate('date', $date->toDateString())
            ->where('menu_day_id', $menuDay->id)
            ->where('status', Order::STATUS_ORDERED)
            ->exists();

        if ($attend !== '1') {
            // Abbestellen.
            if ($existing && ! $deadline->canCancel($season, $date)) {
                return back()->withErrors(['bestellung' => 'Die Abbestell-Frist für diesen Tag ist abgelaufen.']);
            }
            Order::where('season_id', $season->id)->where('user_id', $eater->id)
                ->whereDate('date', $date->toDateString())->where('menu_day_id', $menuDay->id)->delete();

            return back()->with('status', 'Abbestellt: '.$menuDay->name.' für '.$eater->name.' am '.$date->format('d.m.Y').'.');
        }

        // Bestellen – nur wenn alle Slots ein Gericht haben.
        $slots = $menuDay->slots;
        if ($slots->isEmpty() || $slots->contains(fn ($s) => $s->dish_id === null)) {
            return back()->withErrors(['bestellung' => 'Dieses Menü ist noch nicht vollständig zusammengestellt.']);
        }
        if (! $deadline->canOrder($season, $date)) {
            return back()->withErrors(['bestellung' => 'Die Bestellfrist für diesen Tag ist abgelaufen.']);
        }

        // Verdrängung: alle aktiven Gericht-/Menü-Bestellungen des Essers an dem Tag
        // (nicht OGS) räumen, dann das Menü frisch anlegen.
        Order::where('season_id', $season->id)->where('user_id', $eater->id)
            ->whereDate('date', $date->toDateString())
            ->where('status', Order::STATUS_ORDERED)
            ->where(fn ($q) => $q->whereNotNull('category_id')->orWhereNotNull('menu_day_id'))
            ->delete();

        $prices = $this->distributeMenuPrice((float) $menuDay->price, $slots->map(fn ($s) => (float) ($s->dish->price ?? 0))->all());

        foreach ($slots->values() as $i => $slot) {
            Order::create([
                'season_id' => $season->id,
                'user_id' => $eater->id,
                'date' => $date->toDateString(),
                'menu_day_id' => $menuDay->id,
                'dish_id' => $slot->dish_id,
                'category_id' => $slot->category_id,
                'price_snapshot' => $prices[$i] ?? 0,
                'status' => Order::STATUS_ORDERED,
            ]);
        }

        return back()->with('status', 'Bestellt: '.$menuDay->name.' für '.$eater->name.' am '.$date->format('d.m.Y').'.');
    }

    /**
     * Verteilt den Menü-Festpreis auf die Bestandteil-Gerichte – proportional zu
     * ihren Einzelpreisen, damit die Summe exakt dem Menü-Preis entspricht. Ohne
     * Einzelpreise (alle 0) wird gleichmäßig geteilt. Rundungsrest landet auf dem
     * ersten Posten.
     *
     * @param  array<int,float>  $dishPrices
     * @return array<int,float>
     */
    private function distributeMenuPrice(float $menuPrice, array $dishPrices): array
    {
        $n = count($dishPrices);
        if ($n === 0) {
            return [];
        }
        $sum = array_sum($dishPrices);

        $parts = [];
        if ($sum > 0) {
            foreach ($dishPrices as $p) {
                $parts[] = round($menuPrice * $p / $sum, 2);
            }
        } else {
            $each = round($menuPrice / $n, 2);
            $parts = array_fill(0, $n, $each);
        }
        // Rundungsrest auf den ersten Posten legen, damit die Summe exakt stimmt.
        $parts[0] = round($parts[0] + ($menuPrice - array_sum($parts)), 2);

        return $parts;
    }

    /**
     * OGS-Abo ganz an- oder abbestellen. Aus = das Kind isst nur noch an
     * angehakten Tagen; An = wieder Standard-Teilnahme an allen Öffnungstagen.
     * (Die Fristen/Abrechnung je Tag bleiben davon unberührt – Feinschliff für
     *  die spätere Abrechnung folgt in Phase 5.)
     */
    public function subscription(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'eater_id' => ['required', 'integer'],
            'active' => ['required', 'in:0,1'],
            'weekdays' => ['nullable', 'array'],
            'weekdays.*' => ['integer', 'between:1,7'],
        ]);

        $season = Season::where('is_active', true)->firstOrFail();
        $eater = User::findOrFail($data['eater_id']);

        abort_unless($this->mayOrderFor($user, $eater), 403, 'Du darfst für diese Person kein Abo verwalten.');

        $group = CustomerGroup::forUser($eater);
        abort_unless($group && $group->ordering_mode === CustomerGroup::MODE_JA_NEIN, 422, 'Nur OGS-Esser haben ein Abo.');

        $active = $data['active'] === '1';
        // Leere Auswahl = keine Einschränkung (isst an allen Öffnungstagen).
        $weekdays = collect($data['weekdays'] ?? [])
            ->map(fn ($d) => (int) $d)->unique()->sort()->values()->all();

        Subscription::updateOrCreate(
            ['season_id' => $season->id, 'user_id' => $eater->id],
            ['active' => $active, 'weekdays' => $weekdays ?: null],
        );

        if (! $active) {
            return back()->with('status', $eater->name.': Abo abbestellt – isst nur noch an einzeln angehakten Tagen.');
        }

        $tage = $weekdays === []
            ? 'an allen Öffnungstagen'
            : 'nur '.collect($weekdays)->map(fn ($d) => self::WEEKDAY_KURZ[$d] ?? $d)->join(', ');

        return back()->with('status', $eater->name.': Abo aktiviert – isst '.$tage.' (außer abbestellten Tagen).');
    }

    // ------------------------------------------------------------- Menü-Modus

    private function handleMenu(Request $request, Season $season, User $eater, Carbon $date, DeadlineService $deadline, array $data)
    {
        abort_if(empty($data['category_id']), 422, 'Es fehlt die Kategorie.');
        $categoryId = (int) $data['category_id'];

        $existing = Order::where('user_id', $eater->id)
            ->where('season_id', $season->id)
            ->whereDate('date', $date->toDateString())
            ->where('category_id', $categoryId)
            ->where('status', Order::STATUS_ORDERED)
            ->first();

        // Nichts gewählt → vorhandene Bestellung dieser Kategorie abbestellen.
        if (empty($data['dish_id'])) {
            if ($existing) {
                if (! $deadline->canCancel($season, $date)) {
                    return back()->withErrors(['bestellung' => 'Die Abbestell-Frist für diesen Tag ist abgelaufen.']);
                }
                $existing->delete();

                return back()->with('status', 'Abbestellt: '.$eater->name.' am '.$date->format('d.m.Y').'.');
            }

            return back();
        }

        // Ein Gericht gewählt → es muss an diesem Tag im Speiseplan stehen.
        $menu = Menu::where('season_id', $season->id)
            ->whereDate('date', $date->toDateString())
            ->where('dish_id', (int) $data['dish_id'])
            ->with(['dish.category'])
            ->first();

        abort_if(! $menu, 422, 'Dieses Gericht steht an dem Tag nicht auf dem Speiseplan.');
        abort_if((int) $menu->dish->category_id !== $categoryId, 422, 'Gericht passt nicht zur Kategorie.');

        if (! $deadline->canOrder($season, $date)) {
            return back()->withErrors(['bestellung' => 'Die Bestellfrist für diesen Tag ist abgelaufen.']);
        }

        // Die Kategorie, die diese Bestellung belegt – Basis für Eltern-Sperre & Verdrängung.
        $occupied = array_values(array_filter([$menu->dish->category_id]));

        // 1. Vorbestellbar? Es zählt die Kategorie des Gerichts.
        $ownCategory = $menu->dish->category;
        if ($ownCategory && ! $ownCategory->allows_preorder) {
            return back()->withErrors(['bestellung' =>
                '„'.$ownCategory->name.'" kann nicht vorbestellt werden – nur spontan bei der Ausgabe.']);
        }

        // 2. Kategorie-Freigabe: Eltern können die Vorbestellung einzelner Kategorien
        //    für ihre Kinder sperren (z. B. keinen Nachtisch).
        $categories = Category::whereIn('id', $occupied)->get()->keyBy('id');
        foreach ($occupied as $catId) {
            if (! ChildCategoryPermission::canPreorder($eater->id, $catId)) {
                $category = $categories->get($catId);

                return back()->withErrors(['bestellung' =>
                    'Für '.$eater->name.' ist die Vorbestellung nicht freigegeben'
                    .($category ? ' (Kategorie „'.$category->name.'")' : '').'.']);
            }
        }

        // Verdrängung: eine vorhandene Bestellung derselben Kategorie an dem Tag
        // wird durch die neue ersetzt (pro Kategorie höchstens ein Gericht).
        $displaced = $this->displaceConflicting($eater, $season, $date, $occupied, $existing);

        $attributes = [
            'menu_id' => $menu->id,
            'dish_id' => $menu->dish_id,
            'price_snapshot' => $menu->dish->price,
            'status' => Order::STATUS_ORDERED,
        ];

        if ($existing) {
            $existing->update($attributes);
        } else {
            Order::create($attributes + [
                'season_id' => $season->id,
                'user_id' => $eater->id,
                'date' => $date->toDateString(),
                'category_id' => $categoryId,
            ]);
        }

        $status = 'Bestellung gespeichert: '.$menu->dish->name.' für '.$eater->name.' am '.$date->format('d.m.Y').'.';
        if ($displaced !== []) {
            $status .= ' Ersetzt wurde: '.implode(', ', $displaced).'.';
        }

        return back()->with('status', $status);
    }

    /**
     * Löscht die aktiven Bestellungen dieses Essers am selben Tag, die dieselbe
     * Kategorie beanspruchen – ausgenommen die Zeile, die der Aufrufer ohnehin
     * gleich überschreibt. So bleibt es bei höchstens einem Gericht je Kategorie.
     *
     * @param  array<int>  $occupied
     * @return array<string> Namen der verdrängten Gerichte (für die Rückmeldung)
     */
    private function displaceConflicting(User $eater, Season $season, Carbon $date, array $occupied, ?Order $keep): array
    {
        $others = Order::where('user_id', $eater->id)
            ->where('season_id', $season->id)
            ->whereDate('date', $date->toDateString())
            ->where('status', Order::STATUS_ORDERED)
            ->whereNotNull('category_id') // NULL = OGS ja/nein, betrifft uns nicht
            ->when($keep, fn ($q) => $q->where('id', '!=', $keep->id))
            ->with(['dish', 'menuDay'])
            ->get();

        $displaced = [];
        $droppedMenus = [];
        foreach ($others as $o) {
            $theirs = array_values(array_filter([$o->category_id]));

            if (array_intersect($theirs, $occupied) !== []) {
                // Gehört die Zeile zu einem Menü, fällt das GANZE Menü (kein
                // verwaister Slot); sonst nur diese Gericht-Bestellung.
                if ($o->menu_day_id) {
                    if (! in_array($o->menu_day_id, $droppedMenus, true)) {
                        $droppedMenus[] = $o->menu_day_id;
                        $displaced[] = $o->menuDay?->name ?? 'Menü';
                        Order::where('user_id', $eater->id)->where('season_id', $season->id)
                            ->whereDate('date', $date->toDateString())
                            ->where('menu_day_id', $o->menu_day_id)->delete();
                    }
                } else {
                    $displaced[] = $o->dish?->name ?? 'frühere Bestellung';
                    $o->delete();
                }
            }
        }

        return $displaced;
    }

    // -------------------------------------------------------------- OGS ja/nein

    private function handleOgs(Request $request, Season $season, User $eater, Carbon $date, DeadlineService $deadline, string $attend)
    {
        $sub = Subscription::where('season_id', $season->id)->where('user_id', $eater->id)->first();
        // Standardteilnahme aus dem Abo-Muster (Wochentag); einzelne Tage schlagen sie.
        $default = $sub && $sub->active && $sub->eatsWeekday($date->dayOfWeekIso);

        $cancelOrder = Order::where('user_id', $eater->id)
            ->where('season_id', $season->id)
            ->whereDate('date', $date->toDateString())
            ->whereNull('category_id')
            ->where('status', Order::STATUS_CANCELLED)
            ->first();

        $orderRow = Order::where('user_id', $eater->id)
            ->where('season_id', $season->id)
            ->whereDate('date', $date->toDateString())
            ->whereNull('category_id')
            ->where('status', Order::STATUS_ORDERED)
            ->first();

        if ($attend === '1') {
            // Isst → als Bestellung behandeln (Frist: Bestellschluss).
            if (! $deadline->canOrder($season, $date)) {
                return back()->withErrors(['bestellung' => 'Die Bestellfrist für diesen Tag ist abgelaufen.']);
            }
            // Etwaige Abbestellung aufheben.
            $cancelOrder?->delete();
            // Nicht-Standardtag (oder kein Abo) braucht eine explizite Bestell-Zeile.
            if (! $default && ! $orderRow) {
                Order::create([
                    'season_id' => $season->id,
                    'user_id' => $eater->id,
                    'date' => $date->toDateString(),
                    'status' => Order::STATUS_ORDERED,
                ]);
            }

            return back()->with('status', $eater->name.' isst am '.$date->format('d.m.Y').'.');
        }

        // Isst NICHT → Abbestellung (Frist: Abbestell-Frist).
        if (! $deadline->canCancel($season, $date)) {
            return back()->withErrors(['bestellung' => 'Die Abbestell-Frist für diesen Tag ist abgelaufen.']);
        }
        // Eine evtl. Einzelbestellung entfernen.
        $orderRow?->delete();
        // Standardtag: Abbestellung als storniert-Zeile festhalten.
        if ($default && ! $cancelOrder) {
            Order::create([
                'season_id' => $season->id,
                'user_id' => $eater->id,
                'date' => $date->toDateString(),
                'status' => Order::STATUS_CANCELLED,
            ]);
        }

        return back()->with('status', $eater->name.' isst am '.$date->format('d.m.Y').' NICHT.');
    }

    // ----------------------------------------------------------------- Helfer

    /**
     * Die Esser des Haushalts – KINDER ZUERST, der Nutzer selbst zuletzt
     * (Eltern kümmern sich meist zuerst um die Kinder). Jeweils mit Sonderkost.
     */
    private function eatersFor(User $user): Collection
    {
        $user->loadMissing(['kantineAllergens', 'kantineDiets', 'roles']);
        $children = $user->children()->with(['kantineAllergens', 'kantineDiets', 'roles'])->orderBy('name')->get();

        return $children->concat([$user])->unique('id')->values();
    }

    private function mayOrderFor(User $user, User $eater): bool
    {
        return $user->id === $eater->id || $user->children()->whereKey($eater->id)->exists();
    }

    private function resolveWeekStart(Request $request, Season $season): Carbon
    {
        $default = Carbon::today();
        if ($default->lt($season->start_date)) {
            $default = $season->start_date->copy();
        } elseif ($default->gt($season->end_date)) {
            $default = $season->end_date->copy();
        }
        $probe = $default->copy();
        while (! $season->isOpenOn($probe) && $probe->lt($season->end_date)) {
            $probe->addDay();
        }
        if ($season->isOpenOn($probe)) {
            $default = $probe;
        }

        try {
            $base = $request->filled('week') ? Carbon::parse($request->query('week')) : $default;
        } catch (\Exception $e) {
            $base = $default;
        }

        return $base->copy()->startOfWeek(Carbon::MONDAY);
    }
}
