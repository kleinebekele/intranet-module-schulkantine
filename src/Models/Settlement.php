<?php

namespace Intranet\Modules\Schulkantine\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bezahlt-Status einer Person für einen Monat (Phase 5). Siehe Migration:
 * Existenz = „als bezahlt markiert", kein Eintrag = offen.
 */
class Settlement extends Model
{
    protected $table = 'kantine_settlements';

    protected $fillable = [
        'season_id',
        'user_id',
        'year',
        'month',
        'amount',
        'paid_at',
        'marked_by',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    /** Der Esser (= Benutzer). */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Wer hat als bezahlt markiert. */
    public function markedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }
}
