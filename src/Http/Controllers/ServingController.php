<?php

namespace Intranet\Modules\Schulkantine\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Intranet\Modules\Schulkantine\Models\Budget;
use Intranet\Modules\Schulkantine\Models\ChildCategoryPermission;
use Intranet\Modules\Schulkantine\Models\CustomerGroup;
use Intranet\Modules\Schulkantine\Models\Dish;
use Intranet\Modules\Schulkantine\Models\Menu;
use Intranet\Modules\Schulkantine\Models\NfcChip;
use Intranet\Modules\Schulkantine\Models\Order;
use Intranet\Modules\Schulkantine\Models\Season;
use Intranet\Modules\Schulkantine\Models\Serving;
use Intranet\Modules\Schulkantine\Models\Subscription;
use Intranet\Modules\Schulkantine\Support\Access;
use Intranet\Modules\Schulkantine\Support\DeadlineService;

/**
 * Ausgabe & Betrieb (Phase 4). Das Küchen-/Ausgabepersonal arbeitet hier mit der
 * TATSÄCHLICHEN Ausgabe (kantine_servings), nicht mit den Vorbestellungen:
 *
 *  - index()        Ausgabeliste eines Tages, umschaltbar Tagesmenü ⇄ OGS.
 *                   Abhaken je Esser + spontane Abholung erfassen.
 *  - toggle()       Einen Esser als „ausgegeben" markieren / zurücknehmen.
 *  - spontaneous()  Spontane Abholung (ohne/nach Vorbestellung) erfassen.
 *  - destroy()      Eine Ausgabe-Zeile entfernen (v. a. spontane Abholung).
 *  - quantities()   Mengenplanung je Gericht + No-Shows („bestellt, nicht geholt").
 *  - ogsList()      OGS-Sammelliste: heute essende OGS-Kinder (für den Betreuer).
 *
 * Zugriff über die Betriebs-Rollen (siehe Support\Access).
 */
class ServingController
{
    // --------------------------------------------------------- Ausgabeliste

    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless(Access::canViewServings($user), 403, 'Kein Zugriff auf die Ausgabelisten.');

        $season = Season::where('is_active', true)->first();
        if (! $season) {
            return view('schulkantine::servings.index', ['season' => null]);
        }

        $date = $this->resolveDate($request, $season);
        $group = $request->query('group') === 'ogs' ? 'ogs' : 'menu';
        $open = $season->isOpenOn($date);
        $canServe = Access::canServe($user);

        $rows = [];
        if ($open) {
            $rows = $group === 'ogs'
                ? $this->ogsRows($season, $date)
                : $this->menuRows($season, $date);
        }

        // Spontane Abholungen des Tages (getrennt gelistet) + Auswahl fürs Erfassen.
        $spontaneous = Serving::where('season_id', $season->id)
            ->whereDate('date', $date->toDateString())
            ->where('spontaneous', true)
            ->with(['user', 'dish.category'])
            ->orderBy('id')
            ->get();

        $walkinDishes = Dish::whereHas('category', fn ($q) => $q->where('allows_walkin', true))
            ->with('category')
            ->orderBy('name')
            ->get();

        // Simulations-Chips: die AKTIVEN Chips der heute gelisteten Menü-Esser
        // (für die Chip-Simulation, wenn kein NFC-Gerät vorhanden ist). Nur aktive
        // Chips → ein zurückgegebener Chip taucht nicht als „gültig" auf. OGS hat keine.
        $simChips = collect();
        if ($group === 'menu' && ! empty($rows)) {
            $rowUsers = collect($rows)->keyBy(fn ($r) => $r['user']->id);
            $simChips = NfcChip::active()->whereIn('user_id', $rowUsers->keys())->get()
                ->map(fn ($c) => [
                    'uid' => $c->uid,
                    'name' => $rowUsers[$c->user_id]['user']->name ?? 'Unbekannt',
                    'served' => (bool) ($rowUsers[$c->user_id]['served'] ?? false),
                ])
                ->sortBy('name')
                ->values();
        }

