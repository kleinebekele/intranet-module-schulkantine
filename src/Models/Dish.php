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

    /** Allergene (Codes), die das Gericht zu Fisch/Meeresfrüchten machen: Krebstiere, Fisch, Weichtiere. */
    public const ALLERGENE_FISCH = ['B', 'D', 'N'];

    /** Allergene (Codes), die „vegan" ausschließen: Eier, Milch. */
    public const ALLERGENE_NICHT_VEGAN = ['C', 'G'];

    /**
     * Die Fleischart, wie sie angezeigt wird – die Allergene haben Vorrang vor der
     * Angabe: Fisch/Krebstiere/Weichtiere → Fisch (statt keine Angabe, „Fleisch",
     * vegan oder vegetarisch); Ei oder Milch machen aus „vegan" „vegetarisch".
     */
    public function fleischartWirksam(): ?string
    {
        return self::artWirksam($this->fleischart, $this->allergenCodes());
    }

    /** @param  list<string>  $codes  Allergen-Codes (A–N) */
    public static function artWirksam(?string $art, array $codes): ?string
    {
        if (array_intersect($codes, self::ALLERGENE_FISCH) && in_array($art, [null, 'fleisch', 'vegan', 'vegetarisch'], true)) {
            return 'fisch';
        }
        if ($art === 'vegan' && array_intersect($codes, self::ALLERGENE_NICHT_VEGAN)) {
            return 'vegetarisch';
        }

        return $art;
    }

    /** @return list<string> */
    private function allergenCodes(): array
    {
        return $this->allergens->pluck('code')->map(fn ($c) => strtoupper((string) $c))->all();
    }

    /**
     * Was aus Fleischart und Allergenen für die Diäten FOLGT – Diät-Name (klein) →
     * [geeignet?, Grund]. Diäten, die hier fehlen, entscheidet man von Hand. Das
     * Gericht-Formular rechnet dieselben Regeln live in JavaScript nach (dishes/form).
     *
     * @param  array<string, string>  $allergene  Code → Name der Allergene des Gerichts
     * @return array<string, array{0: bool, 1: string}>
     */
    public static function dietRegeln(?string $art, array $allergene): array
    {
        $codes = array_keys($allergene);
        $art = self::artWirksam($art, $codes);
        $artName = self::FLEISCHARTEN[$art][0] ?? '';
        $namen = fn (array $wahl) => implode(', ', array_values(array_intersect_key($allergene, array_flip($wahl))));
        $tier = in_array($art, ['fisch', 'gefluegel', 'rind', 'schwein', 'rind_schwein', 'lamm', 'fleisch'], true);
        $schwein = in_array($art, ['schwein', 'rind_schwein'], true);

        $r = [];
        if ($tier) {
            $r['vegetarisch'] = [false, $artName];
            $r['vegan'] = [false, $artName];
        } elseif (in_array($art, ['vegan', 'vegetarisch'], true)) {
            $r['vegetarisch'] = [true, $artName];
            $nichtVegan = $namen(self::ALLERGENE_NICHT_VEGAN);
            $r['vegan'] = $nichtVegan !== '' ? [false, $nichtVegan] : [$art === 'vegan', $artName];
        } elseif (($nichtVegan = $namen(self::ALLERGENE_NICHT_VEGAN)) !== '') {
            $r['vegan'] = [false, $nichtVegan];
        }
        if ($schwein) {
            $r['halal'] = [false, $artName];
            $r['schweinefleischfrei'] = [false, $artName];
        } elseif ($art !== null && $art !== 'fleisch') {
            $r['schweinefleischfrei'] = [true, $artName];
        }
        if (isset($allergene['A'])) {
            $r['glutenfrei'] = [false, $allergene['A']];
        }
        if (isset($allergene['G'])) {
            $r['laktosefrei'] = [false, $allergene['G']];
        }

        return $r;
    }

    /**
     * „Nicht geeignet für" neu setzen: was aus Fleischart/Allergenen folgt, gilt fest;
     * die übrigen Diäten sind nur geeignet, wenn sie in $geeignetIds angekreuzt sind.
     *
     * @param  list<int|string>  $geeignetIds
     */
    public function dietenSetzen(array $geeignetIds): void
    {
        $this->load('allergens');
        $regeln = self::dietRegeln($this->fleischart, $this->allergens->mapWithKeys(fn ($a) => [strtoupper((string) $a->code) => $a->name])->all());
        $geeignetIds = array_map('intval', $geeignetIds);

        $nicht = Diet::all()
            ->reject(fn (Diet $d) => $regeln[mb_strtolower($d->name)][0] ?? in_array($d->id, $geeignetIds, true))
            ->pluck('id')->all();

        $this->unsuitableDiets()->sync($nicht);
    }

    /** Symbol der Fleischart (🐄, 🥦 …) oder null. */
    public function symbol(): ?string
    {
        return self::FLEISCHARTEN[$this->fleischartWirksam()][1] ?? null;
    }

    /** Bezeichnung der Fleischart oder null. */
    public function fleischartName(): ?string
    {
        return self::FLEISCHARTEN[$this->fleischartWirksam()][0] ?? null;
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
