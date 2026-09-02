<?php

namespace Intranet\Modules\Schulkantine\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ein Kategorie-Slot einer Menü-Vorlage: „aus Kategorie X genau `quantity`
 * Gerichte". Welche Gerichte konkret, entscheidet der Speiseplan je Tag.
 */
class MenuTemplateSlot extends Model
{
    public $timestamps = false;

    protected $table = 'kantine_menu_template_slots';

    protected $fillable = [
        'menu_template_id',
        'category_id',
        'quantity',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function menuTemplate(): BelongsTo
    {
        return $this->belongsTo(MenuTemplate::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }
}
