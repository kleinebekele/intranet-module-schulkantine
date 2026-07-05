<?php

namespace Intranet\Modules\Schulkantine\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ein Speiseplan-Eintrag: ein Gericht gehört an einem Öffnungstag zum
 * Tagesangebot. Es gibt nur eine Spur – dasselbe Angebot für alle Gruppen.
 */
class Menu extends Model
{
    protected $table = 'kantine_menus';

    protected $fillable = [
        'season_id',
        'date',
        'dish_id',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function dish(): BelongsTo
    {
        return $this->belongsTo(Dish::class);
    }

    /** Bestellungen, die auf diesen Speiseplan-Eintrag verweisen (Löschschutz). */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
