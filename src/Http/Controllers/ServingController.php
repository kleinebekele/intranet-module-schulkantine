<?php

namespace Intranet\Modules\Schulkantine\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Intranet\Modules\Schulkantine\Models\Budget;
use Intranet\Modules\Schulkantine\Models\ChildCategoryPermission;
use Intranet\Modules\Schulkantine\Models\CustomerGroup;
use Intranet\Modules\Schulkantine\Models\Dish;
use Intranet\Modules\Schulkantine\Models\Menu;
use Intranet\Modules\Schulkantine\Models\MenuDay;
use Intranet\Modules\Schulkantine\Models\NfcChip;
use Intranet\Modules\Schulkantine\Models\Order;
use Intranet\Modules\Schulkantine\Models\Season;
use Intranet\Modules\Schulkantine\Models\Serving;
use Intranet\Modules\Schulkantine\Models\Subscription;
use Intranet\Modules\Schulkantine\Support\Access;
use Intranet\Modules\Schulkantine\Support\DeadlineService;
use Intranet\Modules\Schulkantine\Support\OgsAttendance;

/**
 * Ausgabe & Betrieb (Phase 4). Das Küchen-/Ausgabepersonal arbeitet hier mit der
 * TATSÄCHLICHEN Ausgabe (kantine_servings), nicht mit den Vorbestellungen:
 *
 *  - index()        Administrative Ausgabe-Übersicht eines Tages (nur lesen):
 *                   Tab „Übersicht" (Mengen/No-Shows/OGS-Zahl) + Tab „Details"
 *                   (jeder Esser einzeln, Tagesmenü und OGS getrennt). Das
 *                   eigentliche Ausgeben/Abhaken läuft am Ausgabe-Terminal.
 *  - toggle()       Einen Esser als „ausgegeben" markieren / zurücknehmen.
 *  - spontaneous()  Spontane Abholung (ohne/nach Vorbestellung) erfassen.
 *  - destroy()      Eine Ausgabe-Zeile entfernen (v. a. spontane Abholung).
 *  - quantities()   Mengenplanung je Gericht + No-Shows („bestellt, nicht geholt").
 *  - ogsPdf()       OGS-Sammelliste als PDF (heute essende OGS-Kinder, zum Drucken).
 *  - bestellerPdf() Besteller des Tagesmenüs als PDF (Notfall-Liste, gleiche Form).
 *
 * Zugriff über die Betriebs-Rollen (siehe Support\Access).
 */
class ServingController
{
    // --------------------------------------------------------- Ausgabeliste

    public function index(Request $request)
    {
        $user = $request->user();
        // Seit Einführung des Ausgabe-Terminals ist diese Seite eine reine
        // administrative Übersicht (nur lesen). Ausgeben/Abhaken läuft am Terminal.
        // Zugriff daher für Betriebs-Rollen UND OGS-Betreuer (deren Sammelliste
        // im Details-Tab aufgegangen ist).
        abort_unless(
            Access::canViewServings($user) || Access::canViewOgsList($user),
            403,
            'Kein Zugriff auf die Ausgabelisten.'
        );

        $season = Season::where('is_active', true)->first();
        if (! $season) {
            return view('schulkantine::servings.index', ['season' => null]);
        }

        // Die ganze Kalenderwoche des gewählten Tages, ein Öffnungstag unter dem anderen.
        $date = $this->resolveDate($request, $season);
        $monday = $date->copy()->startOfWeek(Carbon::MONDAY);
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $d = $monday->copy()->addDays($i);
            if ($season->isOpenOn($d)) {
                $days[] = $this->ausgabeTag($season, $d);
            }
        }

