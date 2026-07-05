<?php

namespace Intranet\Modules\Schulkantine\Support;

use Illuminate\Support\Carbon;
use Intranet\Modules\Schulkantine\Models\Season;
use Intranet\Modules\Schulkantine\Models\Setting;
use Intranet\Modules\Schulkantine\Models\WeekRelease;

/**
 * Wochen-Freigabe (HYBRID). Entscheidet, ob für eine Woche überhaupt bestellt
 * werden darf. Granularität = ganze Woche (Montag als Schlüssel).
 *
 *  1. Manueller Override (kantine_week_releases) hat Vorrang:
 *     released → freigegeben, held → gesperrt.
 *  2. Sonst Automatik: freigegeben, sobald der Montag der Woche innerhalb des
 *     Vorlaufs (settings.release_lead_weeks) ab der aktuellen Woche liegt.
 *
 * Die eigentliche Bestellbarkeit eines einzelnen Tages hängt zusätzlich an der
 * Bestell-Frist (DeadlineService) – die Freigabe ist die grobe, der Frist die
 * feine Schranke.
 */
class ReleaseService
{
    public function __construct(private ?Setting $settings = null)
    {
        $this->settings ??= Setting::current();
    }

    /** Normalisiert ein Datum auf den Montag seiner Woche. */
    public function weekStart(Carbon $date): Carbon
    {
        return $date->copy()->startOfWeek(Carbon::MONDAY);
    }

    /**
     * Ist die Woche von $anyDayInWeek zum Zeitpunkt $now freigegeben?
     * $now dient nur der Automatik (Standard: jetzt).
     */
    public function isWeekReleased(Season $season, Carbon $anyDayInWeek, ?Carbon $now = null): bool
    {
        $weekStart = $this->weekStart($anyDayInWeek);

        $override = $this->override($season, $weekStart);
        if ($override !== null) {
            return $override === WeekRelease::STATE_RELEASED;
        }

        return $this->isAutoReleased($weekStart, $now ?? Carbon::now());
    }

    /** Der manuelle Override-Zustand der Woche (released|held) oder null. */
    public function override(Season $season, Carbon $anyDayInWeek): ?string
    {
        return WeekRelease::where('season_id', $season->id)
            ->whereDate('week_start', $this->weekStart($anyDayInWeek)->toDateString())
            ->value('state');
    }

    /**
     * Freigabe rein nach Automatik (ohne Override): Der Montag der Woche liegt
     * nicht in der Vergangenheit (vor der aktuellen Woche) und höchstens
     * release_lead_weeks Wochen in der Zukunft.
     */
    public function isAutoReleased(Carbon $weekStart, Carbon $now): bool
    {
        $currentWeek = $this->weekStart($now);
        $latestOpen = $currentWeek->copy()->addWeeks($this->settings->release_lead_weeks);

        return $weekStart->gte($currentWeek) && $weekStart->lte($latestOpen);
    }

    /** Setzt/ändert den manuellen Override für eine Woche. */
    public function setOverride(Season $season, Carbon $anyDayInWeek, string $state): void
    {
        $weekStart = $this->weekStart($anyDayInWeek)->toDateString();

        // Bewusst per whereDate suchen: week_start ist ein date-Cast und wird als
        // "Y-m-d 00:00:00" gespeichert – ein exakter String-Vergleich (wie in
        // updateOrCreate) würde die Zeile nicht finden und beim zweiten Setzen
        // (z. B. erst freigeben, dann zurückhalten) am UNIQUE-Index scheitern.
        $existing = WeekRelease::where('season_id', $season->id)
            ->whereDate('week_start', $weekStart)
            ->first();

        if ($existing) {
            $existing->update(['state' => $state]);

            return;
        }

        WeekRelease::create([
            'season_id' => $season->id,
            'week_start' => $weekStart,
            'state' => $state,
        ]);
    }

    /** Entfernt den manuellen Override – die Woche folgt wieder der Automatik. */
    public function clearOverride(Season $season, Carbon $anyDayInWeek): void
    {
        WeekRelease::where('season_id', $season->id)
            ->whereDate('week_start', $this->weekStart($anyDayInWeek)->toDateString())
            ->delete();
    }
}
