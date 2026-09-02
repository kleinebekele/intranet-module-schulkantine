<?php

namespace Intranet\Modules\Schulkantine\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Eine Menü-Vorlage einer Saison (Ersatz für die Sparmenüs): Name, Preis, die
 * Wochentage, an denen das Menü angeboten wird, und die Kategorie-Slots (aus
 * welcher Kategorie wie viele Gerichte). Die konkreten Gerichte werden erst je
 * Öffnungstag im Speiseplan gewählt.
 */
class MenuTemplate extends Model
{
    protected $table = 'kantine_menu_templates';

    protected $fillable = [
        'season_id',
        'name',
        'price',
        'weekdays',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'weekdays' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    /** Die Kategorie-Slots des Menüs (aus welcher Kategorie wie viele Gerichte). */
    public function slots(): HasMany
    {
        return $this->hasMany(MenuTemplateSlot::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Wird das Menü an diesem Datum (Wochentag) angeboten? Leere Wochentagsliste
     * = an allen Öffnungstagen. (Ob die Kantine an dem Tag überhaupt offen hat,
     * prüft der Aufrufer separat über Season::isOpenOn().)
     */
    public function availableOn(Carbon $date): bool
    {
        $weekdays = $this->weekdays ?: [];

        return $weekdays === [] || in_array($date->dayOfWeekIso, $weekdays, true);
    }
}
