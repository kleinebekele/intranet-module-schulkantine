<?php

namespace Intranet\Modules\Schulkantine\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

/**
 * Ein Gericht aus dem Katalog. Fixpreis, genau eine Kategorie, dazu Allergene,
 * Zusatzstoffe und die Diäten, für die es NICHT geeignet ist (jeweils n:m).
 *
 * Ein Gericht kann außerdem ein **Sparmenü** sein: dann zeigt es über
 * `components()` auf andere Gerichte und fasst sie zu einem eigenen (günstigeren)
 * Fixpreis zusammen. Für den Rest des Moduls bleibt es ein ganz normales Gericht
 * mit einem Preis – Bestellung, Ausgabe und Abrechnung merken davon nichts.
 *
 * Wichtig für alles, was mit Verträglichkeiten zu tun hat: NICHT `allergens()`
 * benutzen, sondern `effectiveAllergens()` – sonst warnt ein Sparmenü nicht vor
 * den Allergenen seiner Bestandteile.
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

    /**
     * Diäten, für die dieses Gericht NICHT geeignet ist (Ausnahmen). Standard =
     * für alles geeignet; hier werden nur die Verstöße markiert. Grundlage der
     * Diät-Warnung: fordert ein Esser eine Diät, die hier steht → Warnung.
     */
    public function unsuitableDiets(): BelongsToMany
    {
        return $this->belongsToMany(Diet::class, 'kantine_dish_diet', 'dish_id', 'diet_id');
    }

    /* ---------------------------------------------------------------------
     | Sparmenü (Bündel-Gericht)
     * ------------------------------------------------------------------ */

    /** Die Gerichte, aus denen dieses Sparmenü besteht (leer = normales Gericht). */
    public function components(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'kantine_dish_components', 'bundle_dish_id', 'part_dish_id')
            ->withPivot('sort_order')
            ->orderBy('kantine_dish_components.sort_order');
    }

    /** Die Sparmenüs, in denen dieses Gericht als Bestandteil steckt. */
    public function partOfBundles(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'kantine_dish_components', 'part_dish_id', 'bundle_dish_id');
    }

    /**
     * Ist dieses Gericht ein Sparmenü? Ein Gericht IST ein Bündel, sobald es
     * Bestandteile hat – es gibt bewusst kein separates Flag.
     */
    public function isBundle(): bool
    {
        return $this->components->isNotEmpty();
    }

    /**
     * Alle Allergene, vor denen dieses Gericht warnen muss: die eigenen plus die
     * aller Bestandteile. Bei einem Sparmenü stecken die Allergene per Definition
     * in den Bestandteilen – wer hier `allergens()` nutzt, sieht nichts.
     */
    public function effectiveAllergens(): Collection
    {
        return $this->mergeFromComponents('allergens');
    }

    /** Analog zu effectiveAllergens(), für Zusatzstoffe (Kennzeichnungspflicht). */
    public function effectiveAdditives(): Collection
    {
        return $this->mergeFromComponents('additives');
    }

    /** Analog: Ein Sparmenü verstößt gegen jede Diät, gegen die ein Bestandteil verstößt. */
    public function effectiveUnsuitableDiets(): Collection
    {
        return $this->mergeFromComponents('unsuitableDiets');
    }

    /**
     * Die Kategorien, die eine Bestellung dieses Gerichts belegt. Ein normales
     * Gericht belegt seine eigene; ein Sparmenü zusätzlich die seiner Bestandteile.
     *
     * Das ist der Schlüssel für zwei Regeln:
     *  - Verdrängung: Ein Sparmenü ersetzt einzeln bestelltes Hauptmenü/Nachspeise
     *    (und umgekehrt), weil sich die belegten Kategorien überschneiden.
     *  - Elternsperre: Ist EINE der belegten Kategorien für das Kind gesperrt, ist
     *    das ganze Sparmenü gesperrt – sonst käme der Nachtisch durchs Hintertürchen.
     *
     * @return array<int> Kategorie-IDs, ohne Dubletten
     */
    public function occupiedCategoryIds(): array
    {
        $ids = collect([$this->category_id])
            ->merge($this->components->pluck('category_id'))
            ->filter()      // Gerichte ohne Kategorie belegen nichts
            ->unique()
            ->values();

        return $ids->all();
    }

    /** Was die Bestandteile einzeln gekostet hätten (Grundlage der Ersparnis). */
    public function componentsPrice(): float
    {
        return (float) $this->components->sum(fn (self $d) => (float) $d->price);
    }

    /** Ersparnis gegenüber dem Einzelkauf. Kann 0 oder negativ sein – dann warnt das Formular. */
    public function savings(): float
    {
        return round($this->componentsPrice() - (float) $this->price, 2);
    }

    /**
     * Eigene Einträge + die aller Bestandteile, nach id entdoppelt.
     *
     * @param  'allergens'|'additives'|'unsuitableDiets'  $relation
     */
    private function mergeFromComponents(string $relation): Collection
    {
        return $this->{$relation}
            ->merge($this->components->flatMap->{$relation})
            ->unique('id')
            ->values();
    }
}