        return view('schulkantine::servings.index', [
            'simChips' => $simChips,
            'season' => $season,
            'date' => $date,
            'open' => $open,
            'group' => $group,
            'rows' => $rows,
            'canServe' => $canServe,
            'spontaneous' => $spontaneous,
            'walkinDishes' => $walkinDishes,
            'walkinUsers' => $walkinDishes->isNotEmpty() ? User::orderBy('name')->get(['id', 'name']) : collect(),
            'closedReason' => $open ? null : $this->closedReason($season, $date),
        ] + $this->dayNav($season, $date, ['group' => $group]));
    }

    /**
     * Einen Esser für einen Tag als „ausgegeben" abhaken – oder das Abhaken
     * wieder zurücknehmen (Umschalter). Legt je aktiver Vorbestellung eine
     * Ausgabe-Zeile an (Menü: je Gericht; OGS: eine Zeile ja/nein).
     */
    public function toggle(Request $request)
    {
        $user = $request->user();
        abort_unless(Access::canServe($user), 403, 'Du darfst die Ausgabe nicht abhaken.');

        $data = $request->validate([
            'eater_id' => ['required', 'integer'],
            'date' => ['required', 'date'],
        ]);

        $season = Season::where('is_active', true)->firstOrFail();
        $eater = User::findOrFail($data['eater_id']);
        $date = Carbon::parse($data['date'])->startOfDay();

        // Bereits abgehakt? → zurücknehmen (nur die vorbestellten Zeilen; spontane
        // Abholungen bleiben unberührt und werden separat entfernt).
        if ($this->isServed($season, $eater, $date)) {
            Serving::where('season_id', $season->id)
                ->where('user_id', $eater->id)
                ->whereDate('date', $date->toDateString())
                ->where('spontaneous', false)
                ->delete();

            return back()->with('status', 'Zurückgenommen: '.$eater->name.' am '.$date->format('d.m.Y').'.');
        }

        $result = $this->createServingsFor($season, $eater, $date, $user->id);

        if ($result['created'] === 0) {
            return back()->withErrors(['ausgabe' => $eater->name.' hat für diesen Tag keine Bestellung – nutze „spontane Abholung".']);
        }

        return back()->with('status', 'Ausgegeben: '.$eater->name.' am '.$date->format('d.m.Y').'.');
    }

    /**
     * NFC-Chip nachschlagen: UID → Esser + heutige Bestellung. Es wird NICHTS
     * ausgegeben – die Antwort füllt das Ausgabe-Modal, in dem das Personal dann
     * bestätigt (Abhaken läuft über toggle()). Antwort als JSON.
     */
    public function lookup(Request $request)
    {
        $user = $request->user();
        abort_unless(Access::canServe($user), 403, 'Kein Zugriff auf die Ausgabe.');

        $data = $request->validate([
            'uid' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
        ]);

        $season = Season::where('is_active', true)->firstOrFail();
        $date = Carbon::parse($data['date'])->startOfDay();

        $eater = NfcChip::userForUid($data['uid']);
        if (! $eater) {
            return response()->json(['found' => false]);
        }

        return response()->json(['found' => true] + $this->eaterServingInfo($season, $eater, $date));
    }

    /** Wie lookup(), aber direkt per Esser (Modal aus der Liste „öffnen"). */
    public function lookupEater(Request $request)
    {
        $user = $request->user();
        abort_unless(Access::canServe($user), 403, 'Kein Zugriff auf die Ausgabe.');

        $data = $request->validate([
            'eater_id' => ['required', 'integer'],
            'date' => ['required', 'date'],
        ]);

        $season = Season::where('is_active', true)->firstOrFail();
        $date = Carbon::parse($data['date'])->startOfDay();
        $eater = User::findOrFail($data['eater_id']);

        return response()->json(['found' => true] + $this->eaterServingInfo($season, $eater, $date));
    }

    /**
     * Ausgabe aus dem Modal bestätigen: je bestelltem Gericht wird erfasst, ob es
     * genommen (ausgegeben) oder NICHT genommen (declined + Grund) wurde. Ersetzt
     * die bisherigen Ausgabe-Zeilen des Essers an dem Tag.
     */
    public function serveConfirm(Request $request)
    {
        $user = $request->user();
        abort_unless(Access::canServe($user), 403, 'Du darfst die Ausgabe nicht abhaken.');

        $data = $request->validate([
            'eater_id' => ['required', 'integer'],
            'date' => ['required', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_id' => ['required', 'integer'],
            'items.*.outcome' => ['required', 'in:taken,alternative,declined'],
            'items.*.reason' => ['nullable', 'string', 'max:100'],
        ]);

        $season = Season::where('is_active', true)->firstOrFail();
        $eater = User::findOrFail($data['eater_id']);
        $date = Carbon::parse($data['date'])->startOfDay();

        $orders = Order::where('season_id', $season->id)
            ->where('user_id', $eater->id)
            ->whereDate('date', $date->toDateString())
            ->whereNotNull('category_id')
            ->where('status', Order::STATUS_ORDERED)
            ->get()
            ->keyBy('id');

        if ($orders->isEmpty()) {
            return response()->json(['ok' => false, 'status' => 'no_order', 'name' => $eater->name]);
        }

        // Bisherige (nicht-spontane) Ausgabe-Zeilen ersetzen.
        Serving::where('season_id', $season->id)
            ->where('user_id', $eater->id)
            ->whereDate('date', $date->toDateString())
            ->where('spontaneous', false)
            ->delete();

        foreach ($data['items'] as $item) {
            $order = $orders->get((int) $item['order_id']);
            if (! $order) {
                continue;
            }
            $outcome = $item['outcome'];
            Serving::create([
                'season_id' => $season->id,
                'user_id' => $eater->id,
                'date' => $date->toDateString(),
                'order_id' => $order->id,
                'dish_id' => $order->dish_id,
                'category_id' => $order->category_id,
                'price_snapshot' => $order->price_snapshot, // immer wie bestellt berechnet
                'spontaneous' => false,
                'declined' => $outcome === 'declined',
                'decline_reason' => $outcome === 'declined' ? (trim((string) ($item['reason'] ?? '')) ?: 'nicht genommen') : null,
                'alternative' => $outcome === 'alternative',
                'served_by' => $user->id,
            ]);
        }

        return response()->json(['ok' => true, 'status' => 'served', 'name' => $eater->name]);
    }

    /**
     * Aufbereitete Ausgabe-Info eines Essers für Modal/Lookup: Gruppe, Bestellung
     * (mit Sonderkost-Warnungen), Ausgabe-Status. Verändert nichts.
     *
     * @return array<string, mixed>
     */
    private function eaterServingInfo(Season $season, User $eater, Carbon $date): array
    {
        $eater->loadMissing(['kantineAllergens', 'kantineDiets', 'roles']);
        $group = CustomerGroup::forUser($eater);
        $mode = $group?->ordering_mode;

        $allergenIds = $eater->kantineAllergens->pluck('id');
        $dietIds = $eater->kantineDiets->pluck('id');

        $dishes = [];
        $hasOrder = false;

        if ($mode === CustomerGroup::MODE_JA_NEIN) {
            $hasOrder = $this->isOgsAttending($season, $eater, $date);
        } else {
            // Bereits erfasste Ausgabe-Zeilen (für Vorbelegung des Status je Gericht).
            $existing = Serving::where('season_id', $season->id)
                ->where('user_id', $eater->id)
                ->whereDate('date', $date->toDateString())
                ->where('spontaneous', false)
                ->get()
                ->keyBy('order_id');

            $orders = Order::where('season_id', $season->id)
                ->where('user_id', $eater->id)
                ->whereDate('date', $date->toDateString())
                ->whereNotNull('category_id')
                ->where('status', Order::STATUS_ORDERED)
                ->with(['dish.category', 'dish.allergens', 'dish.additives', 'dish.unsuitableDiets'])
                ->get()
                ->sortBy(fn (Order $o) => $o->dish?->category?->sort_order ?? 999);

            $hasOrder = $orders->isNotEmpty();
            foreach ($orders as $o) {
                $dish = $o->dish;
                $sv = $existing->get($o->id);
                $dishes[] = [
                    'order_id' => $o->id,
                    'name' => $dish?->name,
                    'category' => $dish?->category?->name,
                    'price' => (float) $o->price_snapshot,
                    // Sonderkost des GERICHTS
                    'allergens' => $dish ? $dish->allergens->pluck('name')->all() : [],
                    'additives' => $dish ? $dish->additives->pluck('name')->all() : [],
                    'unsuitable' => $dish ? $dish->unsuitableDiets->pluck('name')->all() : [],
                    // Konflikte mit der Sonderkost des ABHOLERS
                    'allergenHits' => $dish ? $dish->allergens->whereIn('id', $allergenIds)->pluck('name')->all() : [],
                    'dietHits' => $dish ? $dish->unsuitableDiets->whereIn('id', $dietIds)->pluck('name')->all() : [],
                    // Aktueller Status (falls schon erfasst)
                    'handled' => $sv !== null,
                    'declined' => $sv ? (bool) $sv->declined : false,
                    'alternative' => $sv ? (bool) $sv->alternative : false,
                    'reason' => $sv?->decline_reason,
                ];
            }
        }

        return [
            'user_id' => $eater->id,
            'name' => $eater->name,
            'group' => $group?->name,
            'mode' => $mode,
            'served' => $this->isServed($season, $eater, $date),
            'hasOrder' => $hasOrder,
            'dishes' => $dishes,
            'allergens' => $eater->kantineAllergens->pluck('name')->all(),
            'diets' => $eater->kantineDiets->pluck('name')->all(),
            'warn' => collect($dishes)->contains(fn ($d) => ! empty($d['allergenHits']) || ! empty($d['dietHits'])),
        ];
    }

    /**
     * Legt die Ausgabe-Zeilen für einen Esser an einem Tag an (Menü: je Gericht;
     * OGS: eine ja/nein-Zeile, nur wenn heute teilnehmend). Gemeinsam genutzt von
     * manuellem Abhaken und NFC-Scan.
     *
     * @return array{created: int, dishes: array<int, string>}
     */
    private function createServingsFor(Season $season, User $eater, Carbon $date, int $servedBy): array
    {
        $dateStr = $date->toDateString();
        $group = CustomerGroup::forUser($eater);

        if ($group && $group->ordering_mode === CustomerGroup::MODE_JA_NEIN) {
            // OGS: nur abhaken, wenn das Kind heute überhaupt teilnimmt.
            if (! $this->isOgsAttending($season, $eater, $date)) {
                return ['created' => 0, 'dishes' => []];
            }

            $order = Order::where('season_id', $season->id)
                ->where('user_id', $eater->id)
                ->whereDate('date', $dateStr)
                ->whereNull('category_id')
                ->where('status', Order::STATUS_ORDERED)
                ->first();

            Serving::create([
                'season_id' => $season->id,
                'user_id' => $eater->id,
                'date' => $dateStr,
                'order_id' => $order?->id,
                'price_snapshot' => $season->ogs_price,
                'spontaneous' => false,
                'served_by' => $servedBy,
            ]);

            return ['created' => 1, 'dishes' => ['OGS-Essen']];
        }

        // Menü-Modus: je aktiver Bestellung (Kategorie) eine Ausgabe-Zeile.
        $orders = Order::where('season_id', $season->id)
            ->where('user_id', $eater->id)
            ->whereDate('date', $dateStr)
            ->whereNotNull('category_id')
            ->where('status', Order::STATUS_ORDERED)
            ->with('dish')
            ->get();

        if ($orders->isEmpty()) {
            return ['created' => 0, 'dishes' => []];
        }

        $dishes = [];
        foreach ($orders as $order) {
            Serving::create([
                'season_id' => $season->id,
                'user_id' => $eater->id,
                'date' => $dateStr,
                'order_id' => $order->id,
                'dish_id' => $order->dish_id,
                'category_id' => $order->category_id,
                'price_snapshot' => $order->price_snapshot,
                'spontaneous' => false,
                'served_by' => $servedBy,
            ]);
            if ($order->dish?->name) {
                $dishes[] = $order->dish->name;
            }
        }

        return ['created' => $orders->count(), 'dishes' => $dishes];
    }

    /** Ist dieser Esser an dem Tag bereits (vorbestellt) ausgegeben? */
    private function isServed(Season $season, User $eater, Carbon $date): bool
    {
        return Serving::where('season_id', $season->id)
            ->where('user_id', $eater->id)
            ->whereDate('date', $date->toDateString())
            ->where('spontaneous', false)
            ->exists();
    }

    /** Nimmt dieser OGS-Esser an dem Tag teil (Abo minus Abbestellung oder Einzelbestellung)? */
    private function isOgsAttending(Season $season, User $eater, Carbon $date): bool
    {
        $dateStr = $date->toDateString();

        $subscribed = Subscription::where('season_id', $season->id)
            ->where('user_id', $eater->id)
            ->where('active', true)
            ->exists();

        $cancelled = Order::where('season_id', $season->id)
            ->where('user_id', $eater->id)
            ->whereNull('category_id')
            ->whereDate('date', $dateStr)
            ->where('status', Order::STATUS_CANCELLED)
            ->exists();

        $ordered = Order::where('season_id', $season->id)
            ->where('user_id', $eater->id)
            ->whereNull('category_id')
            ->whereDate('date', $dateStr)
            ->where('status', Order::STATUS_ORDERED)
            ->exists();

        return ($subscribed && ! $cancelled) || $ordered;
    }

    /**
     * Spontane Abholung erfassen: ein Kind nimmt (ohne Vorbestellung oder nach
     * Fristablauf) ein Gericht. Nur für Kategorien mit „spontane Abholung erlaubt"
     * (allows_walkin) und OHNE Mengen-Limit – der physische Vorrat begrenzt, nicht
     * das System.
     */
    public function spontaneous(Request $request)
    {
        $user = $request->user();
        abort_unless(Access::canServe($user), 403, 'Du darfst keine Ausgabe erfassen.');

        $data = $request->validate([
            'eater_id' => ['required', 'integer', 'exists:users,id'],
            'date' => ['required', 'date'],
            'dish_id' => ['required', 'integer', 'exists:kantine_dishes,id'],
        ]);

        $season = Season::where('is_active', true)->firstOrFail();
        $eater = User::findOrFail($data['eater_id']);
        $date = Carbon::parse($data['date'])->startOfDay();
        $dish = Dish::with('category')->findOrFail($data['dish_id']);

        abort_unless($season->isOpenOn($date), 422, 'An diesem Tag hat die Kantine nicht geöffnet.');
        abort_unless(
            $dish->category && $dish->category->allows_walkin,
            422,
            'Für die Kategorie dieses Gerichts ist keine spontane Abholung erlaubt.'
        );

        // Eltern-Freigabe: darf dieses Kind diese Kategorie überhaupt spontan kaufen?
        if (! ChildCategoryPermission::canWalkin($eater->id, $dish->category_id)) {
            return back()->withErrors(['ausgabe' =>
                'Für '.$eater->name.' ist der Spontankauf dieser Kategorie nicht freigegeben.']);
        }

        // Wochenbudget für Spontankäufe (falls von den Eltern gesetzt).
        $budget = Budget::weeklyAmount($eater->id);
        if ($budget !== null) {
            $weekStart = $date->copy()->startOfWeek(Carbon::MONDAY);
            $spent = (float) Serving::where('user_id', $eater->id)
                ->where('spontaneous', true)
                ->whereBetween('date', [$weekStart->toDateString(), $weekStart->copy()->addDays(6)->toDateString()])
                ->sum('price_snapshot');
            if ($spent + (float) $dish->price > $budget + 0.001) {
                return back()->withErrors(['ausgabe' =>
                    $eater->name.': Wochenbudget für Spontankäufe erreicht (Limit '.number_format($budget, 2, ',', '.').' €, '
                    .'diese Woche schon '.number_format($spent, 2, ',', '.').' € genutzt).']);
            }
        }

        Serving::create([
            'season_id' => $season->id,
            'user_id' => $eater->id,
            'date' => $date->toDateString(),
            'order_id' => null,
            'dish_id' => $dish->id,
            'category_id' => $dish->category_id,
            'price_snapshot' => $dish->price,
            'spontaneous' => true,
            'served_by' => $user->id,
        ]);

        return back()->with('status', 'Spontane Abholung erfasst: '.$dish->name.' für '.$eater->name.'.');
    }

    /** Eine Ausgabe-Zeile entfernen (v. a. eine spontane Abholung korrigieren). */
    public function destroy(Request $request, Serving $serving)
    {
        abort_unless(Access::canServe($request->user()), 403, 'Du darfst keine Ausgabe entfernen.');

        $name = $serving->user?->name;
        $serving->delete();

        return back()->with('status', 'Ausgabe-Zeile entfernt'.($name ? ' ('.$name.')' : '').'.');
    }

    // ------------------------------------------------------- Mengen (4d)

    /**
     * Mengenliste: wie viele Portionen je Gericht sind aus den Vorbestellungen
     * zu kochen. Diese Liste braucht die Küche nach Bestellschluss (Einkauf) und
     * zum Kochstart – daher auch als PDF (siehe mengenPdf()).
     */
    public function quantities(Request $request)
    {
        $user = $request->user();
        abort_unless(Access::canViewServings($user), 403, 'Kein Zugriff auf die Mengen.');

        $season = Season::where('is_active', true)->first();
        if (! $season) {
            return view('schulkantine::servings.quantities', ['season' => null]);
        }

        $date = $this->resolveDate($request, $season);
        $open = $season->isOpenOn($date);
        $data = $open ? $this->quantitiesData($season, $date) : ['menuByDish' => [], 'ogs' => $this->emptyOgs()];

        return view('schulkantine::servings.quantities', [
            'season' => $season,
            'date' => $date,
            'open' => $open,
            'menuByDish' => $data['menuByDish'],
            'ogs' => $data['ogs'],
            'ogsPrice' => (float) ($season->ogs_price ?? 0),
            'closedReason' => $open ? null : $this->closedReason($season, $date),
        ] + $this->dayNav($season, $date, []));
    }

    /** Mengenliste als PDF (zum Download/Drucken für die Küche). */
    public function mengenPdf(Request $request)
    {
        $user = $request->user();
        abort_unless(Access::canViewServings($user), 403, 'Kein Zugriff auf die Mengen.');

        $season = Season::where('is_active', true)->firstOrFail();
        $date = $this->resolveDate($request, $season);
        abort_unless($season->isOpenOn($date), 422, 'An diesem Tag hat die Kantine nicht geöffnet.');

        $data = $this->quantitiesData($season, $date);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('schulkantine::servings.mengen_pdf', [
            'season' => $season,
            'date' => $date,
            'menuByDish' => $data['menuByDish'],
            'ogs' => $data['ogs'],
            'ogsPrice' => (float) ($season->ogs_price ?? 0),
            'generatedAt' => Carbon::now(),
        ])->setPaper('a4', 'portrait');

        return $pdf->download('mengenliste-'.$date->toDateString().'.pdf');
    }

    // ----------------------------------------------------- No-Shows (4d)

    /** No-Shows: bestellt, aber (noch) nicht als ausgegeben abgehakt. */
    public function noShows(Request $request)
    {
        $user = $request->user();
        abort_unless(Access::canViewServings($user), 403, 'Kein Zugriff auf die No-Shows.');

        $season = Season::where('is_active', true)->first();
        if (! $season) {
            return view('schulkantine::servings.noshows', ['season' => null]);
        }

        $date = $this->resolveDate($request, $season);
        $open = $season->isOpenOn($date);
        $data = $open ? $this->quantitiesData($season, $date) : ['noShows' => [], 'ogs' => $this->emptyOgs()];

        return view('schulkantine::servings.noshows', [
            'season' => $season,
            'date' => $date,
            'open' => $open,
            'noShows' => $data['noShows'],
            'ogs' => $data['ogs'],
            'closedReason' => $open ? null : $this->closedReason($season, $date),
        ] + $this->dayNav($season, $date, []));
    }

    /**
     * Gemeinsame Auswertung eines Tages: Portionen je Gericht, No-Shows und die
     * OGS-Zahlen. Von Mengen-Seite, No-Show-Seite und PDF gemeinsam genutzt.
     */
    private function quantitiesData(Season $season, Carbon $date): array
    {
        $dateStr = $date->toDateString();

        // Aktive Menü-Bestellungen des Tages → Portionen je Gericht.
        $orders = Order::where('season_id', $season->id)
            ->whereDate('date', $dateStr)
            ->whereNotNull('category_id')
            ->where('status', Order::STATUS_ORDERED)
            ->with(['dish.category', 'user'])
            ->get();

        // Ausgegebene Zeilen des Tages (nicht spontan) je order_id.
        $servedOrderIds = Serving::where('season_id', $season->id)
            ->whereDate('date', $dateStr)
            ->where('spontaneous', false)
            ->whereNotNull('order_id')
            ->pluck('order_id')
            ->flip();

        // Spontane Abholungen je Gericht.
        $spontaneousByDish = Serving::where('season_id', $season->id)
            ->whereDate('date', $dateStr)
            ->where('spontaneous', true)
            ->whereNotNull('dish_id')
            ->get()
            ->countBy('dish_id');

        $menuByDish = [];
        foreach ($orders->groupBy('dish_id') as $dishId => $dishOrders) {
            $dish = $dishOrders->first()->dish;
            $ordered = $dishOrders->count();
            $served = $dishOrders->filter(fn ($o) => $servedOrderIds->has($o->id))->count();
            $menuByDish[] = [
                'dish' => $dish,
                'category' => $dish?->category?->name ?? 'Ohne Kategorie',
                'color' => $dish?->category?->color,
                'ordered' => $ordered,
                'served' => $served,
                'spontaneous' => (int) ($spontaneousByDish[$dishId] ?? 0),
                'openNoShow' => $ordered - $served,
            ];
        }

        // Reihenfolge wie auf „Essen bestellen": nach dem Tages-Speiseplan.
        // Kategorien in Reihenfolge ihres ersten Auftretens im Plan, Gerichte
        // innerhalb der Kategorie in Plan-Reihenfolge (Menü-sort_order).
        [$dishPos, $catFirstPos] = $this->planOrder($season, $date);
        usort($menuByDish, function ($a, $b) use ($dishPos, $catFirstPos) {
            $ca = $catFirstPos[$a['dish']?->category_id] ?? PHP_INT_MAX;
            $cb = $catFirstPos[$b['dish']?->category_id] ?? PHP_INT_MAX;
            $da = $dishPos[$a['dish']?->id] ?? PHP_INT_MAX;
            $db = $dishPos[$b['dish']?->id] ?? PHP_INT_MAX;

            return [$ca, $da, $a['dish']?->name] <=> [$cb, $db, $b['dish']?->name];
        });

        // No-Shows Menü: aktive Bestellung, aber keine (vorbestellte) Ausgabe.
        $noShows = [];
        foreach ($orders as $order) {
            if (! $servedOrderIds->has($order->id)) {
                $noShows[] = ['user' => $order->user, 'dish' => $order->dish?->name];
            }
        }

        // OGS: Teilnehmer heute + davon ausgegeben.
        $ogsEaters = $this->attendingOgs($season, $date);
        $ogsServedIds = Serving::where('season_id', $season->id)
            ->whereDate('date', $dateStr)
            ->where('spontaneous', false)
            ->whereIn('user_id', $ogsEaters->pluck('id'))
            ->pluck('user_id')
            ->flip();

        return [
            'menuByDish' => $menuByDish,
            'noShows' => $noShows,
            'ogs' => [
                'attending' => $ogsEaters->count(),
                'served' => $ogsEaters->filter(fn ($u) => $ogsServedIds->has($u->id))->count(),
                'noShows' => $ogsEaters->reject(fn ($u) => $ogsServedIds->has($u->id))->values(),
            ],
        ];
    }

    private function emptyOgs(): array
    {
        return ['attending' => 0, 'served' => 0, 'noShows' => collect()];
    }

    /**
     * Plan-Reihenfolge des Tages (wie „Essen bestellen" sie zeigt): Menüs nach
     * sort_order/id. Liefert [dish_id → Position, category_id → erste Position],
     * damit die Mengenliste die Gerichte in derselben Reihenfolge sortiert.
     *
     * @return array{0: array<int,int>, 1: array<int,int>}
     */
    private function planOrder(Season $season, Carbon $date): array
    {
        $menus = Menu::where('season_id', $season->id)
            ->whereDate('date', $date->toDateString())
            ->with('dish:id,category_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $dishPos = [];
        $catFirstPos = [];
        $i = 0;
        foreach ($menus as $menu) {
            $dishPos[$menu->dish_id] ??= $i;
            $catId = $menu->dish?->category_id;
            if ($catId !== null) {
                $catFirstPos[$catId] ??= $i;
            }
            $i++;
        }

        return [$dishPos, $catFirstPos];
    }

    // -------------------------------------------------- OGS-Sammelliste (4d)

    public function ogsList(Request $request)
    {
        $user = $request->user();
        abort_unless(Access::canViewOgsList($user), 403, 'Kein Zugriff auf die OGS-Sammelliste.');

        $season = Season::where('is_active', true)->first();
        if (! $season) {
            return view('schulkantine::servings.ogs', ['season' => null]);
        }

        $date = $this->resolveDate($request, $season);
        $open = $season->isOpenOn($date);

        $eaters = collect();
        if ($open) {
            $attending = $this->attendingOgs($season, $date);
            $servedIds = Serving::where('season_id', $season->id)
                ->whereDate('date', $date->toDateString())
                ->where('spontaneous', false)
                ->whereIn('user_id', $attending->pluck('id'))
                ->pluck('user_id')
                ->flip();

            $eaters = $attending->map(fn (User $u) => [
                'user' => $u,
                'served' => $servedIds->has($u->id),
                'allergens' => $u->kantineAllergens->pluck('name')->all(),
                'diets' => $u->kantineDiets->pluck('name')->all(),
            ])->values();
        }

        return view('schulkantine::servings.ogs', [
            'season' => $season,
            'date' => $date,
            'open' => $open,
            'eaters' => $eaters,
            'closedReason' => $open ? null : $this->closedReason($season, $date),
        ] + $this->dayNav($season, $date, []));
    }

    // ----------------------------------------------------------------- Helfer

    /** Zeilen der Tagesmenü-Ausgabeliste (Schüler & Sonstige) für einen Tag. */
    private function menuRows(Season $season, Carbon $date): array
    {
        $orders = Order::where('season_id', $season->id)
            ->whereDate('date', $date->toDateString())
            ->whereNotNull('category_id')
            ->where('status', Order::STATUS_ORDERED)
            ->with(['dish.category', 'dish.allergens', 'dish.unsuitableDiets'])
            ->get();

        if ($orders->isEmpty()) {
            return [];
        }

        $userIds = $orders->pluck('user_id')->unique();
        $users = User::whereIn('id', $userIds)
            ->with(['kantineAllergens', 'kantineDiets', 'roles'])
            ->get();

        $servedUserIds = $this->servedUserIds($season, $date, $userIds);
        $groups = CustomerGroup::all()->keyBy('role_id');

        // Reihenfolge wie auf „Essen bestellen": Kinder zuerst, Erwachsene zuletzt.
        // Global heißt das nach Gruppen-Priorität (OGS → Schüler → Sonstige),
        // dann alphabetisch. Auf dem Tagesmenü-Tab kommen so Schüler vor Sonstige.
        $priority = array_flip(CustomerGroup::ROLE_PRIORITY);
        $users = $users->sortBy([
            fn (User $u) => $this->groupPriority($u, $priority),
            fn (User $u) => mb_strtolower($u->name),
        ])->values();

        $rows = [];
        foreach ($users as $eater) {
            $allergenIds = $eater->kantineAllergens->pluck('id');
            $dietIds = $eater->kantineDiets->pluck('id');

            // Gerichte je Person in Kategorie-Reihenfolge (wie im Speiseplan).
            $dishes = $orders->where('user_id', $eater->id)
                ->sortBy(fn (Order $o) => $o->dish?->category?->sort_order ?? 999)
                ->map(function (Order $o) use ($allergenIds, $dietIds) {
                $dish = $o->dish;
                $allergenHits = $dish ? $dish->allergens->whereIn('id', $allergenIds)->pluck('name')->all() : [];
                $dietHits = $dish ? $dish->unsuitableDiets->whereIn('id', $dietIds)->pluck('name')->all() : [];

                return [
                    'dish' => $dish,
                    'category' => $dish?->category?->name,
                    'color' => $dish?->category?->color,
                    'allergenHits' => $allergenHits,
                    'dietHits' => $dietHits,
                ];
            })->values();

            $rows[] = [
                'user' => $eater,
                'group' => CustomerGroup::forUser($eater, $groups)?->name,
                'served' => $servedUserIds->has($eater->id),
                'dishes' => $dishes,
                'warn' => $dishes->contains(fn ($d) => $d['allergenHits'] || $d['dietHits']),
                'allergens' => $eater->kantineAllergens->pluck('name')->all(),
                'diets' => $eater->kantineDiets->pluck('name')->all(),
            ];
        }

        return $rows;
    }

    /**
     * Sortier-Priorität eines Essers anhand seiner Gruppen-Rolle
     * (OGS → Schüler → Sonstige). Niedriger = weiter oben. Wer keine der
     * Gruppen-Rollen hat, landet ganz unten.
     */
    private function groupPriority(User $user, array $priority): int
    {
        $roleIds = $user->roles->pluck('role_id');
        foreach ($priority as $roleId => $rank) {
            if ($roleIds->contains($roleId)) {
                return $rank;
            }
        }

        return count($priority);
    }

    /** Zeilen der OGS-Ausgabeliste (ja/nein) für einen Tag. */
    private function ogsRows(Season $season, Carbon $date): array
    {
        $attending = $this->attendingOgs($season, $date);
        if ($attending->isEmpty()) {
            return [];
        }

        $servedUserIds = $this->servedUserIds($season, $date, $attending->pluck('id'));

        return $attending->map(fn (User $u) => [
            'user' => $u,
            'group' => 'OGS',
            'served' => $servedUserIds->has($u->id),
            'dishes' => collect(), // OGS: kein Gericht (nur ja/nein)
            'warn' => false,
            'allergens' => $u->kantineAllergens->pluck('name')->all(),
            'diets' => $u->kantineDiets->pluck('name')->all(),
        ])->all();
    }

    /** IDs der Esser, die an dem Tag bereits (vorbestellt) ausgegeben wurden. */
    private function servedUserIds(Season $season, Carbon $date, Collection $userIds): Collection
    {
        return Serving::where('season_id', $season->id)
            ->whereDate('date', $date->toDateString())
            ->where('spontaneous', false)
            ->whereIn('user_id', $userIds)
            ->pluck('user_id')
            ->flip();
    }

    /**
     * Die heute essenden OGS-Kinder: mit aktivem Abo (minus Abbestellungen) ODER
     * mit ausdrücklicher Einzelbestellung an dem Tag. (Gleiche Logik wie in der
     * Vorbestellung/Abrechnung.)
     */
    private function attendingOgs(Season $season, Carbon $date): Collection
    {
        $dateStr = $date->toDateString();

        $subscribed = Subscription::where('season_id', $season->id)
            ->where('active', true)
            ->pluck('user_id');

        $cancelled = Order::where('season_id', $season->id)
            ->whereNull('category_id')
            ->whereDate('date', $dateStr)
            ->where('status', Order::STATUS_CANCELLED)
            ->pluck('user_id');

        $ordered = Order::where('season_id', $season->id)
            ->whereNull('category_id')
            ->whereDate('date', $dateStr)
            ->where('status', Order::STATUS_ORDERED)
            ->pluck('user_id');

        $attendingIds = $subscribed->diff($cancelled)->merge($ordered)->unique();

        if ($attendingIds->isEmpty()) {
            return collect();
        }

        return User::whereIn('id', $attendingIds)
            ->with(['kantineAllergens', 'kantineDiets'])
            ->orderBy('name')
            ->get();
    }

    /** Standard-Tag (heute → nächster Öffnungstag) bzw. der per ?date gewählte. */
    private function resolveDate(Request $request, Season $season): Carbon
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
            $base = $request->filled('date') ? Carbon::parse($request->query('date')) : $default;
        } catch (\Exception $e) {
            $base = $default;
        }

        return $base->startOfDay();
    }

    /** Vorheriger/nächster Öffnungstag für die Tages-Navigation. */
    private function dayNav(Season $season, Carbon $date, array $extra): array
    {
        $prev = (new DeadlineService)->previousOpenDay($season, $date);

        $next = $date->copy()->addDay();
        while ($next->lte($season->end_date) && ! $season->isOpenOn($next)) {
            $next->addDay();
        }
        $hasNext = $next->lte($season->end_date) && $season->isOpenOn($next);

        return [
            'prevDate' => $prev?->toDateString(),
            'nextDate' => $hasNext ? $next->toDateString() : null,
            'navExtra' => $extra,
        ];
    }

    /** Warum ist an dem Tag geschlossen? (für die Anzeige) */
    private function closedReason(Season $season, Carbon $date): string
    {
        if ($date->lt($season->start_date) || $date->gt($season->end_date)) {
            return 'außerhalb der Saison';
        }
        $closed = $season->closedDays()->whereDate('date', $date->toDateString())->first();

        return $closed->reason ?? 'geschlossen';
    }
}
