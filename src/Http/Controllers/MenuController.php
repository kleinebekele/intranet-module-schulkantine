<?php

namespace Intranet\Modules\Schulkantine\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Intranet\Modules\Schulkantine\Models\Dish;
use Intranet\Modules\Schulkantine\Models\Menu;
use Intranet\Modules\Schulkantine\Models\Season;

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
        $menus = Menu::where('season_id', $season->id)
            ->whereBetween('date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->with('dish.category')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $plan = [];
        foreach ($menus as $m) {
            $plan[$m->date->toDateString()][] = $m;
        }

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

        $sort = (int) Menu::where('season_id', $season->id)
            ->whereDate('date', $date->toDateString())
            ->max('sort_order') + 1;

        Menu::firstOrCreate([
            'season_id' => $season->id,
            'date' => $date->toDateString(),
            'dish_id' => $data['dish_id'],
        ], ['sort_order' => $sort]);

        return $this->redirectToWeek($date)->with('status', 'Speiseplan aktualisiert.');
    }

    public function destroy(Request $request, Menu $menu)
    {
        $this->authorizeAdmin($request);

        $date = $menu->date->copy();
        $menu->delete();

        return $this->redirectToWeek($date)->with('status', 'Gericht aus dem Speiseplan entfernt.');
    }

    // ---------------------------------------------------------------- Helfer

    private function redirectToWeek(Carbon $date)
    {
        return redirect()->route('module.schulkantine.menus.index', [
            'week' => $date->copy()->startOfWeek(Carbon::MONDAY)->toDateString(),
        ]);
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Nur Administratoren dürfen die Kantine verwalten.');
    }
}
