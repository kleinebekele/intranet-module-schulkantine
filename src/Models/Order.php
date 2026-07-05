<?php

namespace Intranet\Modules\Schulkantine\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eine Vorbestellung. Siehe Migration für die zwei Ausprägungen (Menü vs. OGS).
 */
class Order extends Model
{
    protected $table = 'kantine_orders';

    public const STATUS_ORDERED = 'bestellt';

    public const STATUS_CANCELLED = 'storniert';

    protected $fillable = [
        'season_id',
        'user_id',
        'date',
        'menu_id',
        'dish_id',
        'category_id',
        'price_snapshot',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'price_snapshot' => 'decimal:2',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ORDERED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
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

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    public function dish(): BelongsTo
    {
        return $this->belongsTo(Dish::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
