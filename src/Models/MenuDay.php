<?php

namespace Intranet\Modules\Schulkantine\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ein konkretes Menü an einem Öffnungstag (materialisiert per „Push"). Name und
 * Preis sind ein Snapshot der Vorlage; die Slots werden im Speiseplan mit
 * Gerichten gefüllt.
 */
class MenuDay extends Model
{
    protected $table = 'kantine_menu_days';

    protected $fillable = [
        'season_id',
        'menu_template_id',
        'date',
        'name',
        'price',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'price' => 'decimal:2',
        ];
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(MenuTemplate::class, 'menu_template_id');
    }

    /** Die füllbaren Plätze dieses Menü-Tags (je Kategorie `quantity` Stück). */
    public function slots(): HasMany
    {
        return $this->hasMany(MenuDaySlot::class)->orderBy('position')->orderBy('id');
    }
}
