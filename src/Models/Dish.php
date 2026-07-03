<?php

namespace Intranet\Modules\Schulkantine\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Ein Gericht aus dem Katalog. Fixpreis, genau eine Kategorie, dazu Allergene,
 * Zusatzstoffe und geeignete Diäten (jeweils n:m).
 */
class Dish extends Model
{
    protected $table = 'kantine_dishes';

    protected $fillable = [
        'category_id',
        'name',
        'description',
        'photo_path',
        'price',
        'is_active',
    ];

    /** Öffentliche URL des Fotos (oder null, wenn keins hinterlegt ist). */
    public function photoUrl(): ?string
    {
        if (! $this->photo_path) {
            return null;
        }

        $url = asset('storage/'.$this->photo_path);

        // Cache-Buster anhand der Datei-Änderungszeit: Wird ein Bild unter
        // gleichem Namen ersetzt, lädt der Browser automatisch die neue Version.
        try {
            $url .= '?v='.\Illuminate\Support\Facades\Storage::disk('public')->lastModified($this->photo_path);
        } catch (\Throwable $e) {
            // Datei (noch) nicht vorhanden – dann eben ohne Cache-Buster.
        }

        return $url;
    }

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function allergens(): BelongsToMany
    {
        return $this->belongsToMany(Allergen::class, 'kantine_dish_allergen', 'dish_id', 'allergen_id');
    }

    public function additives(): BelongsToMany
    {
        return $this->belongsToMany(Additive::class, 'kantine_dish_additive', 'dish_id', 'additive_id');
    }

    public function diets(): BelongsToMany
    {
        return $this->belongsToMany(Diet::class, 'kantine_dish_diet', 'dish_id', 'diet_id');
    }
}