        return view('schulkantine::servings.index', [
            'season' => $season,
            'date' => $date,
            'monday' => $monday,
            'days' => $days,
            'prevWeek' => $this->wocheMitOeffnung($season, $monday, -1),
            'nextWeek' => $this->wocheMitOeffnung($season, $monday, 1),
            'thisWeek' => $monday->isSameDay(Carbon::today()->startOfWeek(Carbon::MONDAY)),
        ]);
    }

    /**
     * Alle Daten eines Öffnungstags für die Ausgabe-Übersicht: Übersicht (Mengen je
     * Menü/Gericht, No-Shows, OGS-Zahl) und Details (jeder Esser einzeln).
     *
     * @return array<string, mixed>
     */
    private function ausgabeTag(Season $season, Carbon $date): array
    {
        $quant = $this->quantitiesData($season, $date);

        // No-Shows je Esser gruppiert (Person → Posten), damit die Liste kurz bleibt;
        // mit Klasse und sortiert wie die übrigen Listen.
        $noShowGroups = [];
        $groups = CustomerGroup::all()->keyBy('role_id');
        foreach ($quant['noShows'] as $ns) {
            $u = $ns['user'];
            $key = $u?->id ?? 0;
            if (! isset($noShowGroups[$key])) {
                $u?->loadMissing(['roles', 'kantineInfo']);
                $noShowGroups[$key] = [
                    'klasse' => $u ? self::klasseOderRolle($u, CustomerGroup::forUser($u, $groups)) : null,
                    'sortName' => $u ? self::sortName($u) : 'Unbekannt',
                    'dishes' => [],
                ];
            }
            $noShowGroups[$key]['dishes'][] = $ns['dish'];
        }
        usort($noShowGroups, [self::class, 'nachKlasseUndName']);

        return [
            'date' => $date,
            'isToday' => $date->isToday(),
            // Erst ab dem Ausgabetag gibt es „nicht abgeholt" – davor ist alles noch offen.
            'begonnen' => $date->lte(Carbon::today()),
            'menuByDish' => $quant['menuByDish'],
            'noShowGroups' => $noShowGroups,
            'ogsQuant' => $quant['ogs'],
            'menuRows' => $this->menuRows($season, $date),
            'ogsRows' => $this->ogsEatersDetailed($season, $date),
        ];
    }

    /** Erster Öffnungstag der nächsten Woche (Richtung ±1) mit Öffnungstagen, sonst null. */
    private function wocheMitOeffnung(Season $season, Carbon $monday, int $richtung): ?string
    {
        for ($w = 1; $w <= 10; $w++) {
            $mo = $monday->copy()->addWeeks($richtung * $w);
            if ($mo->copy()->addDays(6)->lt($season->start_date) || $mo->gt($season->end_date)) {
                return null;
            }
            for ($i = 0; $i < 7; $i++) {
                $d = $mo->copy()->addDays($i);
                if ($season->isOpenOn($d)) {
                    return $d->toDateString();
                }
            }
        }

        return null;
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
     * Kurzes Etikett neben dem Namen im Terminal: die Klasse (importierte
     * Teilnehmer-Info), sonst die Personengruppe nach Rolle (Lehrer, Mitarbeiter,
     * Eltern, Schüler), sonst die Kundengruppe. „Klasse: Sonstige" war irreführend.
     */
    /**
     * Welche Personengruppe zählt, wenn jemand mehrere Rollen hat (erste gewinnt,
     * gleiche Priorität wie personLabel) – und ihr Rang in der Terminal-Suche
     * (kleiner = weiter oben: Schüler zuerst, Eltern zuletzt).
     */
    public const PERSONEN_RANG = ['teacher' => 2, 'staff' => 1, 'parent' => 3, 'student' => 0];

    /** Personengruppe für Sortierung und Farbe: student/staff/teacher/parent, sonst 'other'. */
    public static function personenRolle(User $user): string
    {
        $roleIds = $user->roles->pluck('role_id');
        foreach (array_keys(self::PERSONEN_RANG) as $rolle) {
            if ($roleIds->contains($rolle)) {
                return $rolle;
            }
        }

        return 'other';
    }

    public static function personLabel(User $user, ?CustomerGroup $group): string
    {
        $info = trim((string) $user->kantineInfo?->info);
        if ($info !== '') {
            return preg_match('/^\d/', $info) ? 'Klasse '.$info : $info;
        }

        $roleIds = $user->roles->pluck('role_id');
        foreach (['teacher' => 'Lehrer', 'staff' => 'Mitarbeiter', 'parent' => 'Eltern', 'student' => 'Schüler'] as $rolle => $text) {
            if ($roleIds->contains($rolle)) {
                return $text;
            }
        }

        return $group?->name ?? '–';
    }

    /**
     * Aufbereitete Ausgabe-Info eines Essers für Modal/Lookup: Gruppe, Bestellung
     * (mit Sonderkost-Warnungen), Ausgabe-Status. Verändert nichts.
     *
     * @return array<string, mixed>
     */
    private function eaterServingInfo(Season $season, User $eater, Carbon $date): array
    {
        $eater->loadMissing(['kantineAllergens', 'kantineDiets', 'roles', 'kantineInfo']);
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
                ->with(['dish.category', 'dish.allergens', 'dish.additives', 'dish.unsuitableDiets', 'menuDay'])
                ->get()
                ->sortBy(fn (Order $o) => $o->dish?->category?->sort_order ?? 999);

            $hasOrder = $orders->isNotEmpty();
            foreach ($orders as $o) {
                $dish = $o->dish;
                $sv = $existing->get($o->id);
                $dishes[] = [
                    'order_id' => $o->id,
                    'dish_id' => $o->dish_id,
                    'category_id' => $o->category_id,
                    // Teil eines Menüs? Dann wählt das Terminal das Menü als Ganzes.
                    'menu_day_id' => $o->menu_day_id,
                    'menu_name' => $o->menuDay?->name,
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

        // Bereits erfasste spontane Abholungen dieses Essers am Tag (für das Modal).
        $walkin = Serving::where('season_id', $season->id)
            ->where('user_id', $eater->id)
            ->whereDate('date', $date->toDateString())
            ->where('spontaneous', true)
            ->with('dish')
            ->orderBy('id')
            ->get()
            ->map(fn (Serving $s) => [
                'id' => $s->id,
                'dish_id' => $s->dish_id,
                'label' => $s->label,
                'name' => $s->dish?->name ?? $s->label ?? '—',
                'price' => (float) $s->price_snapshot,
            ])->values();

        return [
            'user_id' => $eater->id,
            'name' => $eater->name,
            'group' => $group?->name,
            'label' => self::personLabel($eater, $group),
            'mode' => $mode,
            'served' => $this->isServed($season, $eater, $date),
            'hasOrder' => $hasOrder,
            'dishes' => $dishes,
            'walkin' => $walkin,
            'allergens' => $eater->kantineAllergens->pluck('name')->all(),
            'diets' => $eater->kantineDiets->pluck('name')->all(),
            // IDs für den Konflikt-Abgleich der Terminal-Kacheln (Client-seitig).
            'allergenIds' => $allergenIds->all(),
            'dietIds' => $dietIds->all(),
            'warn' => collect($dishes)->contains(fn ($d) => ! empty($d['allergenHits']) || ! empty($d['dietHits'])),
            // Wochenbudget – nur, wenn die Eltern die Anzeige an der Ausgabe eingeschaltet haben.
            'budget' => $this->budgetAnzeige($eater, $date),
            // Preise dieser Person je Terminal-Kachel (Lehrer zahlen mehr als Schüler).
            'preise' => $this->terminalPreise($season, $eater, $date),
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
                'price_snapshot' => $season->ogsPreis(),
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

    /** Nimmt dieser OGS-Esser an dem Tag teil? Standardtage (Abo-Muster) plus
     *  einzelne An-/Abmeldungen – zentral über OgsAttendance. */
    private function isOgsAttending(Season $season, User $eater, Carbon $date): bool
    {
        $dateStr = $date->toDateString();

        $sub = Subscription::where('season_id', $season->id)
            ->where('user_id', $eater->id)
            ->first();

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

        return OgsAttendance::attends($sub, $date, $ordered, $cancelled);
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

        // Einheitliche Fehlerausgabe: JSON (aus dem Chip-Modal) oder Redirect (Formular).
        $fail = fn (string $msg) => $request->wantsJson()
            ? response()->json(['ok' => false, 'error' => $msg], 200)
            : back()->withErrors(['ausgabe' => $msg]);

        abort_unless($season->isOpenOn($date), 422, 'An diesem Tag hat die Mensa nicht geöffnet.');

        // OGS-Kinder (ja/nein) sind vom Spontankauf ausgeschlossen: Sie bekommen ihr
        // OGS-Essen über das Abo, nicht spontan am Tresen. (UI blendet sie ohnehin aus;
        // hier als serverseitiges Sicherheitsnetz.)
        if (CustomerGroup::forUser($eater)?->ordering_mode === CustomerGroup::MODE_JA_NEIN) {
            return $fail($eater->name.' gehört zur OGS-Gruppe (ja/nein) – dafür ist kein Spontankauf möglich.');
        }

        abort_unless(
            $dish->category && $dish->category->allows_walkin,
            422,
            'Für die Kategorie dieses Gerichts ist keine spontane Abholung erlaubt.'
        );

        // Eltern-Freigabe: darf dieses Kind diese Kategorie überhaupt spontan kaufen?
        if (! ChildCategoryPermission::canWalkin($eater->id, $dish->category_id)) {
            return $fail('Für '.$eater->name.' ist der Spontankauf dieser Kategorie nicht freigegeben.');
        }

        // Wochenbudget für Spontankäufe (Kinder: ohne Eintrag 0 €). Überschreiten nur,
        // wenn die Ausgabe es ausdrücklich erlaubt und keine ältere offen ist.
        // Prüfung und Buchung in EINER Transaktion unter Sperre des Essers – sonst
        // könnten zwei Ausgabe-Geräte gleichzeitig dasselbe Restbudget verbuchen.
        $ergebnis = DB::transaction(function () use ($request, $season, $eater, $date, $dish, $user) {
            $this->esserSperren($eater);

            $pruefung = $this->budgetPruefen($eater, $date, $dish->preisFuer($eater), $request->boolean('budget_ueberschreiten'));
            if (! $pruefung['ok']) {
                return $pruefung;
            }
            $this->ueberschreitungMerken($pruefung['ueber'] ?? null, $user->id);

            return ['ok' => true, 'serving' => Serving::create([
                'season_id' => $season->id,
                'user_id' => $eater->id,
                'date' => $date->toDateString(),
                'order_id' => null,
                'dish_id' => $dish->id,
                'category_id' => $dish->category_id,
                'price_snapshot' => $dish->preisFuer($eater),
                'spontaneous' => true,
                'served_by' => $user->id,
            ])];
        });
        if (! $ergebnis['ok']) {
            return $request->wantsJson()
                ? response()->json(['ok' => false, 'error' => $ergebnis['fehler'], 'budget_frage' => $ergebnis['frage'] ?? false], 200)
                : back()->withErrors(['ausgabe' => $ergebnis['fehler']]);
        }
        $serving = $ergebnis['serving'];

        if ($request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'text' => 'Spontan erfasst: '.$dish->name.' für '.$eater->name.'.',
                'item' => ['id' => $serving->id, 'name' => $dish->name, 'price' => $dish->preisFuer($eater)],
            ]);
        }

        return back()->with('status', 'Spontane Abholung erfasst: '.$dish->name.' für '.$eater->name.'.');
    }

    /**
     * Wochenbudget prüfen (Spontankäufe: Extras + Nachschlag). Kinder haben ohne
     * Eintrag 0 €. Reicht es nicht, fragt die Ausgabe (frage = true) – erlaubt sie
     * es, entsteht eine Überschreitung, die bis zur Bestätigung durch die Eltern
     * „in der Schwebe" steht; solange ist keine weitere Überschreitung möglich.
     *
     * @param  bool  $tagErsetzt  die Buchung ersetzt die Extras dieses Tages (Terminal)
     * @return array{ok: bool, fehler?: string, frage?: bool, ueber?: array}
     */
    private function budgetPruefen(User $eater, Carbon $date, float $extra, bool $erlauben, bool $tagErsetzt = false): array
    {
        $woche = $date->copy()->startOfWeek(Carbon::MONDAY);
        $budget = Budget::fuerWoche($eater->id, $woche);
        if ($budget === null || $extra <= 0) {
            return ['ok' => true];
        }

        $genutzt = Budget::genutzt($eater->id, $woche);
        if ($tagErsetzt) {
            $genutzt -= (float) Serving::where('user_id', $eater->id)->where('spontaneous', true)
                ->whereDate('date', $date->toDateString())->sum('price_snapshot');
        }
        $nachher = round($genutzt + $extra, 2);
        if ($nachher <= $budget + 0.001) {
            return ['ok' => true];
        }

        $euro = fn ($v) => number_format((float) $v, 2, ',', '.').' €';
        $text = "{$eater->name}: Wochenbudget {$euro($budget)}, schon genutzt {$euro($genutzt)}, mit diesem Kauf {$euro($nachher)}.";
        if (Budget::offeneUeberschreitungen($eater->id)->isNotEmpty()) {
            return ['ok' => false, 'fehler' => $text.' Eine frühere Überschreitung haben die Eltern noch nicht bestätigt – keine weitere möglich.'];
        }
        if (! $erlauben) {
            return ['ok' => false, 'frage' => true, 'fehler' => $text.' Budget um '.$euro($nachher - $budget).' überschreiten?'];
        }

        return ['ok' => true, 'ueber' => [
            'user_id' => $eater->id, 'woche' => $woche->toDateString(), 'tag' => $date->toDateString(),
            'budget' => $budget, 'genutzt' => $nachher, 'ueber' => round($nachher - $budget, 2),
        ]];
    }

    /**
     * Preise einer Person für alle Kacheln des Tages: Menüs ('md<id>') nach dem
     * Linear-Preis der Person, Gerichte (id) nach ihrem Vertragspreis.
     *
     * @return array<string, float>
     */
    private function terminalPreise(Season $season, User $eater, Carbon $date): array
    {
        $preise = [];
        $menuDays = MenuDay::where('season_id', $season->id)->whereDate('date', $date->toDateString())
            ->with(['template', 'slots.dish'])->get();
        foreach ($menuDays as $md) {
            $preise['md'.$md->id] = \Intranet\Modules\Schulkantine\Support\LinearPreise::menuPreis($md, $eater);
            foreach ($md->slots as $slot) {
                if ($slot->dish) {
                    $preise[$slot->dish->id] = $slot->dish->preisFuer($eater);
                }
            }
        }
        Menu::where('season_id', $season->id)->whereDate('date', $date->toDateString())->with('dish')->get()
            ->each(function (Menu $m) use (&$preise, $eater) {
                if ($m->dish) {
                    $preise[$m->dish->id] = $m->dish->preisFuer($eater);
                }
            });

        return $preise;
    }

    /** @return array{betrag: float, genutzt: float, rest: float, offen: bool}|null */
    private function budgetAnzeige(User $eater, Carbon $date): ?array
    {
        if (! Budget::amAusgabeterminal($eater->id)) {
            return null;
        }
        $woche = $date->copy()->startOfWeek(Carbon::MONDAY);
        $betrag = Budget::fuerWoche($eater->id, $woche);
        if ($betrag === null) {
            return null;
        }
        $genutzt = Budget::genutzt($eater->id, $woche);

        return ['betrag' => $betrag, 'genutzt' => $genutzt, 'rest' => round($betrag - $genutzt, 2),
            'offen' => Budget::offeneUeberschreitungen($eater->id)->isNotEmpty()];
    }

    /**
     * Sperrt den Benutzer-Datensatz des Essers bis zum Ende der laufenden
     * Transaktion. Muss die ERSTE Abfrage der Transaktion sein: Erst danach
     * gelesene Budget-/Ausgabe-Stände sehen die Buchungen, die ein paralleler
     * Aufruf gerade abgeschlossen hat. Gesperrt wird der Esser statt der
     * Budget-Zeile, weil es die (Kinder ohne Eintrag: 0 €) nicht immer gibt.
     */
    private function esserSperren(User $eater): void
    {
        User::query()->whereKey($eater->getKey())->lockForUpdate()->first();
    }

    /** Eine erlaubte Überschreitung festhalten (wartet dann auf die Eltern). */
    private function ueberschreitungMerken(?array $ueber, int $erlaubtVon): void
    {
        if ($ueber) {
            DB::table('kantine_budget_ueberschreitungen')->insert($ueber + [
                'erlaubt_von' => $erlaubtVon, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    /** Eine Ausgabe-Zeile entfernen (v. a. eine spontane Abholung korrigieren). */
    public function destroy(Request $request, Serving $serving)
    {
        abort_unless(Access::canServe($request->user()), 403, 'Du darfst keine Ausgabe entfernen.');

        $name = $serving->user?->name;
        $serving->delete();

        if ($request->wantsJson()) {
            return response()->json(['ok' => true]);
        }

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
            'menuByDish' => self::nurBestellte($data['menuByDish']),
            'ogs' => $data['ogs'],
            'ogsPrice' => (float) ($season->ogsPreis() ?? 0),
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
        abort_unless($season->isOpenOn($date), 422, 'An diesem Tag hat die Mensa nicht geöffnet.');

        $data = $this->quantitiesData($season, $date);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('schulkantine::servings.mengen_pdf', [
            'season' => $season,
            'date' => $date,
            'menuByDish' => self::nurBestellte($data['menuByDish']),
            'ogs' => $data['ogs'],
            'ogsPrice' => (float) ($season->ogsPreis() ?? 0),
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
     * Gemeinsame Auswertung eines Tages: Portionen je Posten, No-Shows und die
     * OGS-Zahlen. Von Ausgabe-Übersicht, Mengen-Seite, No-Show-Seite und PDF
     * gemeinsam genutzt. Ein Menü ist EIN Posten (je Esser einmal gezählt), nicht
     * seine Einzelgerichte – sonst stünde jede Menü-Bestellung mehrfach da.
     * Zeilen entstehen aus dem ganzen Tagesangebot (Menüs + Speiseplan), damit
     * auch rein spontan Verkauftes seine Zahl bekommt.
     */
    private function quantitiesData(Season $season, Carbon $date): array
    {
        $dateStr = $date->toDateString();

        // Aktive Menü-Bestellungen des Tages.
        $orders = Order::where('season_id', $season->id)
            ->whereDate('date', $dateStr)
            ->whereNotNull('category_id')
            ->where('status', Order::STATUS_ORDERED)
            ->with(['dish.category', 'user', 'menuDay'])
            ->get();

        // Ausgegebene Zeilen des Tages (nicht spontan) je order_id.
        $servedOrderIds = Serving::where('season_id', $season->id)
            ->whereDate('date', $dateStr)
            ->where('spontaneous', false)
            ->whereNotNull('order_id')
            ->pluck('order_id')
            ->flip();

        // Spontane Abholungen des Tages (mit Gericht).
        $spontaneous = Serving::where('season_id', $season->id)
            ->whereDate('date', $dateStr)
            ->where('spontaneous', true)
            ->whereNotNull('dish_id')
            ->with('dish.category')
            ->get();

        $rows = [];
        $zeile = function (string $key, array $kopf) use (&$rows) {
            $rows[$key] ??= $kopf + ['ordered' => 0, 'served' => 0, 'spontaneous' => 0];
        };
        $gerichtZeile = function (?Dish $dish) use ($zeile): ?string {
            if (! $dish) {
                return null;
            }
            $zeile('d'.$dish->id, [
                'name' => $dish->name, 'is_menu' => false, 'inhalt' => [],
                'dish' => $dish, 'category' => $dish->category?->name ?? 'Ohne Kategorie',
                'color' => $dish->category?->color,
            ]);

            return 'd'.$dish->id;
        };

        // 1) Tagesangebot: Menüs (mit Inhalt) und Gerichte des Speiseplans.
        $menuDays = MenuDay::where('season_id', $season->id)->whereDate('date', $dateStr)
            ->with('slots.dish')->orderBy('id')->get();
        foreach ($menuDays as $md) {
            $zeile('md'.$md->id, [
                'name' => $md->name, 'is_menu' => true,
                'inhalt' => $md->slots->map(fn ($sl) => $sl->dish?->name)->filter()->values()->all(),
                'dish' => null, 'category' => 'Menü', 'color' => null,
            ]);
        }
        $plan = Menu::where('season_id', $season->id)->whereDate('date', $dateStr)
            ->with('dish.category')->orderBy('sort_order')->orderBy('id')->get();
        foreach ($plan as $m) {
            $gerichtZeile($m->dish);
        }

        // 2) Bestellungen: ein Menü je Esser einmal, Einzelgerichte je Bestellung.
        $noShows = [];
        foreach ($orders->groupBy(fn (Order $o) => $o->menu_day_id ? $o->user_id.'|md'.$o->menu_day_id : 'o'.$o->id) as $gruppe) {
            $first = $gruppe->first();
            if ($first->menu_day_id) {
                $key = 'md'.$first->menu_day_id;
                $zeile($key, ['name' => $first->menuDay?->name ?? 'Menü', 'is_menu' => true, 'inhalt' => [],
                    'dish' => null, 'category' => 'Menü', 'color' => null]);
            } elseif (! ($key = $gerichtZeile($first->dish))) {
                continue;
            }
            $rows[$key]['ordered']++;
            if ($gruppe->contains(fn (Order $o) => $servedOrderIds->has($o->id))) {
                $rows[$key]['served']++;
            } else {
                // No-Show: aktive Bestellung, aber keine (vorbestellte) Ausgabe.
                $noShows[] = ['user' => $first->user, 'dish' => $rows[$key]['name']];
            }
        }

        // 3) Spontane Abholungen (immer Einzelgerichte).
        foreach ($spontaneous as $serving) {
            if ($key = $gerichtZeile($serving->dish)) {
                $rows[$key]['spontaneous']++;
            }
        }

        $menuByDish = [];
        foreach ($rows as $key => $row) {
            $row['key'] = $key;
            $row['openNoShow'] = $row['ordered'] - $row['served'];
            $menuByDish[] = $row;
        }

        // Reihenfolge: zuerst die Menüs, dann wie auf „Essen bestellen" nach dem
        // Tages-Speiseplan – Kategorien in Reihenfolge ihres ersten Auftretens,
        // Gerichte innerhalb der Kategorie in Plan-Reihenfolge.
        [$dishPos, $catFirstPos] = $this->planOrder($season, $date);
        $rang = fn ($r) => $r['is_menu']
            ? [0, 0, 0, $r['key']]
            : [1, $catFirstPos[$r['dish']?->category_id] ?? PHP_INT_MAX, $dishPos[$r['dish']?->id] ?? PHP_INT_MAX, $r['name']];
        usort($menuByDish, fn ($a, $b) => $rang($a) <=> $rang($b));

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

    /** Nur die Posten mit Vorbestellungen – die Kochliste braucht keine Nullzeilen. */
    private static function nurBestellte(array $menuByDish): array
    {
        return array_values(array_filter($menuByDish, fn ($r) => $r['ordered'] > 0));
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
            ->with(['dish'])
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

    /**
     * OGS-Sammelliste als PDF (zum Drucken für den OGS-Betreuer). Die Bildschirm-
     * ansicht ist in die Ausgabe-Übersicht (Details-Tab) gewandert; gedruckt wird
     * hier.
     */
    public function ogsPdf(Request $request)
    {
        $user = $request->user();
        abort_unless(
            Access::canViewOgsList($user) || Access::canViewServings($user),
            403,
            'Kein Zugriff auf die OGS-Sammelliste.'
        );

        $season = Season::where('is_active', true)->firstOrFail();
        $date = $this->resolveDate($request, $season);
        abort_unless($season->isOpenOn($date), 422, 'An diesem Tag hat die Mensa nicht geöffnet.');

        $eintraege = $this->ogsEatersDetailed($season, $date)->map(fn ($e) => [
            'klasse' => $e['klasse'],
            'sortName' => $e['sortName'],
            'zeilen' => ($e['allergens'] || $e['diets']) ? [['text' => '⚠ '.trim(
                ($e['allergens'] ? 'Allergien: '.implode(', ', $e['allergens']).'. ' : '')
                .($e['diets'] ? 'Diäten: '.implode(', ', $e['diets']).'.' : '')
            ), 'warn' => true]] : [],
        ]);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('schulkantine::servings.liste_pdf', [
            'titel' => 'OGS-Sammelliste',
            'zaehler' => 'Heute essende OGS-Kinder',
            'leer' => 'Heute isst kein OGS-Kind.',
            'hinweis' => 'Bis zur Abbestell-Frist kann sich die Liste noch ändern.',
            'season' => $season,
            'date' => $date,
            'eintraege' => $eintraege,
            'generatedAt' => Carbon::now(),
        ])->setPaper('a4', 'portrait');

        return $pdf->download('ogs-sammelliste-'.$date->toDateString().'.pdf');
    }

    /**
     * Besteller-Liste als PDF – gleiche Form wie die OGS-Sammelliste (Klasse, Name,
     * Kästchen), je Person die bestellten Gerichte. Notfall-Papier, falls Scanner oder
     * Terminal ausfallen: abhaken und später am Terminal nachtragen.
     */
    public function bestellerPdf(Request $request)
    {
        $user = $request->user();
        abort_unless(Access::canViewServings($user), 403, 'Kein Zugriff auf die Besteller-Liste.');

        $season = Season::where('is_active', true)->firstOrFail();
        $date = $this->resolveDate($request, $season);
        abort_unless($season->isOpenOn($date), 422, 'An diesem Tag hat die Mensa nicht geöffnet.');

        $eintraege = collect($this->menuRows($season, $date))->map(fn ($r) => [
            'klasse' => $r['klasse'],
            'sortName' => $r['sortName'],
            'zeilen' => array_values(array_filter([
                ['text' => $r['dishes']->pluck('name')->implode(', '), 'warn' => false],
                $r['warn'] ? ['text' => '⚠ Verträglichkeit: '.$r['dishes']->flatMap(fn ($d) => array_merge($d['allergenHits'], $d['dietHits']))->unique()->implode(', '), 'warn' => true] : null,
            ])),
        ])->sort(fn ($a, $b) => strcmp(self::sortierbar($a['sortName']), self::sortierbar($b['sortName']))
            ?: strnatcasecmp((string) $a['klasse'], (string) $b['klasse']))->values();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('schulkantine::servings.liste_pdf', [
            'titel' => 'Besteller-Liste Tagesmenü',
            'sortierung' => 'alphabetisch nach Nachname, Vorname',
            'zaehler' => 'Besteller heute',
            'leer' => 'Für diesen Tag liegen keine Menü-Bestellungen vor.',
            'hinweis' => 'Notfall-Liste: abhaken und später am Ausgabe-Terminal nachtragen.',
            'season' => $season,
            'date' => $date,
            'eintraege' => $eintraege,
            'generatedAt' => Carbon::now(),
        ])->setPaper('a4', 'portrait');

        return $pdf->download('besteller-'.$date->toDateString().'.pdf');
    }

    /**
     * Die heute essenden OGS-Kinder mit Ausgabe-Status und Verträglichkeiten –
     * für den Details-Tab der Ausgabe-Übersicht und die OGS-Sammelliste-PDF.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function ogsEatersDetailed(Season $season, Carbon $date): Collection
    {
        $attending = $this->attendingOgs($season, $date);
        if ($attending->isEmpty()) {
            return collect();
        }

        $servedIds = Serving::where('season_id', $season->id)
            ->whereDate('date', $date->toDateString())
            ->where('spontaneous', false)
            ->whereIn('user_id', $attending->pluck('id'))
            ->pluck('user_id')
            ->flip();

        $attending->loadMissing('roles');

        // Nachgetragen = ausdrücklich angemeldet, ohne dass das Abo den Tag abdeckt.
        $abos = Subscription::where('season_id', $season->id)->whereIn('user_id', $attending->pluck('id'))->get()->keyBy('user_id');
        $einzeln = Order::where('season_id', $season->id)->whereNull('category_id')
            ->whereDate('date', $date->toDateString())->where('status', Order::STATUS_ORDERED)
            ->whereIn('user_id', $attending->pluck('id'))->pluck('user_id')->flip();
        $nachgetragen = fn (User $u) => $einzeln->has($u->id)
            && ! (($abo = $abos->get($u->id)) && $abo->active && $abo->eatsWeekday($date->dayOfWeekIso));

        // Nach Klasse (natürlich: 2a vor 10a, ohne Klasse ans Ende), dann Nachname, Vorname.
        return $attending->map(function (User $u) use ($servedIds, $nachgetragen) {
            return [
                'user' => $u,
                'klasse' => self::klasse($u),
                'sortName' => self::sortName($u),
                'nachgetragen' => $nachgetragen($u),
                'served' => $servedIds->has($u->id),
                'allergens' => $u->kantineAllergens->pluck('name')->all(),
                'diets' => $u->kantineDiets->pluck('name')->all(),
            ];
        })->sort([self::class, 'nachKlasseUndName'])->values();
    }

    /**
     * OGS-Kind suchen, um es nachzutragen (OGS-Betreuer): alle Kinder der OGS-Gruppe
     * nach Namensteil, mit Klasse. Antwort als JSON.
     */
    public function ogsSuchen(Request $request)
    {
        abort_unless(Access::canViewOgsList($request->user()), 403, 'Kein Zugriff auf die OGS-Liste.');

        $q = trim((string) $request->input('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['results' => []]);
        }
        $ogsRoleIds = CustomerGroup::where('ordering_mode', CustomerGroup::MODE_JA_NEIN)->pluck('role_id');

        $users = User::where('name', 'like', '%'.$q.'%')
            ->whereHas('roles', fn ($r) => $r->whereIn('roles.role_id', $ogsRoleIds))
            ->with('roles')->orderBy('name')->limit(10)->get();

        return response()->json(['results' => $users->map(fn (User $u) => [
            'id' => $u->id, 'name' => self::sortName($u), 'klasse' => self::klasse($u),
        ])->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values()]);
    }

    /**
     * Ein OGS-Kind für einen Tag nachtragen, das ohne Anmeldung zum Essen kam (auch
     * rückwirkend). Legt die Anmeldung an – damit steht es auf der Sammelliste und
     * der Tag wird abgerechnet. Abbestellungen des Tages fallen dabei weg.
     */
    public function ogsNachtragen(Request $request)
    {
        abort_unless(Access::canViewOgsList($request->user()), 403, 'Kein Zugriff auf die OGS-Liste.');

        $data = $request->validate([
            'eater_id' => ['required', 'integer', 'exists:users,id'],
            'date' => ['required', 'date'],
        ]);
        $season = Season::where('is_active', true)->firstOrFail();
        $eater = User::findOrFail($data['eater_id']);
        $date = Carbon::parse($data['date'])->startOfDay();
        $zurueck = redirect()->to(route('module.schulkantine.servings.index', ['date' => $date->toDateString()]).'#tag-'.$date->toDateString());

        if (! $season->isOpenOn($date)) {
            return $zurueck->withErrors(['ogs' => 'An diesem Tag hat die Mensa nicht geöffnet.']);
        }
        if (CustomerGroup::forUser($eater)?->ordering_mode !== CustomerGroup::MODE_JA_NEIN) {
            return $zurueck->withErrors(['ogs' => $eater->name.' gehört nicht zur OGS.']);
        }
        if ($this->isOgsAttending($season, $eater, $date)) {
            return $zurueck->with('status', $eater->name.' steht am '.$date->format('d.m.Y').' schon auf der OGS-Liste.');
        }

        DB::transaction(function () use ($season, $eater, $date) {
            Order::where('season_id', $season->id)->where('user_id', $eater->id)->whereNull('category_id')
                ->whereDate('date', $date->toDateString())->delete();
            Order::create(['season_id' => $season->id, 'user_id' => $eater->id,
                'date' => $date->toDateString(), 'status' => Order::STATUS_ORDERED]);
        });
        \Intranet\Modules\Schulkantine\Support\MensaAudit::schreiben('kantine.bestellt',
            'OGS-Essen nachgetragen: '.$eater->name.' am '.$date->format('d.m.Y'), $eater, ['Tag' => $date->format('d.m.Y')]);

        return $zurueck->with('status', 'Nachgetragen: '.$eater->name.' isst am '.$date->format('d.m.Y').' mit.');
    }

    /** Ein nachgetragenes OGS-Kind wieder von der Liste des Tages nehmen. */
    public function ogsNachtragEntfernen(Request $request)
    {
        abort_unless(Access::canViewOgsList($request->user()), 403, 'Kein Zugriff auf die OGS-Liste.');

        $data = $request->validate([
            'eater_id' => ['required', 'integer', 'exists:users,id'],
            'date' => ['required', 'date'],
        ]);
        $season = Season::where('is_active', true)->firstOrFail();
        $eater = User::findOrFail($data['eater_id']);
        $date = Carbon::parse($data['date'])->startOfDay();

        Order::where('season_id', $season->id)->where('user_id', $eater->id)->whereNull('category_id')
            ->whereDate('date', $date->toDateString())->where('status', Order::STATUS_ORDERED)->delete();
        \Intranet\Modules\Schulkantine\Support\MensaAudit::schreiben('kantine.abbestellt',
            'OGS-Nachtrag entfernt: '.$eater->name.' am '.$date->format('d.m.Y'), $eater, ['Tag' => $date->format('d.m.Y')]);

        return redirect()->to(route('module.schulkantine.servings.index', ['date' => $date->toDateString()]).'#tag-'.$date->toDateString())
            ->with('status', 'Entfernt: '.$eater->name.' am '.$date->format('d.m.Y').'.');
    }

    /**
     * Sortierung der Listen: nach Klasse (natürlich: 2a vor 10a; Lehrer, Mitarbeiter …
     * folgen als Text nach den Klassen, ohne Angabe ganz ans Ende), dann Nachname, Vorname.
     */
    public static function nachKlasseUndName(array $a, array $b): int
    {
        if (($a['klasse'] === null) !== ($b['klasse'] === null)) {
            return $a['klasse'] === null ? 1 : -1;
        }

        return strnatcasecmp((string) $a['klasse'], (string) $b['klasse'])
            ?: strcmp(self::sortierbar($a['sortName']), self::sortierbar($b['sortName']));
    }

    /** Name in der Form „Nachname, Vorname" für Listen. */
    private static function sortName(User $user): string
    {
        [$vorname, $nachname] = self::namensteile($user->name);

        return $nachname !== '' ? $nachname.', '.$vorname : $vorname;
    }

    /** Klasse eines Schülers – sonst, was die Person ist (Lehrer, Mitarbeiter, Info-Text …). */
    private static function klasseOderRolle(User $user, ?CustomerGroup $group): ?string
    {
        $label = self::klasse($user) ?? self::personLabel($user, $group);
        $label = trim(preg_replace('/^Klasse\s+/u', '', $label));

        return $label === '' || $label === '–' ? null : $label;
    }

    /**
     * Vor- und Nachname aus dem Anzeigenamen „Vorname(n) Nachname" – das letzte Wort
     * gilt als Nachname (Linear liefert die Namen in dieser Form).
     *
     * @return array{0: string, 1: string}
     */
    private static function namensteile(string $name): array
    {
        $teile = preg_split('/\s+/u', trim($name)) ?: [];
        $nachname = count($teile) > 1 ? array_pop($teile) : '';

        return [implode(' ', $teile), $nachname];
    }

    /** Umlaut-tolerant vergleichbar (Ä wie A, ß wie ss) – unabhängig von der Server-Locale. */
    private static function sortierbar(string $text): string
    {
        return strtr(mb_strtolower($text), ['ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'ß' => 'ss', 'é' => 'e', 'è' => 'e']);
    }

    // ----------------------------------------------------------------- Helfer

    /** Zeilen der Tagesmenü-Ausgabeliste (Schüler & Sonstige) für einen Tag. */
    private function menuRows(Season $season, Carbon $date): array
    {
        $orders = Order::where('season_id', $season->id)
            ->whereDate('date', $date->toDateString())
            ->whereNotNull('category_id')
            ->where('status', Order::STATUS_ORDERED)
            ->with(['dish.category', 'dish.allergens', 'dish.unsuitableDiets', 'menuDay'])
            ->get();

        if ($orders->isEmpty()) {
            return [];
        }

        $userIds = $orders->pluck('user_id')->unique();
        $users = User::whereIn('id', $userIds)
            ->with(['kantineAllergens', 'kantineDiets', 'roles', 'kantineInfo'])
            ->get();

        $servedUserIds = $this->servedUserIds($season, $date, $userIds);
        $groups = CustomerGroup::all()->keyBy('role_id');

        $rows = [];
        foreach ($users as $eater) {
            $allergenIds = $eater->kantineAllergens->pluck('id');
            $dietIds = $eater->kantineDiets->pluck('id');

            // Posten je Person: ein Menü als EIN Posten (Menüname, Warnungen aller
            // seiner Gerichte), Einzelgerichte in Kategorie-Reihenfolge danach.
            $dishes = $orders->where('user_id', $eater->id)
                ->sortBy(fn (Order $o) => [$o->menu_day_id ? 0 : 1, $o->dish?->category?->sort_order ?? 999])
                ->groupBy(fn (Order $o) => $o->menu_day_id ? 'md'.$o->menu_day_id : 'o'.$o->id)
                ->map(function (Collection $gruppe) use ($allergenIds, $dietIds) {
                    $first = $gruppe->first();
                    $gerichte = $gruppe->map(fn (Order $o) => $o->dish)->filter();

                    return [
                        'name' => $first->menu_day_id ? ($first->menuDay?->name ?? 'Menü') : ($first->dish?->name ?? '—'),
                        'inhalt' => $first->menu_day_id ? $gerichte->pluck('name')->all() : [],
                        'allergenHits' => $gerichte->flatMap(fn (Dish $d) => $d->allergens->whereIn('id', $allergenIds)->pluck('name'))->unique()->values()->all(),
                        'dietHits' => $gerichte->flatMap(fn (Dish $d) => $d->unsuitableDiets->whereIn('id', $dietIds)->pluck('name'))->unique()->values()->all(),
                    ];
                })->values();

            $group = CustomerGroup::forUser($eater, $groups);
            $rows[] = [
                'user' => $eater,
                'group' => $group?->name,
                'klasse' => self::klasseOderRolle($eater, $group),
                'sortName' => self::sortName($eater),
                'served' => $servedUserIds->has($eater->id),
                'dishes' => $dishes,
                'warn' => $dishes->contains(fn ($d) => $d['allergenHits'] || $d['dietHits']),
                'allergens' => $eater->kantineAllergens->pluck('name')->all(),
                'diets' => $eater->kantineDiets->pluck('name')->all(),
            ];
        }

        // Wie die OGS-Sammelliste: Klasse, dann Nachname, Vorname (Erwachsene nach den Klassen).
        usort($rows, [self::class, 'nachKlasseUndName']);

        return $rows;
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
        $attendingIds = $this->attendingOgsIds($season, $date);

        if ($attendingIds->isEmpty()) {
            return collect();
        }

        return User::whereIn('id', $attendingIds)
            ->with(['kantineAllergens', 'kantineDiets'])
            ->orderBy('name')
            ->get();
    }

    /**
     * IDs der heute essenden OGS-Kinder (Abo minus Abbestellung ODER Einzelbestellung).
     * Basis für attendingOgs() und den reinen Zähler (Wochenüberblick) – ohne die
     * User zu hydratisieren.
     */
    private function attendingOgsIds(Season $season, Carbon $date): Collection
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

        return $subscribed->diff($cancelled)->merge($ordered)->unique()->values();
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

    // ------------------------------------------------- Ausgabe-Terminal (Kiosk)

    /**
     * Touch-optimierte Vollbild-Ausgabe (eigenes Layout ohne Header/Sidebar).
     * Liest nur; gebucht wird über terminalCommit(). Der Chip-Stempel läuft im
     * Frontend über die vorhandene lookup()-Route (UID → Esser + Bestellung).
     */
    public function terminal(Request $request)
    {
        $user = $request->user();
        abort_unless(Access::canServe($user), 403, 'Kein Zugriff auf das Ausgabe-Terminal.');

        $season = Season::where('is_active', true)->first();
        if (! $season) {
            return view('schulkantine::servings.terminal', ['season' => null]);
        }

        $date = $this->resolveDate($request, $season);
        $open = $season->isOpenOn($date);

        // Web-NFC-Fallback: alle aktiven Chips zum Simulieren eines Scans.
        $simChips = NfcChip::active()->with('user')->get()
            ->filter(fn ($c) => $c->user !== null)
            ->map(fn ($c) => ['uid' => $c->uid, 'name' => $c->user->name])
            ->sortBy('name')->values();

        // Gibt/gab es in dieser Saison überhaupt OGS? (Abo oder OGS-Order). Nur dann
        // ist der OGS-Umschalter sinnvoll – Schulen ohne OGS bekommen ihn nicht.
        $hasOgs = Subscription::where('season_id', $season->id)->exists()
            || Order::where('season_id', $season->id)->whereNull('category_id')->exists();

        return view('schulkantine::servings.terminal', [
            'season' => $season,
            'date' => $date,
            'open' => $open,
            'hasOgs' => $hasOgs,
            'closedReason' => $open ? null : $this->closedReason($season, $date),
            'planGroups' => $open ? $this->terminalPlanDishes($season, $date) : collect(),
            'walkinGroups' => $open ? $this->terminalWalkinGroups($season, $date) : collect(),
            'ogsEaters' => $open ? $this->terminalOgsEaters($season, $date) : collect(),
            'week' => $this->terminalWeek($season, $date),
            'simChips' => $simChips,
            // Link auf die Auswertung je Person nur zeigen, wenn er auch aufginge.
            'darfAuswertung' => app(\App\Modules\Support\Modulzugriff::class)->darfRoute('module.schulkantine.reports.show', $user),
            'openDates' => $this->seasonOpenDates($season),
            'today' => Carbon::today()->toDateString(),
        ]);
    }

    /**
     * Alle Öffnungstage der Saison als [YYYY-MM-DD]-Liste – für den Touch-Kalender.
     * Bewusst in EINEM Rutsch (Schließtage einmal laden, dann PHP-Schleife), statt
     * Season::isOpenOn() je Tag aufzurufen (das würde je Tag die DB abfragen).
     *
     * @return array<int, string>
     */
    private function seasonOpenDates(Season $season): array
    {
        $weekdays = $season->opening_weekdays ?: [1, 2, 3, 4, 5];
        $closed = $season->closedDays()->pluck('date')
            ->map(fn ($d) => $d instanceof Carbon ? $d->toDateString() : Carbon::parse($d)->toDateString())
            ->flip();

        $dates = [];
        for ($d = $season->start_date->copy(); $d->lte($season->end_date); $d->addDay()) {
            if (in_array($d->dayOfWeekIso, $weekdays, true) && ! $closed->has($d->toDateString())) {
                $dates[] = $d->toDateString();
            }
        }

        return $dates;
    }

    /**
     * Live-Suche fürs Terminal: bis zu 6 Personen zu einem Namensteil, mit Gruppe
     * (als „Klasse"). Zwei Modi:
     *  - Vorbesteller (Standard): Schüler mit Essensvertrag; `alle` = alle mit
     *    Vertrag, `ogs` = zusätzlich die OGS-Kinder.
     *  - OGS (mode=ogs): genau die HEUTE essenden OGS-Kinder (damit sie sich per
     *    Suche schnell finden und direkt abhaken lassen).
     * Antwort als JSON.
     */
    public function terminalSearch(Request $request)
    {
        abort_unless(Access::canServe($request->user()), 403, 'Kein Zugriff auf das Ausgabe-Terminal.');

        $q = trim((string) $request->input('q', ''));
        if ($q === '') {
            return response()->json(['results' => []]);
        }

        // OGS-Modus: ALLE OGS-Kinder (nach Rolle) – auch heute nicht angemeldete,
        // damit ein spontan erschienenes Kind gefunden und abgehakt werden kann.
        if ($request->input('mode') === 'ogs') {
            $ogsRoleIds = CustomerGroup::where('ordering_mode', CustomerGroup::MODE_JA_NEIN)->pluck('role_id');

            $abfrage = User::where('name', 'like', '%'.$q.'%')
                ->whereHas('roles', fn ($r) => $r->whereIn('roles.role_id', $ogsRoleIds));
            $gesamt = (clone $abfrage)->count();
            $users = $abfrage->orderBy('name')->limit(6)->get();

            return response()->json([
                'total' => $gesamt,
                'results' => $users->map(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'group' => 'OGS',
                    'label' => 'OGS',
                ])->values(),
            ]);
        }

        $ogsRoleIds = CustomerGroup::where('ordering_mode', CustomerGroup::MODE_JA_NEIN)->pluck('role_id');
        $groups = CustomerGroup::all()->keyBy('role_id');

        // Reihenfolge: erst nach Personengruppe (Schüler zuerst – die haben am
        // ehesten den Chip vergessen; Eltern zuletzt, die sind fast nie hier),
        // dann alphabetisch. Sortiert in der Abfrage, damit das limit(6) die
        // richtigen drei liefert und nicht die ersten drei nach Name.
        $rang = 'CASE '.implode(' ', array_map(
            fn (string $rolle, int $i) => "WHEN EXISTS (SELECT 1 FROM user_roles WHERE user_roles.user_id = users.id AND user_roles.role_id = '{$rolle}') THEN {$i}",
            array_keys(self::PERSONEN_RANG), array_values(self::PERSONEN_RANG),
        )).' ELSE 9 END';

        // Fast alle Esser sind Kinder: Standard sind darum Schüler mit Essensvertrag
        // (ob bestellt oder nicht). `alle` nimmt Lehrer/Mitarbeiter/Eltern mit Vertrag
        // dazu, `ogs` die OGS-Kinder – sonst wird z. B. „Micha" endlos lang.
        $istOgs = fn ($r) => $r->whereIn('roles.role_id', $ogsRoleIds);
        $treffer = function (bool $alle, bool $mitOgs) use ($q, $istOgs) {
            $abfrage = User::where('name', 'like', '%'.$q.'%');
            $abfrage->where(function ($w) use ($alle, $mitOgs, $istOgs) {
                $w->where(function ($v) use ($alle, $istOgs) {
                    \Intranet\Modules\Schulkantine\Support\Essensvertrag::nurMitVertrag($v->whereDoesntHave('roles', $istOgs));
                    if (! $alle) {
                        $v->whereHas('roles', fn ($r) => $r->where('roles.role_id', 'student'));
                    }
                });
                if ($mitOgs) {
                    $w->orWhereHas('roles', $istOgs);
                }
            });

            return $abfrage;
        };
        $abfrage = $treffer($request->boolean('alle'), $request->boolean('ogs'));
        // Gesamtzahl mitgeben: sieht man mehr als die sechs Angezeigten, weiß
        // man am Terminal, dass weiter eingeschränkt werden muss. „weitere" = Treffer,
        // die erst mit „Alle"/„OGS" sichtbar würden.
        $gesamt = (clone $abfrage)->count();
        $weitere = max(0, $treffer(true, true)->count() - $gesamt);

        $users = $abfrage
            ->with(['roles', 'kantineInfo'])
            ->orderByRaw($rang)
            ->orderBy('name')
            ->limit(6)
            ->get();

        return response()->json([
            'total' => $gesamt,
            'weitere' => $weitere,
            'results' => $users->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'group' => ($g = CustomerGroup::forUser($u, $groups))?->name,
                'label' => $g?->ordering_mode === CustomerGroup::MODE_JA_NEIN ? 'OGS' : self::personLabel($u, $g),
                'rolle' => self::personenRolle($u),
                // OGS-Kinder bucht das Terminal als „abgeholt" statt über den Menü-Fluss.
                'ogs' => $g?->ordering_mode === CustomerGroup::MODE_JA_NEIN,
            ])->values(),
        ]);
    }

    /**
     * Bucht eine ganze Terminal-Transaktion atomar: die Menü-Ausgabe des Essers
     * (je Bestellung genommen/Alternative/abgelehnt – wie serveConfirm), spontane
     * Extras (Walk-in) und freie Nachschlag-Beträge. Antwort als JSON inkl. der
     * aktualisierten Tages-Zähler, damit die linke Spalte sofort nachzieht.
     */
    public function terminalCommit(Request $request)
    {
        $user = $request->user();
        abort_unless(Access::canServe($user), 403, 'Du darfst die Ausgabe nicht erfassen.');

        $data = $request->validate([
            'eater_id' => ['required', 'integer', 'exists:users,id'],
            'date' => ['required', 'date'],
            'menu' => ['array'],
            'menu.*.order_id' => ['required', 'integer'],
            'menu.*.outcome' => ['required', 'in:taken,alternative,declined'],
            'walkin' => ['array'],
            'walkin.*.dish_id' => ['required', 'integer', 'exists:kantine_dishes,id'],
            'walkin.*.qty' => ['required', 'integer', 'min:1', 'max:50'],
            'nachschlag' => ['array'],
            'nachschlag.*.amount' => ['required', 'numeric', 'min:0.01', 'max:99'],
            'nachschlag.*.qty' => ['required', 'integer', 'min:1', 'max:50'],
        ]);

        $season = Season::where('is_active', true)->firstOrFail();
        $eater = User::findOrFail($data['eater_id']);
        $date = Carbon::parse($data['date'])->startOfDay();

        $fail = fn (string $msg) => response()->json(['ok' => false, 'error' => $msg], 200);

        if (! $season->isOpenOn($date)) {
            return $fail('An diesem Tag hat die Mensa nicht geöffnet.');
        }

        $menu = $data['menu'] ?? [];
        $walkinIn = $data['walkin'] ?? [];
        $nachschlag = $data['nachschlag'] ?? [];

        // OGS (ja/nein) ist vom Spontankauf/Nachschlag ausgeschlossen – Sicherheitsnetz
        // zur UI, die die rechte Seite für OGS ohnehin sperrt.
        $isOgs = CustomerGroup::forUser($eater)?->ordering_mode === CustomerGroup::MODE_JA_NEIN;
        if ($isOgs && ($walkinIn || $nachschlag)) {
            return $fail($eater->name.' gehört zur OGS-Gruppe – dafür sind keine Extras möglich.');
        }

        // Walk-in-Gerichte prüfen (Kategorie erlaubt Spontan? Eltern-Freigabe?).
        $walkinDishes = [];
        foreach ($walkinIn as $w) {
            $dish = Dish::with('category')->find($w['dish_id']);
            if (! $dish || ! $dish->category || ! $dish->category->allows_walkin) {
                return $fail('Ein gewähltes Extra ist nicht für spontane Abholung freigegeben.');
            }
            if (! ChildCategoryPermission::canWalkin($eater->id, $dish->category_id)) {
                return $fail('Für '.$eater->name.' ist der Spontankauf der Kategorie „'.$dish->category->name.'" nicht freigegeben.');
            }
            $walkinDishes[] = ['dish' => $dish, 'qty' => (int) $w['qty']];
        }

        // Wochenbudget prüfen (Walk-in + Nachschlag zusammen).
        $extraSum = 0.0;
        foreach ($walkinDishes as $wd) {
            $extraSum += $wd['dish']->preisFuer($eater) * $wd['qty'];
        }
        foreach ($nachschlag as $n) {
            $extraSum += (float) $n['amount'] * (int) $n['qty'];
        }
        // Prüfung und Buchung laufen in EINER Transaktion unter Sperre des Essers
        // (siehe esserSperren) – parallele Terminals können das Budget so nicht
        // gemeinsam überziehen.
        $pruefung = DB::transaction(function () use ($request, $season, $eater, $date, $user, $menu, $walkinDishes, $nachschlag, $extraSum) {
            $this->esserSperren($eater);

            // Die Buchung ersetzt die Extras dieses Tages (s. u.) – die zählen hier also nicht mit.
            $pruefung = $this->budgetPruefen($eater, $date, $extraSum, $request->boolean('budget_ueberschreiten'), tagErsetzt: true);
            if (! $pruefung['ok']) {
                return $pruefung;
            }
            $this->ueberschreitungMerken($pruefung['ueber'] ?? null, $user->id);
            // 1) Menü-Ausgabe – ersetzt die bisherigen nicht-spontanen Zeilen (wie serveConfirm).
            if ($menu) {
                $orders = Order::where('season_id', $season->id)->where('user_id', $eater->id)
                    ->whereDate('date', $date->toDateString())->whereNotNull('category_id')
                    ->where('status', Order::STATUS_ORDERED)->get()->keyBy('id');

                Serving::where('season_id', $season->id)->where('user_id', $eater->id)
                    ->whereDate('date', $date->toDateString())->where('spontaneous', false)->delete();

                foreach ($menu as $item) {
                    $order = $orders->get((int) $item['order_id']);
                    if (! $order) {
                        continue;
                    }
                    $outcome = $item['outcome'];
                    Serving::create([
                        'season_id' => $season->id, 'user_id' => $eater->id, 'date' => $date->toDateString(),
                        'order_id' => $order->id, 'dish_id' => $order->dish_id, 'category_id' => $order->category_id,
                        'price_snapshot' => $order->price_snapshot, 'spontaneous' => false,
                        'declined' => $outcome === 'declined',
                        'decline_reason' => $outcome === 'declined' ? 'nicht genommen' : null,
                        'alternative' => $outcome === 'alternative',
                        'served_by' => $user->id,
                    ]);
                }
            }

            // Die bisherigen spontanen Zeilen (Walk-in + Nachschlag) IMMER ersetzen:
            // Beim erneuten Vorhalten eines Chips zeigt das Terminal den bereits
            // gebuchten Stand und schickt ihn – ggf. geändert – komplett zurück.
            // Ohne dieses Löschen würde ein zweites Bestätigen die Extras verdoppeln;
            // so ist ein Zurücknehmen (Menge 0) ebenfalls möglich.
            Serving::where('season_id', $season->id)->where('user_id', $eater->id)
                ->whereDate('date', $date->toDateString())->where('spontaneous', true)->delete();

            // 2) Walk-in (spontan) – je Menge eine Zeile.
            foreach ($walkinDishes as $wd) {
                for ($i = 0; $i < $wd['qty']; $i++) {
                    Serving::create([
                        'season_id' => $season->id, 'user_id' => $eater->id, 'date' => $date->toDateString(),
                        'order_id' => null, 'dish_id' => $wd['dish']->id, 'category_id' => $wd['dish']->category_id,
                        'price_snapshot' => $wd['dish']->preisFuer($eater), 'spontaneous' => true, 'served_by' => $user->id,
                    ]);
                }
            }

            // 3) Nachschlag – Betrag ohne Gericht, mit Label (für die Abrechnung).
            foreach ($nachschlag as $n) {
                for ($i = 0; $i < (int) $n['qty']; $i++) {
                    Serving::create([
                        'season_id' => $season->id, 'user_id' => $eater->id, 'date' => $date->toDateString(),
                        'order_id' => null, 'dish_id' => null, 'category_id' => null, 'label' => 'Extras/Sonstiges',
                        'price_snapshot' => (float) $n['amount'], 'spontaneous' => true, 'served_by' => $user->id,
                    ]);
                }
            }

            return $pruefung;
        });
        if (! $pruefung['ok']) {
            return response()->json(['ok' => false, 'error' => $pruefung['fehler'], 'budget_frage' => $pruefung['frage'] ?? false], 200);
        }

        return response()->json([
            'ok' => true,
            'name' => $eater->name,
            // Aktualisierte Tages-Zähler, damit die linke Spalte ohne Reload nachzieht.
            'plan' => $this->terminalPlanDishes($season, $date),
        ]);
    }

    /**
     * OGS-Ansicht des Terminals: alle heute essenden OGS-Kinder mit Ausgabe-Status.
     * Sortiert wird clientseitig nach Vorname (springt beim Abholen nach unten),
     * darum hier nur die Rohliste (inkl. Vorname zum Sortieren + Sonderkost-Info).
     *
     * @return Collection<int, array>
     */
    private function terminalOgsEaters(Season $season, Carbon $date): Collection
    {
        $attending = $this->attendingOgs($season, $date);
        if ($attending->isEmpty()) {
            return collect();
        }

        // Ausgabe-Zeilen (nicht-spontan) je Kind – für Status abgeholt/abgelehnt.
        $servings = Serving::where('season_id', $season->id)
            ->whereDate('date', $date->toDateString())
            ->where('spontaneous', false)
            ->whereIn('user_id', $attending->pluck('id'))
            ->get()
            ->keyBy('user_id');

        return $attending->map(fn (User $u) => [
            'user_id' => $u->id,
            'name' => $u->name,
            'first' => \Illuminate\Support\Str::of($u->name)->trim()->explode(' ')->first(),
            'served' => $servings->has($u->id),
            'declined' => (bool) optional($servings->get($u->id))->declined,
            'allergens' => $u->kantineAllergens->pluck('name')->all(),
            'diets' => $u->kantineDiets->pluck('name')->all(),
        ])->values();
    }

    /**
     * OGS-Kind im Terminal buchen. Drei Ausgänge über `outcome`:
     *  - 'served'   → abgeholt (Ausgabe-Zeile, declined=false)
     *  - 'declined' → abgelehnt (Ausgabe-Zeile, declined=true)   [Long-Press-Modal]
     *  - 'open'     → zurücknehmen (Ausgabe-Zeile weg; spontane Anwesenheit ganz raus)
     * Ohne `outcome` verhält sich der Aufruf als Tap-Umschalter (abgeholt ⇄ offen).
     * Abgeholt/abgelehnt legen bei nicht angemeldeten Kindern die Anwesenheit spontan
     * an (am Ausgabetag erlaubt). Antwort als JSON mit dem neuen Status.
     */
    public function terminalOgsToggle(Request $request)
    {
        $user = $request->user();
        abort_unless(Access::canServe($user), 403, 'Du darfst die Ausgabe nicht erfassen.');

        $data = $request->validate([
            'eater_id' => ['required', 'integer', 'exists:users,id'],
            'date' => ['required', 'date'],
            'outcome' => ['nullable', 'in:served,declined,open'],
        ]);

        $season = Season::where('is_active', true)->firstOrFail();
        $eater = User::findOrFail($data['eater_id']);
        $date = Carbon::parse($data['date'])->startOfDay();

        $fail = fn (string $msg) => response()->json(['ok' => false, 'error' => $msg], 200);

        if (! $season->isOpenOn($date)) {
            return $fail('An diesem Tag hat die Mensa nicht geöffnet.');
        }
        if (CustomerGroup::forUser($eater)?->ordering_mode !== CustomerGroup::MODE_JA_NEIN) {
            return $fail($eater->name.' gehört nicht zur OGS-Gruppe (ja/nein).');
        }

        // Ohne ausdrücklichen Ausgang: Tap-Umschalter (abgeholt ⇄ offen).
        $outcome = $data['outcome'] ?? ($this->isServed($season, $eater, $date) ? 'open' : 'served');

        // Zurücknehmen → Ausgabe-Zeile weg; spontane Anwesenheit (kein Abo) ganz raus.
        if ($outcome === 'open') {
            Serving::where('season_id', $season->id)->where('user_id', $eater->id)
                ->whereDate('date', $date->toDateString())->where('spontaneous', false)->delete();

            // Nur spontan dabei (kein aktives Abo)? Dann Anwesenheit ganz entfernen –
            // weg vom Board, keine Berechnung. Kommt es doch, erneut über die Suche.
            // Abonnierte bleiben angemeldet (ihre Anwesenheit ist das Abo).
            $removed = false;
            $subscribed = Subscription::where('season_id', $season->id)
                ->where('user_id', $eater->id)->where('active', true)->exists();
            if (! $subscribed) {
                Order::where('season_id', $season->id)->where('user_id', $eater->id)
                    ->whereDate('date', $date->toDateString())->whereNull('category_id')->delete();
                $removed = true;
            }

            return response()->json(['ok' => true, 'served' => false, 'declined' => false, 'removed' => $removed]);
        }

        // Abgeholt oder abgelehnt: Anwesenheit sicherstellen (spontan, ohne Frist –
        // es wird jetzt real ausgegeben; sonst zählt der Tag nicht in der Abrechnung,
        // die OGS-Tage über die Order/Abo bucht), dann die Ausgabe-Zeile setzen.
        $declined = $outcome === 'declined';
        DB::transaction(function () use ($season, $eater, $date, $user, $declined) {
            if (! $this->isOgsAttending($season, $eater, $date)) {
                Order::where('season_id', $season->id)->where('user_id', $eater->id)
                    ->whereDate('date', $date->toDateString())->whereNull('category_id')
                    ->where('status', Order::STATUS_CANCELLED)->delete();
                if (! $this->isOgsAttending($season, $eater, $date)) {
                    Order::create([
                        'season_id' => $season->id,
                        'user_id' => $eater->id,
                        'date' => $date->toDateString(),
                        'status' => Order::STATUS_ORDERED,
                    ]);
                }
            }

            // Bisherige (nicht-spontane) OGS-Zeile ersetzen – so ist ein Wechsel
            // abgeholt ⇄ abgelehnt jederzeit möglich.
            Serving::where('season_id', $season->id)->where('user_id', $eater->id)
                ->whereDate('date', $date->toDateString())->where('spontaneous', false)->delete();

            $order = Order::where('season_id', $season->id)->where('user_id', $eater->id)
                ->whereDate('date', $date->toDateString())->whereNull('category_id')
                ->where('status', Order::STATUS_ORDERED)->first();

            Serving::create([
                'season_id' => $season->id,
                'user_id' => $eater->id,
                'date' => $date->toDateString(),
                'order_id' => $order?->id,
                'price_snapshot' => $season->ogsPreis(),
                'spontaneous' => false,
                'declined' => $declined,
                'decline_reason' => $declined ? 'nicht genommen' : null,
                'served_by' => $user->id,
            ]);
        });

        return response()->json([
            'ok' => true,
            'served' => true,
            'declined' => $declined,
            // Board-Eintrag, damit ein spontan dazugekommenes Kind ohne Reload erscheint.
            'eater' => $this->terminalOgsEaterPayload($season, $eater, $date),
        ]);
    }

    /** Board-Eintrag eines einzelnen OGS-Kindes (Form wie in terminalOgsEaters()). */
    private function terminalOgsEaterPayload(Season $season, User $eater, Carbon $date): array
    {
        $eater->loadMissing(['kantineAllergens', 'kantineDiets']);

        $sv = Serving::where('season_id', $season->id)->where('user_id', $eater->id)
            ->whereDate('date', $date->toDateString())->where('spontaneous', false)->first();

        return [
            'user_id' => $eater->id,
            'name' => $eater->name,
            'first' => \Illuminate\Support\Str::of($eater->name)->trim()->explode(' ')->first(),
            'served' => $sv !== null,
            'declined' => $sv ? (bool) $sv->declined : false,
            'allergens' => $eater->kantineAllergens->pluck('name')->all(),
            'diets' => $eater->kantineDiets->pluck('name')->all(),
        ];
    }

    /**
     * Übersicht-Ansicht des Terminals: Wochen-Matrix aller Esser für die KW des
     * gewählten Tages. Je Person und Öffnungstag: Vorbesteller → die vorbestellten
     * Gerichte, OGS → ob teilgenommen wird (ja/nein). Antwort als JSON (on demand
     * geladen, wenn die Übersicht geöffnet wird).
     */
    public function terminalOverview(Request $request)
    {
        abort_unless(Access::canServe($request->user()), 403, 'Kein Zugriff auf das Ausgabe-Terminal.');

        $season = Season::where('is_active', true)->first();
        if (! $season) {
            return response()->json(['days' => [], 'people' => []]);
        }
        try {
            $date = Carbon::parse((string) $request->input('date'))->startOfDay();
        } catch (\Exception $e) {
            $date = Carbon::today();
        }

        // Öffnungstage der ISO-Woche des gewählten Tages.
        $monday = $date->copy()->startOfWeek(Carbon::MONDAY);
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $d = $monday->copy()->addDays($i);
            if ($season->isOpenOn($d)) {
                $days[] = ['date' => $d->toDateString(), 'weekday' => ucfirst($d->isoFormat('dd')), 'dayLabel' => $d->format('d.m.')];
            }
        }
        $dayStrs = array_column($days, 'date');
        if (empty($dayStrs)) {
            return response()->json(['days' => [], 'people' => []]);
        }
        $weekStart = $monday->toDateString();
        $weekEnd = $monday->copy()->addDays(6)->toDateString();

        // Menü-Bestellungen der Woche je Nutzer (Kategorie ≠ OGS, aktiv).
        $ordersByUser = Order::where('season_id', $season->id)
            ->whereBetween('date', [$weekStart, $weekEnd])
            ->whereNotNull('category_id')->where('status', Order::STATUS_ORDERED)
            ->with('dish', 'menuDay')->get()->groupBy('user_id');

        // Wer in der Woche etwas abgeholt hat (auch spontan) – für den Filter „nur Besteller".
        $abgeholt = Serving::where('season_id', $season->id)
            ->whereBetween('date', [$weekStart, $weekEnd])
            ->where('declined', false)
            ->distinct()->pluck('user_id')->flip();

        // OGS-Teilnahme je Öffnungstag (Set der user_ids).
        $attendingByDay = [];
        foreach ($days as $d) {
            $attendingByDay[$d['date']] = $this->attendingOgsIds($season, Carbon::parse($d['date']))->flip();
        }

        // Wer wird gelistet: alle OGS-/Schüler-Kinder (nach Rolle) PLUS alle, die in der
        // Woche etwas bestellt haben oder als OGS teilnehmen (deckt „Sonstige" ab, die
        // bestellen). So bleibt die Liste bei den echten Essern, nicht der ganzen DB.
        $childRoleIds = ['kantine_ogs', 'kantine_student'];
        $eaterIds = User::whereHas('roles', fn ($q) => $q->whereIn('roles.role_id', $childRoleIds))->pluck('id')
            ->merge($ordersByUser->keys())
            ->merge(collect($attendingByDay)->flatMap(fn ($set) => $set->keys()))
            ->unique();

        $groups = CustomerGroup::all()->keyBy('role_id');
        $eaters = User::whereIn('id', $eaterIds)->with('roles')->orderBy('name')->get();

        $people = $eaters->map(function (User $u) use ($groups, $dayStrs, $ordersByUser, $attendingByDay, $abgeholt) {
            $group = CustomerGroup::forUser($u, $groups);
            $isOgs = $group?->ordering_mode === CustomerGroup::MODE_JA_NEIN;
            $userOrders = $ordersByUser->get($u->id, collect());

            $daysData = [];
            foreach ($dayStrs as $ds) {
                if ($isOgs) {
                    $daysData[$ds] = ['ogs' => $attendingByDay[$ds]->has($u->id)];
                } else {
                    // Ein bestelltes Menü = EIN Posten (nur der Menüname, die Gerichte
                    // stehen als Tooltip dabei); gleiche Einzelgerichte als „2× …".
                    $tag = $userOrders->filter(fn (Order $o) => $o->date->toDateString() === $ds);
                    $menues = $tag->whereNotNull('menu_day_id')->groupBy('menu_day_id')
                        ->map(fn ($g) => ['name' => $g->first()->menuDay?->name ?? 'Menü', 'menu' => true, 'anzahl' => 1,
                            'inhalt' => $g->map(fn (Order $o) => $o->dish?->name)->filter()->implode(', ')]);
                    $einzeln = $tag->whereNull('menu_day_id')->groupBy('dish_id')
                        ->map(fn ($g) => ['name' => $g->first()->dish?->name ?? '—', 'menu' => false, 'anzahl' => $g->count(), 'inhalt' => '']);
                    $daysData[$ds] = ['posten' => $menues->values()->concat($einzeln->values())->all()];
                }
            }

            return [
                'user_id' => $u->id,
                'name' => $u->name,
                'first' => \Illuminate\Support\Str::of($u->name)->trim()->explode(' ')->first(),
                'mode' => $isOgs ? 'ogs' : 'menue',
                'group' => $group?->name,
                'klasse' => self::klasse($u),
                'vertrag' => \Intranet\Modules\Schulkantine\Support\Essensvertrag::hat($u),
                // An irgendeinem Tag der Woche bestellt, als OGS angemeldet oder etwas abgeholt.
                'besteller' => $userOrders->isNotEmpty() || $abgeholt->has($u->id)
                    || collect($dayStrs)->contains(fn ($ds) => $attendingByDay[$ds]->has($u->id)),
                'days' => $daysData,
            ];
        })->values();

        return response()->json(['days' => $days, 'people' => $people]);
    }

    /**
     * Klasse eines Schülers aus seiner Klassen-Rolle (vom Linear-Gruppenabgleich
     * angelegt: role_id `linear-klasse-…-schueler`, Name „Schüler Klasse 5a").
     * Null, wenn er keine hat.
     */
    public static function klasse(User $user): ?string
    {
        $rolle = $user->roles->first(fn ($r) => str_starts_with($r->role_id, 'linear-klasse-') && str_ends_with($r->role_id, '-schueler'));

        if ($rolle === null) {
            return null;
        }

        return trim(preg_replace('/^Schüler\s+Klasse\s+/u', '', (string) $rolle->name)) ?: null;
    }

    /**
     * Linke Terminal-Spalte: zuerst die Menüs des Tages (je Menü EINE Kachel, nicht
     * seine Einzelgerichte), danach die einzeln vorbestellbaren Gerichte nach
     * Kategorie. Jede Kachel trägt die Zähler bestellt / offen / ausgegeben und die
     * Personen dahinter (für das Modal hinter den Zahlen). Gezählt wird je Esser:
     * ein Menü mit drei Gerichten ist eine Bestellung.
     *
     * @return Collection<int, array>
     */
    private function terminalPlanDishes(Season $season, Carbon $date): Collection
    {
        $dateStr = $date->toDateString();

        $personen = $this->terminalTilePeople($season, $date);
        $zaehler = function (string $tile) use ($personen): array {
            $p = $personen->get($tile, collect());

            return [
                'people' => $p->values()->all(),
                'ordered' => $p->count(),
                'served' => $p->where('status', 'served')->count(),
                'open' => $p->where('status', 'open')->count(),
                'declined' => $p->where('status', 'declined')->count(),
            ];
        };

        $gruppen = collect();

        // 1) Menüs: Inhalt, Preis und Sonderkost aus ihren Gerichten.
        $menuDays = MenuDay::where('season_id', $season->id)
            ->whereDate('date', $dateStr)
            ->with(collect($this->terminalDishRelations())->map(fn ($r) => 'slots.'.$r)->all())
            ->orderBy('id')
            ->get();
        if ($menuDays->isNotEmpty()) {
            $gruppen->push([
                'category' => 'Menüs',
                'category_id' => 'menus',
                'color' => null,
                'dishes' => $menuDays->map(function (MenuDay $md) use ($zaehler) {
                    $gerichte = $md->slots->sortBy('position')->map(fn ($s) => $s->dish)->filter()->values();
                    $vereint = fn (string $feld) => $gerichte->flatMap(fn (Dish $d) => $this->terminalDishPayload($d)[$feld])
                        ->unique(fn ($x) => is_array($x) ? $x['id'] : $x)->values()->all();

                    return [
                        'id' => 'md'.$md->id,
                        'menu' => true,
                        'name' => $md->name,
                        'price' => (float) $md->price,
                        'photo' => $gerichte->map(fn (Dish $d) => $d->photoUrl())->filter()->first(),
                        'inhalt' => $gerichte->pluck('name')->all(),
                        'allergens' => $vereint('allergens'),
                        'additives' => $vereint('additives'),
                        'unsuitable' => $vereint('unsuitable'),
                        'allergenIds' => $vereint('allergenIds'),
                        'dietIds' => $vereint('dietIds'),
                    ] + $zaehler('md'.$md->id);
                })->values(),
            ]);
        }

        // 2) Einzeln vorbestellbare Gerichte des Tagesplans.
        $einzeln = Menu::where('season_id', $season->id)
            ->whereDate('date', $dateStr)
            ->with($this->terminalDishRelations())
            ->orderBy('sort_order')->orderBy('id')
            ->get()
            ->filter(fn (Menu $m) => $m->dish && $m->dish->category && $m->dish->category->allows_preorder)
            ->map(fn (Menu $m) => $m->dish)
            ->unique('id');

        return $gruppen->concat($einzeln->groupBy(fn (Dish $d) => $d->category?->name ?? 'Ohne Kategorie')
            ->map(fn (Collection $group, string $cat) => [
                'category' => $cat,
                'category_id' => $group->first()->category_id,
                'color' => $group->first()->category?->color,
                'dishes' => $group->map(fn (Dish $d) => $this->terminalDishPayload($d)
                    + ['menu' => false, 'inhalt' => []] + $zaehler('d'.$d->id))->values(),
            ])->values());
    }

    /**
     * Personen je Terminal-Kachel ('md<id>' für Menüs, 'd<id>' für Einzelgerichte)
     * mit Ausgabe-Status open/served/declined – sortiert nach Nachname, Vorname, Klasse.
     *
     * @return Collection<string, Collection<int, array>>
     */
    private function terminalTilePeople(Season $season, Carbon $date): Collection
    {
        $dateStr = $date->toDateString();

        $orders = Order::where('season_id', $season->id)->whereDate('date', $dateStr)
            ->whereNotNull('category_id')->where('status', Order::STATUS_ORDERED)
            ->with(['user.roles', 'user.kantineInfo'])
            ->get();
        $servings = Serving::where('season_id', $season->id)->whereDate('date', $dateStr)
            ->where('spontaneous', false)->whereNotNull('order_id')
            ->get()->keyBy('order_id');
        $groups = CustomerGroup::all()->keyBy('role_id');

        return $orders
            ->filter(fn (Order $o) => $o->user !== null)
            ->groupBy(fn (Order $o) => $o->menu_day_id ? $o->user_id.'|md'.$o->menu_day_id : 'o'.$o->id)
            ->map(function (Collection $g) use ($servings, $groups) {
                $first = $g->first();
                $sv = $g->map(fn (Order $o) => $servings->get($o->id))->filter();

                return [
                    'tile' => $first->menu_day_id ? 'md'.$first->menu_day_id : 'd'.$first->dish_id,
                    'id' => $first->user_id,
                    'name' => self::sortName($first->user),
                    'klasse' => self::klasseOderRolle($first->user, CustomerGroup::forUser($first->user, $groups)),
                    'status' => $sv->isEmpty() ? 'open' : ($sv->every(fn (Serving $s) => $s->declined) ? 'declined' : 'served'),
                ];
            })
            ->sort(fn ($a, $b) => strcmp(self::sortierbar($a['name']), self::sortierbar($b['name']))
                ?: strnatcasecmp((string) $a['klasse'], (string) $b['klasse']))
            ->groupBy('tile')
            ->map(fn (Collection $p) => $p->map(fn ($e) => collect($e)->except('tile')->all())->values());
    }

    /** Walk-in-Gerichte des Tages, gruppiert (rechte Terminal-Spalte oben). */
    private function terminalWalkinGroups(Season $season, Carbon $date): Collection
    {
        // Auch Gerichte aus Menüs, deren Kategorie spontan erlaubt ist (z. B. die
        // Nachspeise des Tagesmenüs) – so wird das Gericht gebucht statt eines Betrags.
        $menueGerichte = MenuDay::where('season_id', $season->id)
            ->whereDate('date', $date->toDateString())
            ->with(collect($this->terminalDishRelations())->map(fn ($r) => 'slots.'.$r)->all())
            ->orderBy('id')
            ->get()
            ->flatMap(fn (MenuDay $md) => $md->slots->sortBy('position')->map(fn ($s) => $s->dish));

        return Menu::where('season_id', $season->id)
            ->whereDate('date', $date->toDateString())
            ->with($this->terminalDishRelations())
            ->orderBy('sort_order')->orderBy('id')
            ->get()
            ->map(fn (Menu $m) => $m->dish)
            ->concat($menueGerichte)
            ->filter(fn (?Dish $d) => $d && $d->category && $d->category->allows_walkin)
            ->unique('id')
            ->groupBy(fn (Dish $d) => $d->category?->name ?? 'Ohne Kategorie')
            ->map(fn (Collection $dishes, string $cat) => [
                'category' => $cat,
                'dishes' => $dishes->map(fn (Dish $d) => $this->terminalDishPayload($d))->values(),
            ])->values();
    }

    /** Eager-Load-Pfade für die Gericht-Details (Allergene/Zusatzstoffe/Diäten). */
    private function terminalDishRelations(): array
    {
        return [
            'dish.category',
            'dish.allergens', 'dish.additives', 'dish.unsuitableDiets',
        ];
    }

    /**
     * Einheitliche Gericht-Daten fürs Terminal (Kachel + Detail-Modal + Warnung).
     *
     * @return array<string, mixed>
     */
    private function terminalDishPayload(Dish $dish): array
    {
        return [
            'id' => $dish->id,
            'name' => $dish->name,
            'price' => (float) $dish->price,
            'photo' => $dish->photoUrl(),
            'allergens' => $dish->allergens->map(fn ($a) => ['id' => $a->id, 'code' => $a->code, 'name' => $a->name])->values()->all(),
            'additives' => $dish->additives->map(fn ($a) => ['id' => $a->id, 'code' => $a->code, 'name' => $a->name])->values()->all(),
            'unsuitable' => $dish->unsuitableDiets->map(fn ($x) => ['id' => $x->id, 'name' => $x->name])->values()->all(),
            'allergenIds' => $dish->allergens->pluck('id')->all(),
            'dietIds' => $dish->unsuitableDiets->pluck('id')->all(),
        ];
    }

    /**
     * Wochenüberblick für die Infozeile: Öffnungstage der ISO-Woche mit ihren
     * vorbestellbaren Gerichten und der jeweiligen Bestellanzahl.
     *
     * @return array{kw:int, days:array<int,array>}
     */
    private function terminalWeek(Season $season, Carbon $date): array
    {
        $monday = $date->copy()->startOfWeek(Carbon::MONDAY);
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $d = $monday->copy()->addDays($i);
            if (! $season->isOpenOn($d)) {
                continue;
            }
            // Menüs je Esser einmal gezählt, Einzelgerichte je Bestellung.
            $bestellt = Order::where('season_id', $season->id)->whereDate('date', $d->toDateString())
                ->whereNotNull('category_id')->where('status', Order::STATUS_ORDERED);
            $menuZahl = (clone $bestellt)->whereNotNull('menu_day_id')
                ->selectRaw('menu_day_id, COUNT(DISTINCT user_id) as c')->groupBy('menu_day_id')->pluck('c', 'menu_day_id');
            $einzelZahl = (clone $bestellt)->whereNull('menu_day_id')
                ->selectRaw('dish_id, COUNT(*) as c')->groupBy('dish_id')->pluck('c', 'dish_id');

            $menues = MenuDay::where('season_id', $season->id)->whereDate('date', $d->toDateString())
                ->orderBy('id')->get()
                ->map(fn (MenuDay $md) => ['name' => $md->name, 'ordered' => (int) ($menuZahl[$md->id] ?? 0)]);
            $dishes = $menues->concat(Menu::where('season_id', $season->id)->whereDate('date', $d->toDateString())
                ->with('dish.category')->orderBy('sort_order')->orderBy('id')->get()
                ->filter(fn (Menu $m) => $m->dish && $m->dish->category && $m->dish->category->allows_preorder)
                ->unique(fn (Menu $m) => $m->dish_id)
                ->map(fn (Menu $m) => ['name' => $m->dish->name, 'ordered' => (int) ($einzelZahl[$m->dish_id] ?? 0)]))
                ->values()->all();

            $days[] = [
                'date' => $d->toDateString(),
                'weekday' => ucfirst($d->isoFormat('dd')),
                'dayLabel' => $d->format('d.m.'),
                'isWorking' => $d->toDateString() === $date->toDateString(),
                'dishes' => $dishes,
                // Für die OGS-Ansicht: Anzahl heute essender OGS-Kinder (statt Menüs).
                'ogsCount' => $this->attendingOgsIds($season, $d)->count(),
            ];
        }

        return ['kw' => $date->isoWeek(), 'days' => $days];
    }
}
