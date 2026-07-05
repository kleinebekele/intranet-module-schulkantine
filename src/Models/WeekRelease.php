<?php

namespace Intranet\Modules\Schulkantine\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Manuelle Übersteuerung der Wochen-Freigabe. Existiert nur, wenn der Admin die
 * Automatik für eine Woche bewusst übersteuert (siehe Migration/ReleaseService).
 */
class WeekRelease extends Model
{
    protected $table = 'kantine_week_releases';

    public const STATE_RELEASED = 'released';

    public const STATE_HELD = 'held';

    protected $fillable = [
        'season_id',
        'week_start',
        'state',
    ];

    protected function casts(): array
    {
        return [
            'week_start' => 'date',
        ];
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }
}
