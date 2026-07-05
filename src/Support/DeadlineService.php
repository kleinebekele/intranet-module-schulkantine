<?php

namespace Intranet\Modules\Schulkantine\Support;

use Illuminate\Support\Carbon;
use Intranet\Modules\Schulkantine\Models\Season;
use Intranet\Modules\Schulkantine\Models\Setting;

/**
 * Berechnet die Bestell- und Abbestell-Fristen – GEGEN DEN ÖFFNUNGSKALENDER,
 * nicht per simpler Wochentags-Rechnung.
 *
 *  - Bestellschluss (neu bestellen / ändern): am *vorigen Öffnungstag* um die
 *    eingestellte Uhrzeit (Standard 14:00). Beispiel: für Montag zählt
 *    Donnerstag 14:00, wenn Fr/Sa/So geschlossen sind.
 *  - Abbestellen (stornieren): noch am *selben Tag* bis zur eingestellten
 *    Uhrzeit (Standard 09:00).
 *
 * Die Uhrzeiten kommen aus den globalen Einstellungen (Setting).
 */
class DeadlineService
{
    public function __construct(private ?Setting $settings = null)
    {
        $this->settings ??= Setting::current();
    }

    /**
     * Frist zum Bestellen/Ändern eines Essens für $servingDate: der vorige
     * Öffnungstag zur Bestellschluss-Uhrzeit.
     *
     * Sonderfall: Gibt es keinen vorigen Öffnungstag innerhalb der Saison
     * (typisch der ALLERERSTE Öffnungstag nach den Sommerferien), fällt die
     * Frist auf den Kalendertag davor zurück – sonst wäre der erste Kantinentag
     * nie bestellbar.
     */
    public function orderDeadline(Season $season, Carbon $servingDate): Carbon
    {
        $previous = $this->previousOpenDay($season, $servingDate) ?? $servingDate->copy()->subDay();

        return $this->applyTime($previous, $this->settings->order_deadline_time);
    }

    /** Frist zum Abbestellen für $servingDate: derselbe Tag zur Abbestell-Uhrzeit. */
    public function cancelDeadline(Season $season, Carbon $servingDate): Carbon
    {
        return $this->applyTime($servingDate->copy(), $this->settings->cancel_deadline_time);
    }

    /** Darf $servingDate zum Zeitpunkt $now (Standard: jetzt) noch bestellt werden? */
    public function canOrder(Season $season, Carbon $servingDate, ?Carbon $now = null): bool
    {
        return ($now ?? Carbon::now())->lte($this->orderDeadline($season, $servingDate));
    }

    /** Darf $servingDate zum Zeitpunkt $now (Standard: jetzt) noch abbestellt werden? */
    public function canCancel(Season $season, Carbon $servingDate, ?Carbon $now = null): bool
    {
        return ($now ?? Carbon::now())->lte($this->cancelDeadline($season, $servingDate));
    }

    /**
     * Der letzte Öffnungstag VOR $date innerhalb der Saison – oder null.
     * Läuft rückwärts und überspringt geschlossene Tage (Kalender ist tragend).
     */
    public function previousOpenDay(Season $season, Carbon $date): ?Carbon
    {
        $cursor = $date->copy()->subDay();

        while ($cursor->gte($season->start_date)) {
            if ($season->isOpenOn($cursor)) {
                return $cursor;
            }
            $cursor->subDay();
        }

        return null;
    }

    /** Setzt die Uhrzeit "HH:MM" auf einen Tag (fällt auf 00:00 zurück, wenn ungültig). */
    private function applyTime(Carbon $day, ?string $time): Carbon
    {
        [$h, $m] = array_pad(explode(':', (string) $time, 2), 2, '0');

        return $day->copy()->setTime((int) $h, (int) $m, 0);
    }
}
