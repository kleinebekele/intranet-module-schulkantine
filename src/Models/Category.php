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
}
