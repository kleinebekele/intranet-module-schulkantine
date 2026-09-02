<?php

namespace Intranet\Modules\Schulkantine\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Intranet\Modules\Schulkantine\Models\Category;
use Intranet\Modules\Schulkantine\Models\Dish;
use Intranet\Modules\Schulkantine\Models\Menu;
use Intranet\Modules\Schulkantine\Models\MenuDay;
use Intranet\Modules\Schulkantine\Models\Order;
use Intranet\Modules\Schulkantine\Models\Season;
use Intranet\Modules\Schulkantine\Models\WeekRelease;
use Intranet\Modules\Schulkantine\Support\MenuRolloutService;
use Intranet\Modules\Schulkantine\Support\ReleaseService;

/**
 * Speiseplan-Verwaltung als Wochen-Raster. Es gibt EIN Tagesangebot je
 * Öffnungstag – dasselbe für alle Gruppen. OGS isst ohne eigenen Eintrag mit.
 */
class MenuController
{
    public function index(Request $request)
    {
        $this->authorizeAdmin($request);

        $season = Season::where('is_active', true)->first();

        if (! $season) {
            return view('schulkantine::menus.index', ['season' => null]);
        }

        // Standard-Woche: heute, in den Saison-Zeitraum geklemmt, dann zum
        // nächsten echten Öffnungstag vorgerückt (sonst leeres Raster in Ferien).
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
        $weekStart = $base->copy()->startOfWeek(Carbon::MONDAY);
        $weekEnd = $weekStart->copy()->addDays(6);

        // Alle Kantinen-Wochentage der Woche (auch geschlossene → markiert).
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
            $days[] = ['date' => $d->copy(), 'open' => $open, 'reason' => $reason];
        }

