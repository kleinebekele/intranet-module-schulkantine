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
}
