<?php

namespace Intranet\Modules\Schulkantine\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Eine Saison (Schuljahr). Oberster Container des Moduls.
 */
class Season extends Model
{
    protected $table = 'kantine_seasons';

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'bundesland',
        'ogs_price',
        'opening_weekdays',
        'is_active',
        'show_additives',
        'show_allergens',
        'show_diets',
        'ratings_enabled',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'ogs_price' => 'decimal:2',
            'opening_weekdays' => 'array',
            'is_active' => 'boolean',
            'show_additives' => 'boolean',
            'show_allergens' => 'boolean',
            'show_diets' => 'boolean',
            'ratings_enabled' => 'boolean',
        ];
    }

    /**
     * Sind Bewertungen in der aktiven Saison erlaubt? Steuert Menüpunkt und
     * Zugriff auf „Essen bewerten". Bewusst fehlertolerant (Default an), damit
     * das Menü auch vor der Migration oder ohne aktive Saison rendert – der
     * Menü-Filter im Core wertet das je Anfrage aus.
     */
    public static function ratingsEnabledForActive(): bool
    {
        try {
            $value = static::where('is_active', true)->value('ratings_enabled');
        } catch (\Throwable $e) {
            return true;
        }

        // Keine aktive Saison ODER Spalte fehlt (null) → Standard: erlaubt.
        return $value === null ? true : (bool) $value;
    }

    /** Alle Schließtage dieser Saison (Ferien, Feiertage, Sonderfälle). */
    public function closedDays(): HasMany
    {
        return $this->hasMany(ClosedDay::class, 'season_id');
    }

    /**
     * Hat die Kantine an diesem Datum geöffnet?
     *
     * Regel: innerhalb des Saison-Zeitraums, an einem Öffnungs-Wochentag,
     * und kein Schließtag. Fällt die Öffnungs-Wochentage-Liste leer aus,
     * gilt Montag–Freitag als Standard.
     */
    public function isOpenOn(Carbon $date): bool
    {
        if ($date->lt($this->start_date) || $date->gt($this->end_date)) {
            return false;
        }

        $openingWeekdays = $this->opening_weekdays ?: [1, 2, 3, 4, 5];

        if (! in_array($date->dayOfWeekIso, $openingWeekdays, true)) {
            return false;
        }

        return ! $this->closedDays()
            ->whereDate('date', $date->toDateString())
            ->exists();
    }
}
