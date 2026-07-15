<?php

namespace Intranet\Modules\Schulkantine\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Intranet\Modules\Schulkantine\Models\Category;
use Intranet\Modules\Schulkantine\Models\Dish;
use Intranet\Modules\Schulkantine\Models\Menu;
use Intranet\Modules\Schulkantine\Models\Order;
use Intranet\Modules\Schulkantine\Models\Season;
use Intranet\Modules\Schulkantine\Models\WeekRelease;
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
            // components: für isBundle() im Tages-Sparmenü-Formular (sonst N+1).
            ->with(['dish.category', 'dish.components'])
            ->withCount('orders')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $plan = [];
        foreach ($menus as $m) {
            $plan[$m->date->toDateString()][] = $m;
        }

        // Wochen-Freigabe (hybrid): effektiver Zustand + evtl. manueller Override.
        $release = new ReleaseService;

        return view('schulkantine::menus.index', [
            'season' => $season,
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'days' => $days,
            'plan' => $plan,
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

        $this->addDishToPlan($season, $date, (int) $data['dish_id']);

        return $this->redirectToWeek($date)->with('status', 'Speiseplan aktualisiert.');
    }

    /**
     * Baut aus Gerichten DIESES Tages ein Sparmenü und legt es direkt auf den Plan.
     *
     * Der Katalog wächst dabei nicht unnötig: Gibt es bereits ein Sparmenü mit genau
     * denselben Bestandteilen und demselben Preis, wird es wiederverwendet. Das hält
     * nebenbei die Bewertungs-Statistik zusammen (immer dasselbe Gericht).
     */
    public function storeBundle(Request $request)
    {
        $this->authorizeAdmin($request);

        $data = $request->validate([
            'date' => ['required', 'date'],
            'parts' => ['required', 'array', 'min:2'],
            'parts.*' => ['integer', 'exists:kantine_dishes,id'],
            'price' => ['required', 'numeric', 'min:0'],
        ]);

        $season = Season::where('is_active', true)->firstOrFail();
        $date = Carbon::parse($data['date']);

        if (! $season->isOpenOn($date)) {
            return back()->withErrors(['date' => 'Die Kantine hat an diesem Tag nicht geöffnet.']);
        }

        $ids = collect($data['parts'])->map(fn ($v) => (int) $v)->unique()->values();
        if ($ids->count() < 2) {
            return $this->redirectToWeek($date)->withErrors(['bundle' => 'Ein Sparmenü braucht mindestens zwei verschiedene Gerichte.']);
        }

        // Bestandteile in Speisefolge (Hauptmenü vor Nachspeise) – bestimmt Name
        // und Reihenfolge überall dort, wo das Sparmenü auftaucht.
        $parts = Dish::with(['category', 'components'])->whereIn('id', $ids)->get()
            ->sortBy(fn (Dish $d) => [$d->category?->sort_order ?? PHP_INT_MAX, $d->name])
            ->values();

        // Keine Verschachtelung (siehe DishController::validateComponents).
        if ($parts->contains(fn (Dish $d) => $d->components->isNotEmpty())) {
            return $this->redirectToWeek($date)->withErrors(['bundle' => 'Ein Sparmenü kann kein anderes Sparmenü enthalten.']);
        }

        $price = round((float) $data['price'], 2);
        $partIds = $parts->pluck('id')->sort()->values()->all();

        $bundle = $this->findBundle($partIds, $price);
        $reused = (bool) $bundle;

        if (! $bundle) {
            $bundle = Dish::create([
                'category_id' => $this->bundleCategoryId(),
                'name' => $parts->pluck('name')->join(' + '),
                'price' => $price,
                'is_active' => true,
            ]);
            foreach ($parts->values() as $i => $part) {
                $bundle->components()->attach($part->id, ['sort_order' => $i]);
            }
        }

        $added = $this->addDishToPlan($season, $date, $bundle->id);

        $einzeln = $parts->sum(fn (Dish $d) => (float) $d->price);
        $status = 'Sparmenü „'.$bundle->name.'" ('.number_format($price, 2, ',', '.').' €, einzeln '
            .number_format($einzeln, 2, ',', '.').' €) '
            .($added ? 'steht jetzt auf dem Plan' : 'stand bereits auf dem Plan')
            .($reused ? ' – vorhandenes Sparmenü wiederverwendet.' : ' und wurde neu im Katalog angelegt.');

        return $this->redirectToWeek($date)->with('status', $status);
    }

    /**
     * Ein vorhandenes Sparmenü mit genau diesen Bestandteilen und diesem Preis.
     *
     * @param  array<int>  $partIds  aufsteigend sortiert
     */
    private function findBundle(array $partIds, float $price): ?Dish
    {
        return Dish::with('components')->whereHas('components')->get()
            ->first(fn (Dish $b) => $b->components->pluck('id')->sort()->values()->all() === $partIds
                && abs((float) $b->price - $price) < 0.005);
    }

    /**
     * Kategorie für neue Sparmenüs (siehe Category::bundleId()). Fehlt sie ganz –
     * etwa weil jemand sie gelöscht hat –, wird sie hier wieder angelegt: Ohne
     * Kategorie wäre die Bestellung nicht von einer OGS-Zeile zu unterscheiden.
     */
    private function bundleCategoryId(): int
    {
        return Category::bundleId() ?? Category::create([
            'name' => Category::BUNDLE_NAME,
            'allows_walkin' => false,
            'sort_order' => (int) Category::max('sort_order') + 1,
            'color' => '#0d9488',
            'is_active' => true,
        ])->id;
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

    /** Gibt es für die Woche von $anyDayInWeek (irgend)eine Bestellung? */
    private function weekHasOrders(Season $season, Carbon $anyDayInWeek): bool
    {
        $weekStart = $anyDayInWeek->copy()->startOfWeek(Carbon::MONDAY);
        $weekEnd = $weekStart->copy()->addDays(6);

        return Order::where('season_id', $season->id)
            ->whereBetween('date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->exists();
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Nur Administratoren dürfen die Kantine verwalten.');
    }
}
