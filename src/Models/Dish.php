<?php

namespace Intranet\Modules\Schulkantine\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Intranet\Modules\Schulkantine\Support\Essensvertrag;

/**
 * Ein Gericht aus dem Katalog. Fixpreis, genau eine Kategorie, dazu Allergene,
 * Zusatzstoffe und die Diäten, für die es NICHT geeignet ist (jeweils n:m).
 */
class Dish extends Model
{
    protected $table = 'kantine_dishes';

    /**
     * Fleischart → [Bezeichnung, Symbol] – das Symbol steht im Speiseplan, wie früher
     * am Menü&Serve-Terminal. Leer = keine Angabe (kein Symbol).
     */
    public const FLEISCHARTEN = [
        'vegan' => ['Vegan', '🌱'],
        'vegetarisch' => ['Vegetarisch', '🥦'],
        'fisch' => ['Fisch', '🐟'],
        'gefluegel' => ['Geflügel', '🐔'],
        'rind' => ['Rind', '🐄'],
        'schwein' => ['Schwein', '🐖'],
        'rind_schwein' => ['Rind/Schwein', '🐄🐖'],
        'lamm' => ['Lamm', '🐑'],
        'fleisch' => ['Fleisch', '🍖'],
    ];

    protected $fillable = [
        'category_id',
        'name',
        'description',
        'fleischart',
        'photo_path',
        'price',
        'contract_prices',
        'is_active',
    ];

    /**
     * Preis für diesen Esser: der günstigste Preis seiner Vertragsarten, für die am
     * Gericht einer eingetragen ist – sonst der Hauptpreis.
     */
    public function preisFuer(?User $esser): float
    {
        $preise = $this->contract_prices ?: [];
        if ($esser && $preise) {
            $eigene = [];
            foreach (Essensvertrag::arten($esser) as $art) {
                if (isset($preise[$art])) {
                    $eigene[] = (float) $preise[$art];
                }
            }
            if ($eigene) {
                return min($eigene);
            }
        }

        return (float) $this->price;
    }

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
            'contract_prices' => 'array',
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

    /** Symbol der Fleischart (🐄, 🥦 …) oder null. */
    public function symbol(): ?string
    {
        return self::FLEISCHARTEN[$this->fleischart][1] ?? null;
    }

    /** Bezeichnung der Fleischart oder null. */
    public function fleischartName(): ?string
    {
        return self::FLEISCHARTEN[$this->fleischart][0] ?? null;
    }

    /**
     * Diäten, für die dieses Gericht NICHT geeignet ist (Ausnahmen). Standard =
     * für alles geeignet; hier werden nur die Verstöße markiert. Grundlage der
     * Diät-Warnung: fordert ein Esser eine Diät, die hier steht → Warnung.
     */
    public function unsuitableDiets(): BelongsToMany
    {
        return $this->belongsToMany(Diet::class, 'kantine_dish_diet', 'dish_id', 'diet_id');
    }
}