        // Tagesangebot der Woche -> $plan[dateStr] = [Menu, …]
        // withCount('orders') für den Löschschutz: ein Gericht mit ≥1 Bestellung
        // ist nicht mehr entfernbar (nur noch Hinzufügen bleibt erlaubt).
        $menus = Menu::where('season_id', $season->id)
            ->whereBetween('date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->with(['dish.category'])
            ->withCount('orders')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $plan = [];
        foreach ($menus as $m) {
            $plan[$m->date->toDateString()][] = $m;
        }

        // Bestellungen der Woche je Tag (Admin sieht im Speiseplan, wer was bestellt
        // hat, und kann einzelne Bestellungen löschen). Nur Speiseplan-Bestellungen:
        // à-la-carte-Gerichte und Menüs (category_id gesetzt). OGS (ja/nein) taucht
        // hier bewusst NICHT auf – das gehört nicht zum Speiseplan und verwirrt nur.
        $ordersByDate = Order::where('season_id', $season->id)
            ->whereBetween('date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->where('status', Order::STATUS_ORDERED)
            ->whereNotNull('category_id')
            ->with(['user:id,name', 'dish:id,name', 'category:id,name'])
            ->orderBy('date')
            ->get()
            ->sortBy(fn (Order $o) => mb_strtolower($o->user?->name ?? ''))
            ->groupBy(fn (Order $o) => $o->date->toDateString());

        // Ausgerollte Menüs (MenuDay) der Woche je Tag – mit füllbaren Slots.
        $menuDaysByDate = MenuDay::where('season_id', $season->id)
            ->whereBetween('date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->with(['slots.category:id,name,color', 'slots.dish:id,name'])
            ->orderBy('id')
            ->get()
            ->groupBy(fn (MenuDay $md) => $md->date->toDateString());

        // Aktive Gerichte je Kategorie – für die Gericht-Auswahl in den Menü-Slots
        // und den „+ hinzufügen"-Feldern je Kategorie.
        $dishesByCategory = Dish::where('is_active', true)
            ->orderBy('name')->get(['id', 'name', 'category_id'])
            ->groupBy('category_id');

        // Echte Kategorien in Reihenfolge (sort_order) – „Ohne Kategorie" bleibt weg.
        $categories = Category::where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')->get();

        // Wochen-Freigabe (hybrid): effektiver Zustand + evtl. manueller Override.
        $release = new ReleaseService;

        return view('schulkantine::menus.index', [
            'season' => $season,
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'days' => $days,
            'plan' => $plan,
            'ordersByDate' => $ordersByDate,
            'menuDaysByDate' => $menuDaysByDate,
            'dishesByCategory' => $dishesByCategory,
            'categories' => $categories,
            'dishes' => Dish::where('is_active', true)->with('category')->orderBy('name')->get(),
            'prevWeek' => $weekStart->copy()->subWeek()->toDateString(),
            'nextWeek' => $weekStart->copy()->addWeek()->toDateString(),
            'canPrev' => $weekStart->copy()->subWeek()->endOfWeek(Carbon::SUNDAY)->gte($season->start_date),
            'canNext' => $weekStart->copy()->addWeek()->lte($season->end_date),
            'weekReleased' => $release->isWeekReleased($season, $weekStart),
            'weekOverride' => $release->override($season, $weekStart),
            'weekHasOrders' => $this->weekHasOrders($season, $weekStart),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeAdmin($request);

        $data = $request->validate([
            'date' => ['required', 'date'],
            'dish_id' => ['required', 'exists:kantine_dishes,id'],
        ]);

        $season = Season::where('is_active', true)->firstOrFail();
        $date = Carbon::parse($data['date']);

        if (! $season->isOpenOn($date)) {
            return back()->withErrors(['date' => 'Die Kantine hat an diesem Tag nicht geöffnet.']);
        }
        if ($this->weekLocked($season, $date)) {
            return $this->redirectToWeek($date)->withErrors(['menu' => $this->lockedMessage()]);
        }

        $this->addDishToPlan($season, $date, (int) $data['dish_id']);

        return $this->redirectToWeek($date)->with('status', 'Speiseplan aktualisiert.');
    }

    /**
     * „Push" nur für die angezeigte Woche: rollt die aktiven Menü-Vorlagen nach
     * aktuellem Stand auf diese Woche aus – auch wenn sie bereits freigegeben ist.
     * Bereits bestellte/angelegte Menüs bleiben erhalten (auffrischen, nichts löschen).
     */
    public function pushWeek(Request $request)
    {
        $this->authorizeAdmin($request);

        $data = $request->validate(['week' => ['required', 'date']]);
        $season = Season::where('is_active', true)->firstOrFail();
        $week = Carbon::parse($data['week']);

        $result = (new MenuRolloutService)->pushWeek($season, $week);

        $message = $result['menus'] === 0
            ? 'Nichts auszurollen – keine aktiven Menüs für diese Woche.'
            : $result['menus'].' Menüs auf '.$result['days'].' Öffnungstage dieser Woche ausgerollt (nach aktuellen Einstellungen).';

        return $this->redirectToWeek($week)->with('status', $message);
    }

    /** Eine freigegebene (festgeschriebene) Woche ist im Speiseplan schreibgeschützt. */
    private function weekLocked(Season $season, Carbon $date): bool
    {
        return (new ReleaseService)->isWeekReleased($season, $date);
    }

    private function lockedMessage(): string
    {
        return 'Diese Woche ist festgeschrieben (freigegeben) und kann nicht mehr bearbeitet werden – zum Bearbeiten oben „Zur Bearbeitung freigeben".';
    }

    /**
     * Legt ein Gericht auf den Tagesplan – idempotent.
     *
     * ⚠️ Der Abgleich MUSS über whereDate laufen: Der `date`-Cast speichert intern
     * '<Tag> 00:00:00', ein exakter =-Vergleich gegen 'Y-m-d' trifft das nicht.
     * Ein firstOrCreate(['date' => 'Y-m-d']) findet die vorhandene Zeile deshalb
     * NICHT, versucht einzufügen und läuft in den Unique-Index (season, date, dish)
     * → 500 statt „steht schon drauf". (Dieselbe Falle wie beim Ferien-Import.)
     *
     * @return bool true = neu angelegt, false = stand schon drauf
     */
    private function addDishToPlan(Season $season, Carbon $date, int $dishId): bool
    {
        $exists = Menu::where('season_id', $season->id)
            ->whereDate('date', $date->toDateString())
            ->where('dish_id', $dishId)
            ->exists();

        if ($exists) {
            return false;
        }

        $sort = (int) Menu::where('season_id', $season->id)
            ->whereDate('date', $date->toDateString())
            ->max('sort_order') + 1;

        Menu::create([
            'season_id' => $season->id,
            'date' => $date->toDateString(),
            'dish_id' => $dishId,
            'sort_order' => $sort,
        ]);

        return true;
    }

    public function destroy(Request $request, Menu $menu)
    {
        $this->authorizeAdmin($request);

        $season = Season::where('is_active', true)->firstOrFail();
        if ($this->weekLocked($season, $menu->date)) {
            return $this->redirectToWeek($menu->date)->withErrors(['menu' => $this->lockedMessage()]);
        }

        // Löschschutz: sobald auf dieses Angebot bestellt wurde, ist es nicht mehr
        // entfernbar (Hinzufügen bleibt immer erlaubt). Schützt bestehende
        // Bestellungen/Abrechnungen vor dem Verschwinden.
        if ($menu->orders()->exists()) {
            return $this->redirectToWeek($menu->date)
                ->withErrors(['menu' => 'Dieses Gericht kann nicht entfernt werden – es liegen bereits Bestellungen dafür vor.']);
        }

        $date = $menu->date->copy();
        $menu->delete();

        return $this->redirectToWeek($date)->with('status', 'Gericht aus dem Speiseplan entfernt.');
    }

    /**
     * Die Gericht-Auswahl der Slots eines ausgerollten Menü-Tags speichern
     * (Speiseplan). Jeder Slot bekommt ein Gericht seiner Kategorie (oder leer).
     */
    public function fillMenuDay(Request $request, MenuDay $menuDay)
    {
        $this->authorizeAdmin($request);

        $season = Season::where('is_active', true)->firstOrFail();
        if ($this->weekLocked($season, $menuDay->date)) {
            return $this->redirectToWeek($menuDay->date)->withErrors(['menu' => $this->lockedMessage()]);
        }

        $data = $request->validate([
            'slots' => ['array'],
            'slots.*' => ['nullable', 'integer', 'exists:kantine_dishes,id'],
        ]);
        $picked = $data['slots'] ?? [];

        // Gültige Gericht-IDs je Kategorie vorbereiten (ein Gericht muss zur
        // Kategorie seines Slots passen – sonst wird die Auswahl verworfen).
        $slots = $menuDay->slots()->get();
        $dishCategory = Dish::whereIn('id', array_filter($picked))->pluck('category_id', 'id');

        foreach ($slots as $slot) {
            $dishId = $picked[$slot->id] ?? null;
            $valid = $dishId && (int) ($dishCategory[$dishId] ?? 0) === (int) $slot->category_id;
            $slot->update(['dish_id' => $valid ? (int) $dishId : null]);
        }

        return $this->redirectToWeek($menuDay->date)
            ->with('status', 'Menü „'.$menuDay->name.'" ('.$menuDay->date->format('d.m.Y').') gespeichert.');
    }

    /**
     * Eine einzelne Bestellung löschen (Admin, aus dem Speiseplan). Umgeht bewusst
     * die Eltern-Fristen – das ist die Superadmin-Korrektur. Ausgabe-Zeilen bleiben
     * bestehen (order_id wird per Fremdschlüssel auf NULL gesetzt), damit die
     * Historie nicht reißt.
     */
    public function destroyOrder(Request $request, Order $order)
    {
        $this->authorizeAdmin($request);

        $date = $order->date->copy();
        $name = $order->user?->name;
        $order->delete();

        return $this->redirectToWeek($date)
            ->with('status', 'Bestellung'.($name ? ' von '.$name : '').' gelöscht.');
    }

    /**
     * Manuelle Wochen-Freigabe (hybrid): früher freigeben, zurückhalten oder
     * zur Automatik zurückkehren. Granularität = ganze Woche.
     */
    public function releaseWeek(Request $request)
    {
        $this->authorizeAdmin($request);

        $data = $request->validate([
            'week' => ['required', 'date'],
            'action' => ['required', 'in:release,hold,auto'],
        ]);

        $season = Season::where('is_active', true)->firstOrFail();
        $week = Carbon::parse($data['week']);
        $release = new ReleaseService;

        // Sperren nur erlauben, solange es für die Woche noch keine Bestellungen
        // gibt. „Sperren" = zurückhalten, oder zurück auf Automatik, wenn diese
        // die Woche sperren würde. (Analog zum Löschschutz beim Speiseplan.)
        $wouldLock = $data['action'] === 'hold'
            || ($data['action'] === 'auto' && ! $release->isAutoReleased($release->weekStart($week), Carbon::now()));

        if ($wouldLock && $this->weekHasOrders($season, $week)) {
            return $this->redirectToWeek($week)
                ->withErrors(['release' => 'Diese Woche kann nicht mehr gesperrt werden – es liegen bereits Bestellungen vor.']);
        }

        $message = match ($data['action']) {
            'release' => tap('Woche wurde freigegeben.', fn () => $release->setOverride($season, $week, WeekRelease::STATE_RELEASED)),
            'hold' => tap('Woche wurde zurückgehalten (gesperrt).', fn () => $release->setOverride($season, $week, WeekRelease::STATE_HELD)),
            'auto' => tap('Woche folgt wieder der automatischen Freigabe.', fn () => $release->clearOverride($season, $week)),
        };

        return $this->redirectToWeek($week)->with('status', $message);
    }

    // ---------------------------------------------------------------- Helfer

    private function redirectToWeek(Carbon $date)
    {
        return redirect()->route('module.schulkantine.menus.index', [
            'week' => $date->copy()->startOfWeek(Carbon::MONDAY)->toDateString(),
        ]);
    }

    /**
     * Gibt es für die Woche von $anyDayInWeek eine AKTIVE Speiseplan-Bestellung?
     * Nur aktive (status = bestellt) zählen, und nur solche, die den Speiseplan
     * betreffen: à-la-carte-Gerichte und Menüs (category_id gesetzt). OGS (ja/nein,
     * category_id NULL) und stornierte Zeilen zählen NICHT – sie haben mit dem
     * Speiseplan nichts zu tun und dürfen das Zurückhalten/Bearbeiten nicht sperren.
     */
    private function weekHasOrders(Season $season, Carbon $anyDayInWeek): bool
    {
        $weekStart = $anyDayInWeek->copy()->startOfWeek(Carbon::MONDAY);
        $weekEnd = $weekStart->copy()->addDays(6);

        return Order::where('season_id', $season->id)
            ->where('status', Order::STATUS_ORDERED)
            ->whereNotNull('category_id')
            ->whereBetween('date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->exists();
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Nur Administratoren dürfen die Kantine verwalten.');
    }
}
