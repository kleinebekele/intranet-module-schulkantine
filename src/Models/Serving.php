<?php

namespace Intranet\Modules\Schulkantine\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eine tatsächliche Essensausgabe. Siehe Migration für die zwei Ausprägungen
 * (vorbestellt vs. spontane Abholung).
 */
class Serving extends Model
{
    protected $table = 'kantine_servings';

    protected $fillable = [
        'season_id',
        'user_id',
        'date',
        'order_id',
        'dish_id',
        'category_id',
        'spontaneous',
        'declined',
        'decline_reason',
        'alternative',
        'price_snapshot',
        'served_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'spontaneous' => 'boolean',
            'declined' => 'boolean',
            'alternative' => 'boolean',
            'price_snapshot' => 'decimal:2',
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

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function dish(): BelongsTo
    {
        return $this->belongsTo(Dish::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** Das Ausgabepersonal, das abgehakt hat. */
    public function servedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'served_by');
    }
}
