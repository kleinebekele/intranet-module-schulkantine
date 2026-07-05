<?php

namespace Intranet\Modules\Schulkantine\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Intranet\Modules\Schulkantine\Models\Budget;
use Intranet\Modules\Schulkantine\Models\CustomerGroup;
use Intranet\Modules\Schulkantine\Models\Menu;
use Intranet\Modules\Schulkantine\Models\Order;
use Intranet\Modules\Schulkantine\Models\Season;
use Intranet\Modules\Schulkantine\Models\Subscription;
use Intranet\Modules\Schulkantine\Support\DeadlineService;
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
        if ($weekReleased) {
            $menus = Menu::where('season_id', $season->id)
                ->whereBetween('date', [$weekStart->toDateString(), $weekEnd->toDateString()])
                ->with(['dish.category', 'dish.allergens', 'dish.additives', 'dish.unsuitableDiets'])
                ->orderBy('sort_order')->orderBy('id')
                ->get();
            foreach ($menus as $m) {
                $plan[$m->date->toDateString()][] = $m;
            }
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
        foreach ($orders as $o) {
            $dateStr = $o->date->toDateString();
            if ($o->category_id) {
                if ($o->isActive()) {
                    $selected[$o->user_id][$dateStr][$o->category_id] = $o->dish_id;
                    $dayTotals[$o->user_id][$dateStr] = ($dayTotals[$o->user_id][$dateStr] ?? 0) + (float) $o->price_snapshot;
                }
            } else { // OGS (keine Kategorie)
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

        // Aktive Abos je Esser (für die OGS-Standard-Teilnahme).
        $subscribed = Subscription::whereIn('user_id', $eaterIds)
            ->where('season_id', $season->id)
            ->where('active', true)
            ->pluck('user_id')->flip();

        // Esser aufbereiten (Gruppe, Modus, Sonderkost-IDs).
        $eaterData = $eaters->map(function (User $eater) use ($groups) {
            $group = CustomerGroup::forUser($eater, $groups);

            return [
                'user' => $eater,
                'group' => $group,
                'mode' => $group?->ordering_mode,
                'allergenIds' => $eater->kantineAllergens->pluck('id')->all(),
                'dietIds' => $eater->kantineDiets->pluck('id')->all(),
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
                $openMonthCount = count($openMonthDays);

                $ogsMonth = Order::whereIn('user_id', $ogsEaterIds)
                    ->where('season_id', $season->id)
                    ->whereNull('category_id')
                    ->whereBetween('date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                    ->get(['user_id', 'date', 'status']);

                foreach ($ogsEaterIds as $uid) {
                    $rows = $ogsMonth->where('user_id', $uid);
                    if ($subscribed->has($uid)) {
                        $storno = $rows->where('status', Order::STATUS_CANCELLED)
                            ->map(fn ($r) => $r->date->toDateString())
                            ->filter(fn ($ds) => isset($openMonthDays[$ds]))->unique()->count();
                        $attended = max(0, $openMonthCount - $storno);
                    } else {
                        $attended = $rows->where('status', Order::STATUS_ORDERED)
                            ->map(fn ($r) => $r->date->toDateString())
                            ->filter(fn ($ds) => isset($openMonthDays[$ds]))->unique()->count();
                    }
                    $monthByUser[$uid] = ($monthByUser[$uid] ?? 0) + $attended * $ogsPrice;
                }
            }
        }

        // Haushalts-Gesamtwert (oben rechts) = Summe aller Personen (inkl. OGS).
        $monthTotal = array_sum($monthByUser);

        // Wochenbudget je SCHÜLER (Rolle kantine_student; OGS/Sonstige haben keins).
        // Anzeige: Budget / genutzt (Menü-Kosten der Woche) / frei.
        $budgets = [];
        foreach ($eaterData as $e) {
            if ($e['group']?->role_id !== 'kantine_student') {
                continue;
            }
            $uid = $e['user']->id;
            $effective = $this->effectiveBudget($uid, $weekStart);
            $spent = array_sum($dayTotals[$uid] ?? []);
            $budgets[$uid] = [
                'general' => optional(Budget::where('user_id', $uid)->whereNull('week_start')->first())->amount,
                'special' => optional(Budget::where('user_id', $uid)->whereDate('week_start', $weekStart->toDateString())->first())->amount,
                'effective' => $effective,
                'spent' => $spent,
                'remaining' => $effective !== null ? $effective - $spent : null,
            ];
        }

        return view('schulkantine::orders.index', [
            'season' => $season,
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'days' => $days,
            'plan' => $plan,
            'eaters' => $eaterData,
            'weekReleased' => $weekReleased,
            'selected' => $selected,
            'ogsCancelled' => $ogsCancelled,
            'ogsOrdered' => $ogsOrdered,
            'subscribed' => $subscribed,
            'dayTotals' => $dayTotals,
            'monthTotal' => $monthTotal,
            'monthByUser' => $monthByUser,
            'ogsPrice' => $ogsPrice,
            'budgets' => $budgets,
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

        if ($mode === CustomerGroup::MODE_JA_NEIN) {
            return $this->handleOgs($request, $season, $eater, $date, $deadline, $data['attend'] ?? '1');
        }

        return $this->handleMenu($request, $season, $eater, $date, $deadline, $data);
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
        ]);

        $season = Season::where('is_active', true)->firstOrFail();
        $eater = User::findOrFail($data['eater_id']);

        abort_unless($this->mayOrderFor($user, $eater), 403, 'Du darfst für diese Person kein Abo verwalten.');

        $group = CustomerGroup::forUser($eater);
        abort_unless($group && $group->ordering_mode === CustomerGroup::MODE_JA_NEIN, 422, 'Nur OGS-Esser haben ein Abo.');

        $active = $data['active'] === '1';
        Subscription::updateOrCreate(
            ['season_id' => $season->id, 'user_id' => $eater->id],
            ['active' => $active],
        );

        return back()->with('status', $active
            ? $eater->name.': Abo aktiviert – isst wieder an allen Öffnungstagen (außer abbestellten).'
            : $eater->name.': Abo abbestellt – isst nur noch an einzeln angehakten Tagen.');
    }

    /**
     * Wochenbudget eines Schülers setzen/entfernen (nur durch die Eltern).
     * scope=general → allgemein (jede Woche); scope=week → nur diese Woche.
     * Leerer Betrag entfernt das jeweilige Budget.
     */
    public function budget(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'eater_id' => ['required', 'integer'],
            'scope' => ['required', 'in:general,week'],
            'week' => ['nullable', 'date'],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
        ]);

        $eater = User::findOrFail($data['eater_id']);

        // Nur ein Elternteil darf das Budget festlegen – nicht das Kind selbst.
        abort_if($user->id === $eater->id, 403, 'Nur Eltern dürfen das Budget festlegen.');
        abort_unless($this->mayOrderFor($user, $eater), 403, 'Du darfst für diese Person kein Budget festlegen.');

        $group = CustomerGroup::forUser($eater);
        abort_unless($group && $group->role_id === 'kantine_student', 422, 'Ein Budget gibt es nur für Schüler.');

        $weekStart = $data['scope'] === 'week'
            ? Carbon::parse($data['week'] ?? 'today')->startOfWeek(Carbon::MONDAY)
            : null;

        $query = Budget::where('user_id', $eater->id);
        $weekStart ? $query->whereDate('week_start', $weekStart->toDateString()) : $query->whereNull('week_start');
        $existing = $query->first();

        $label = $weekStart ? 'für diese Woche' : 'allgemein';

        if (! $request->filled('amount')) {
            $existing?->delete();

            return back()->with('status', $eater->name.': Budget '.$label.' entfernt.');
        }

        $amount = (float) $data['amount'];
        if ($existing) {
            $existing->update(['amount' => $amount]);
        } else {
            Budget::create([
                'user_id' => $eater->id,
                'week_start' => $weekStart?->toDateString(),
                'amount' => $amount,
            ]);
        }

        return back()->with('status', $eater->name.': Wochenbudget '.$label.' auf '.number_format($amount, 2, ',', '.').' € gesetzt.');
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
            ->with('dish')
            ->first();

        abort_if(! $menu, 422, 'Dieses Gericht steht an dem Tag nicht auf dem Speiseplan.');
        abort_if((int) $menu->dish->category_id !== $categoryId, 422, 'Gericht passt nicht zur Kategorie.');

        if (! $deadline->canOrder($season, $date)) {
            return back()->withErrors(['bestellung' => 'Die Bestellfrist für diesen Tag ist abgelaufen.']);
        }

        // Wochenbudget (nur Schüler): Die Menü-Kosten der Woche dürfen das von den
        // Eltern gesetzte Limit nicht übersteigen. Beim Wechsel innerhalb einer
        // Kategorie zählt nur die Differenz (alter Preis raus, neuer rein).
        $group = CustomerGroup::forUser($eater);
        if ($group && $group->role_id === 'kantine_student') {
            $weekStart = $date->copy()->startOfWeek(Carbon::MONDAY);
            $budget = $this->effectiveBudget($eater->id, $weekStart);
            if ($budget !== null) {
                $weekSpent = (float) Order::where('user_id', $eater->id)
                    ->where('season_id', $season->id)
                    ->where('status', Order::STATUS_ORDERED)
                    ->whereNotNull('category_id')
                    ->whereBetween('date', [$weekStart->toDateString(), $weekStart->copy()->addDays(6)->toDateString()])
                    ->sum('price_snapshot');
                $oldPrice = $existing ? (float) $existing->price_snapshot : 0.0;
                $after = $weekSpent - $oldPrice + (float) $menu->dish->price;
                if ($after > $budget + 0.001) {
                    return back()->withErrors(['bestellung' =>
                        'Wochenbudget überschritten: Limit '.number_format($budget, 2, ',', '.').' €, '
                        .'diese Bestellung ergäbe '.number_format($after, 2, ',', '.').' €.']);
                }
            }
        }

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

        return back()->with('status', 'Bestellung gespeichert: '.$menu->dish->name.' für '.$eater->name.' am '.$date->format('d.m.Y').'.');
    }

    // -------------------------------------------------------------- OGS ja/nein

    private function handleOgs(Request $request, Season $season, User $eater, Carbon $date, DeadlineService $deadline, string $attend)
    {
        $subscribed = Subscription::where('season_id', $season->id)->where('user_id', $eater->id)->exists();

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
            // Ohne Abo braucht es eine explizite Bestell-Zeile.
            if (! $subscribed && ! $orderRow) {
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
        // Bei Abo: Abbestellung als storniert-Zeile festhalten.
        if ($subscribed && ! $cancelOrder) {
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

    /**
     * Wirksames Wochenbudget eines Schülers: das spezielle (diese Woche) hat
     * Vorrang vor dem allgemeinen. Null = kein Limit hinterlegt.
     */
    private function effectiveBudget(int $userId, Carbon $weekStart): ?float
    {
        $special = Budget::where('user_id', $userId)
            ->whereDate('week_start', $weekStart->toDateString())->value('amount');
        if ($special !== null) {
            return (float) $special;
        }

        $general = Budget::where('user_id', $userId)->whereNull('week_start')->value('amount');

        return $general !== null ? (float) $general : null;
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
