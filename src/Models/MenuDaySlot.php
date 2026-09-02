<?php

namespace Intranet\Modules\Schulkantine\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ein einzelner Platz eines Menü-Tags: gehört zu einer Kategorie und wird im
 * Speiseplan mit einem konkreten Gericht gefüllt (dish_id).
 */
class MenuDaySlot extends Model
{
    public $timestamps = false;

    protected $table = 'kantine_menu_day_slots';

    protected $fillable = [
        'menu_day_id',
        'category_id',
        'position',
        'dish_id',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    public function menuDay(): BelongsTo
    {
        return $this->belongsTo(MenuDay::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function dish(): BelongsTo
    {
        return $this->belongsTo(Dish::class, 'dish_id');
    }
}
