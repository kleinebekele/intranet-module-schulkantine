<?php

namespace Intranet\Modules\Schulkantine\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ein einzelner Schließtag innerhalb einer Saison.
 */
class ClosedDay extends Model
{
    protected $table = 'kantine_closed_days';

    protected $fillable = [
        'season_id',
        'date',
        'reason',
        'type',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    /** Die Saison, zu der dieser Schließtag gehört. */
    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class, 'season_id');
    }
}
