<?php

namespace Intranet\Modules\Schulkantine\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Wochenbudget eines Schülers (siehe Migration). week_start NULL = allgemein.
 */
class Budget extends Model
{
    protected $table = 'kantine_budgets';

    protected $fillable = [
        'user_id',
        'week_start',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'week_start' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Das allgemeine Wochenbudget (für Spontankäufe) eines Kindes – oder null,
     * wenn keins gesetzt ist (= kein Limit).
     */
    public static function weeklyAmount(int $userId): ?float
    {
        $amount = static::where('user_id', $userId)->whereNull('week_start')->value('amount');

        return $amount !== null ? (float) $amount : null;
    }
}
