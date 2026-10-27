<?php

namespace Intranet\Modules\Schulkantine\Support;

use Illuminate\Support\Carbon;
use Intranet\Modules\Schulkantine\Models\Season;
use Intranet\Modules\Schulkantine\Models\Setting;

/**
 * Berechnet die Bestell- und Abbestell-Fristen – GEGEN DEN ÖFFNUNGSKALENDER,
 * nicht per simpler Wochentags-Rechnung.
 *
 *  - Bestellschluss (neu bestellen / ändern): am *vorigen Werktag* (Mo–Fr, auch
 *    in den Ferien, nur Feiertage zählen nicht) um die eingestellte Uhrzeit
 *    (Standard 14:00). Beispiel: für Montag zählt Freitag 14:00 – auch wenn die
 *    Mensa freitags geschlossen hat. Bis 10/2026 galt der vorige Öffnungstag.
 *  - Abbestellen (stornieren): noch am *selben Tag* bis zur eingestellten
 *    Uhrzeit (Standard 09:00).
 *
 * Die Uhrzeiten kommen aus den globalen Einstellungen (Setting).
 *
 * $ohneFrist: Admin bei „Als Person handeln" – darf jederzeit (auch nachträglich)
 * bestellen und abbestellen; was schon an Linear abgerechnet ist, sperrt der Controller.
 */
class DeadlineService
{
    public function __construct(private ?Setting $settings = null, private bool $ohneFrist = false)
    {
        $this->settings ??= Setting::current();
    }

    /** Frist zum Bestellen/Ändern eines Essens für $servingDate: der vorige Werktag zur Bestellschluss-Uhrzeit. */
    public function orderDeadline(Season $season, Carbon $servingDate): Carbon
    {
        return $this->applyTime($this->previousWorkday($season, $servingDate), $this->settings->order_deadline_time);
    }

    /**
     * Der Werktag (Mo–Fr) vor $date, Feiertage übersprungen. Feiertage kennt nur der
     * Schließtag-Kalender der Saison (Typ „feiertag"); außerhalb zählt jeder Mo–Fr.
     */
    public function previousWorkday(Season $season, Carbon $date): Carbon
    {
        $cursor = $date->copy()->startOfDay()->subDay();
        $feiertage = $season->closedDays()
            ->where('type', 'feiertag')
            ->whereBetween('date', [$cursor->copy()->subDays(14)->toDateString(), $cursor->toDateString()])
            ->get()
            ->map(fn ($c) => $c->date->toDateString())
            ->flip();

        while ($cursor->isWeekend() || $feiertage->has($cursor->toDateString())) {
            $cursor->subDay();
        }

        return $cursor;
    }

    /** Frist zum Abbestellen für $servingDate: derselbe Tag zur Abbestell-Uhrzeit. */
    public function cancelDeadline(Season $season, Carbon $servingDate): Carbon
    {
        return $this->applyTime($servingDate->copy(), $this->settings->cancel_deadline_time);
    }

    /** Darf $servingDate zum Zeitpunkt $now (Standard: jetzt) noch bestellt werden? */
    public function canOrder(Season $season, Carbon $servingDate, ?Carbon $now = null): bool
    {
        return $this->ohneFrist || ($now ?? Carbon::now())->lte($this->orderDeadline($season, $servingDate));
    }

    /** Darf $servingDate zum Zeitpunkt $now (Standard: jetzt) noch abbestellt werden? */
    public function canCancel(Season $season, Carbon $servingDate, ?Carbon $now = null): bool
    {
        return $this->ohneFrist || ($now ?? Carbon::now())->lte($this->cancelDeadline($season, $servingDate));
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
