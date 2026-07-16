<?php

namespace Intranet\Modules\Schulkantine\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eine Gericht-Kategorie. Steuert Reihenfolge, Farbe und – über zwei symmetrische
 * Flags – woher ein Gericht kommen darf:
 *  - `allows_preorder`: darf vorbestellt werden (Standard).
 *  - `allows_walkin`:   darf spontan am Tresen geholt werden.
 * Beides aus ist verboten (siehe Migration); nur `allows_walkin` = „nur spontan".
 */
class Category extends Model
{
    protected $table = 'kantine_categories';

    protected $fillable = [
        'name',
        'allows_walkin',
        'allows_preorder',
        'sort_order',
        'color',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'allows_walkin' => 'boolean',
            'allows_preorder' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Nur spontan erhältlich: steht auf dem Speiseplan und ist am Tresen zu haben,
     * taucht in der Vorbestellung aber nicht auf.
     */
    public function isWalkinOnly(): bool
    {
        return ! $this->allows_preorder && $this->allows_walkin;
    }

    /** Name der per Migration angelegten Sparmenü-Kategorie. */
    public const BUNDLE_NAME = 'Sparmenü';

    /**
     * Die Kategorie, unter der Sparmenüs laufen – oder null, wenn es sie nicht gibt.
     *
     * Vorhandene Sparmenüs geben den Ton an: Die Kategorie darf umbenannt werden,
     * ohne dass die Erkennung bricht. Erst wenn es noch keine gibt, zählt der Name.
     */
    public static function bundleId(): ?int
    {
        return Dish::whereHas('components')->value('category_id')
            ?? static::where('name', self::BUNDLE_NAME)->value('id');
    }
}
