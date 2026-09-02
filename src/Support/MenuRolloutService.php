<?php

namespace Intranet\Modules\Schulkantine\Support;

use Illuminate\Support\Carbon;
use Intranet\Modules\Schulkantine\Models\MenuDay;
use Intranet\Modules\Schulkantine\Models\MenuTemplate;
use Intranet\Modules\Schulkantine\Models\Season;

/**
 * Rollt die Menü-Vorlagen einer Saison auf die offenen Wochen aus („Push").
 *
 * Offen = ab der aktuellen Woche bis Saison-Ende und NICHT freigegeben
 * (ReleaseService). Freigegebene Wochen bleiben unangetastet – deshalb wirkt sich
 * ein reines Bearbeiten einer Vorlage erst mit dem nächsten Push aus, und nur auf
 * die noch nicht freigegebenen Wochen.
 *
 * Beim Ausrollen wird je passendem Öffnungstag und aktiver Vorlage ein Menü-Tag
 * (MenuDay) angelegt/aufgefrischt. Bereits gewählte Gerichte in den Slots bleiben
 * erhalten, solange Kategorie und Position gleich bleiben.
 */
class MenuRolloutService
{
    public function __construct(private ?ReleaseService $release = null)
    {
        $this->release ??= new ReleaseService;
    }

    /**
     * Rollt alle aktiven Vorlagen der Saison auf die offenen Wochen aus.
     *
     * @return array{weeks:int, days:int, menus:int}
     */
    public function pushSeason(Season $season, ?Carbon $today = null): array
    {
        $today = ($today ?? Carbon::today())->startOfDay();

        $templates = $season->menuTemplates()
            ->where('is_active', true)
            ->with('slots')
            ->get();

        if ($templates->isEmpty()) {
            return ['weeks' => 0, 'days' => 0, 'menus' => 0];
        }

        $weekStart = $this->release->weekStart($today);
        $seasonEnd = $season->end_date->copy();

        $weeks = 0;
        $days = 0;
        $menus = 0;

        for ($monday = $weekStart->copy(); $monday->lte($seasonEnd); $monday->addWeek()) {
            // Nur nicht freigegebene Wochen.
            if ($this->release->isWeekReleased($season, $monday, $today)) {
                continue;
            }
            $weeks++;

            for ($day = $monday->copy(); $day->lt($monday->copy()->addWeek()); $day->addDay()) {
                // Vergangene Tage der laufenden Woche überspringen.
                if ($day->lt($today) || $day->gt($seasonEnd)) {
                    continue;
                }
                if (! $season->isOpenOn($day)) {
                    continue;
                }

                $touched = false;
                foreach ($templates as $template) {
                    if (! $template->availableOn($day)) {
                        continue;
                    }
                    $this->materialize($season, $template, $day);
                    $menus++;
                    $touched = true;
                }
                if ($touched) {
                    $days++;
                }
            }
        }

        return ['weeks' => $weeks, 'days' => $days, 'menus' => $menus];
    }

    /**
     * Rollt die aktiven Vorlagen auf EINE Woche aus – unabhängig vom Freigabe-Status
     * (bewusst: ein per-Woche-Push aktualisiert auch eine bereits freigegebene Woche).
     * Frischt vorhandene Menü-Tage auf und legt fehlende an; entfernt nichts (bereits
     * bestellte Menüs bleiben erhalten).
     *
     * @return array{days:int, menus:int}
     */
    public function pushWeek(Season $season, Carbon $anyDayInWeek, ?Carbon $today = null): array
    {
        $today = ($today ?? Carbon::today())->startOfDay();

        $templates = $season->menuTemplates()
            ->where('is_active', true)
            ->with('slots')
            ->get();

        if ($templates->isEmpty()) {
            return ['days' => 0, 'menus' => 0];
        }

        $monday = $this->release->weekStart($anyDayInWeek);
        $weekEnd = $monday->copy()->addWeek();

        $days = 0;
        $menus = 0;
        for ($day = $monday->copy(); $day->lt($weekEnd); $day->addDay()) {
            // Vergangene Tage und Tage außerhalb der Saison überspringen.
            if ($day->lt($today) || $day->lt($season->start_date) || $day->gt($season->end_date)) {
                continue;
            }
            if (! $season->isOpenOn($day)) {
                continue;
            }

            $touched = false;
            foreach ($templates as $template) {
                if (! $template->availableOn($day)) {
                    continue;
                }
                $this->materialize($season, $template, $day);
                $menus++;
                $touched = true;
            }
            if ($touched) {
                $days++;
            }
        }

        return ['days' => $days, 'menus' => $menus];
    }

    /**
     * Legt einen Menü-Tag an bzw. frischt ihn auf (Snapshot Name/Preis) und
     * synchronisiert seine Slots mit der Vorlage – gewählte Gerichte bleiben,
     * solange Kategorie und Position passen.
     */
    private function materialize(Season $season, MenuTemplate $template, Carbon $day): void
    {
        $menuDay = MenuDay::firstOrNew([
            'season_id' => $season->id,
            'date' => $day->toDateString(),
            'menu_template_id' => $template->id,
        ]);
        $menuDay->name = $template->name;
        $menuDay->price = $template->price;
        $menuDay->save();

        // Gewünschte Slot-Plätze aus der Vorlage: je Kategorie-Slot `quantity` Plätze.
        $desired = [];
        $pos = 0;
        foreach ($template->slots as $tslot) {
            for ($i = 0; $i < max(1, (int) $tslot->quantity); $i++) {
                $desired[$pos] = (int) $tslot->category_id;
                $pos++;
            }
        }

        $existing = $menuDay->slots()->get()->keyBy('position');

        foreach ($desired as $position => $categoryId) {
            $slot = $existing->get($position);
            if ($slot === null) {
                $menuDay->slots()->create(['category_id' => $categoryId, 'position' => $position, 'dish_id' => null]);
            } elseif ((int) $slot->category_id !== $categoryId) {
                // Kategorie an dieser Position geändert → Gericht passt nicht mehr.
                $slot->update(['category_id' => $categoryId, 'dish_id' => null]);
            }
        }

        // Überzählige Slots (Vorlage wurde kleiner) entfernen.
        $menuDay->slots()->where('position', '>=', count($desired))->delete();
    }
}
