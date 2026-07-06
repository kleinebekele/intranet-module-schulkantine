<?php

namespace Intranet\Modules\Schulkantine\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intranet\Modules\Schulkantine\Models\Additive;
use Intranet\Modules\Schulkantine\Models\Allergen;
use Intranet\Modules\Schulkantine\Models\Category;
use Intranet\Modules\Schulkantine\Models\Diet;
use Intranet\Modules\Schulkantine\Models\Dish;

/**
 * Legt den Beispiel-Katalog aus der Entwicklungsumgebung an: die festen
 * Kategorien und Gerichte inkl. Allergen-/Zusatzstoff-/Diät-Verknüpfungen.
 *
 * Idempotent: matcht Kategorien und Gerichte über den Namen (updateOrCreate),
 * die Verknüpfungen werden per sync() sauber gesetzt. Fotos sind Dateien und
 * KEINE DB-Daten – sie werden hier nicht gesetzt (photo_path bleibt leer).
 * Nur für Test-/Beta-Umgebungen gedacht.
 */
class SeedDishes extends Command
{
    protected $signature = 'kantine:seed-dishes';

    protected $description = 'Legt Beispiel-Kategorien und -Gerichte an (wie in der Entwicklungsumgebung).';

    /** [name, allows_walkin, sort_order, color] */
    private const CATEGORIES = [
        ['Hauptmenü',  false, 1, '#e8c784'],
        ['Nachspeise', false, 2, '#ec4899'],
        ['Getränk',    true,  3, '#3b82f6'],
        ['Eis',        true,  4, '#06b6d4'],
        ['Snack',      true,  5, '#84cc16'],
    ];

    /** [Kategorie, Name, Preis, [Allergen-Codes], [Zusatzstoff-Codes], [ungeeignete Diäten]] */
    private const DISHES = [
        ['Hauptmenü', 'Chili con Carne mit Reis',          '4.20', [],            ['4'],          []],
        ['Hauptmenü', 'Currywurst mit Pommes',             '3.90', [],            ['2', '4', '13'], []],
        ['Hauptmenü', 'Fischstäbchen mit Kartoffelpüree',  '4.10', ['A', 'D', 'G'], [],           []],
        ['Hauptmenü', 'Gemüsecurry mit Reis',              '4.00', [],            [],             []],
        ['Hauptmenü', 'Gemüselasagne',                     '4.00', ['A', 'C', 'G'], [],           []],
        ['Hauptmenü', 'Hähnchenschnitzel mit Pommes',      '4.20', ['A', 'C'],    ['4'],          []],
        ['Hauptmenü', 'Hühnerfrikassee mit Reis',          '4.30', ['A', 'G'],    [],             []],
        ['Hauptmenü', 'Kartoffelauflauf',                  '3.80', ['C', 'G'],    [],             []],
        ['Hauptmenü', 'Käsespätzle',                       '3.90', ['A', 'C', 'G'], [],           []],
        ['Hauptmenü', 'Linseneintopf mit Bockwurst',       '3.60', [],            ['2', '13'],    []],
        ['Hauptmenü', 'Maultaschen in Brühe',              '3.80', ['A', 'C'],    ['4'],          []],
        ['Hauptmenü', 'Pizza Margherita',                  '3.70', ['A', 'G'],    [],             []],
        ['Hauptmenü', 'Rahmschnitzel mit Knödel',          '4.60', ['A', 'C', 'G'], ['4'],        []],
        ['Hauptmenü', 'Rindergulasch mit Nudeln',          '4.50', ['A'],         ['4'],          []],
        ['Hauptmenü', 'Spaghetti Bolognese',               '3.80', ['A'],         [],             ['glutenfrei']],
        ['Nachspeise', 'Apfelmus',                         '1.00', [],            ['8'],          []],
        ['Nachspeise', 'Grießbrei mit Zimt',               '1.40', ['A', 'G'],    [],             []],
        ['Nachspeise', 'Joghurt mit Früchten',             '1.30', ['G'],         [],             []],
        ['Nachspeise', 'Käsekuchen',                       '1.80', ['A', 'C', 'G'], [],           []],
        ['Nachspeise', 'Obstsalat',                        '1.50', [],            [],             []],
        ['Nachspeise', 'Rote Grütze mit Vanillesoße',      '1.60', ['G'],         ['1'],          []],
        ['Nachspeise', 'Schokopudding',                    '1.20', ['G'],         ['1'],          []],
        ['Nachspeise', 'Vanillepudding',                   '1.20', ['G'],         [],             []],
        ['Getränk', 'Apfelschorle',                        '1.00', [],            [],             []],
        ['Getränk', 'Eistee Pfirsich',                     '1.00', [],            ['1', '9'],     []],
        ['Getränk', 'Kakao',                               '1.20', ['G'],         ['9'],          []],
        ['Getränk', 'Mineralwasser',                       '0.80', [],            [],             []],
        ['Getränk', 'Orangensaft',                         '1.20', [],            [],             []],
        ['Eis', 'Erdbeersorbet',                           '1.00', [],            ['1'],          []],
        ['Eis', 'Schokoeis',                               '1.00', ['G'],         [],             []],
        ['Eis', 'Vanilleeis',                              '1.00', ['G'],         [],             []],
        ['Snack', 'Butterbrezel',                          '0.90', ['A', 'G'],    [],             []],
    ];

    public function handle(): int
    {
        // 1) Kategorien sicherstellen (Match über Namen).
        $this->info('Lege Kategorien an …');
        $categoryIds = [];
        $categoryColors = [];
        foreach (self::CATEGORIES as [$name, $walkin, $sort, $color]) {
            $category = Category::updateOrCreate(
                ['name' => $name],
                ['allows_walkin' => $walkin, 'sort_order' => $sort, 'color' => $color, 'is_active' => true],
            );
            $categoryIds[$name] = $category->id;
            $categoryColors[$name] = $color;
        }

        // 2) Stammdaten-Lookups (Code/Name → ID). Diese Referenzlisten werden
        //    per Migration angelegt, existieren also bereits.
        $allergenByCode = Allergen::pluck('id', 'code');
        $additiveByCode = Additive::pluck('id', 'code');
        $dietByName = Diet::pluck('id', 'name');

        // 3) Gerichte + Verknüpfungen.
        $this->info('Lege Gerichte an …');
        $count = 0;
        $photos = 0;
        foreach (self::DISHES as [$categoryName, $name, $price, $allergens, $additives, $diets]) {
            $dish = Dish::updateOrCreate(
                ['name' => $name],
                [
                    'category_id' => $categoryIds[$categoryName],
                    'price' => $price,
                    'is_active' => true,
                ],
            );

            $dish->allergens()->sync($allergenByCode->only($allergens)->values()->all());
            $dish->additives()->sync($additiveByCode->only($additives)->values()->all());
            $dish->unsuitableDiets()->sync($dietByName->only($diets)->values()->all());

            // Platzhalter-Bild NUR erzeugen, wenn noch keins hinterlegt ist –
            // so bleiben echte Fotos (z. B. in der Entwicklungsumgebung) erhalten.
            if (empty($dish->photo_path)) {
                $path = 'kantine/dishes/seed-'.Str::slug($name).'.svg';
                Storage::disk('public')->put(
                    $path,
                    $this->placeholderSvg($name, $categoryName, $categoryColors[$categoryName] ?? '#64748b'),
                );
                $dish->forceFill(['photo_path' => $path])->save();
                $photos++;
            }

            $count++;
        }

        $this->newLine();
        $this->info('Fertig: '.count(self::CATEGORIES).' Kategorien und '.$count.' Gerichte.');
        $this->line("Platzhalter-Bilder erzeugt: {$photos} (vorhandene Fotos blieben unberührt).");

        return self::SUCCESS;
    }

    /** Eine schlichte, quadratische SVG-Platzhalterkarte in der Kategorie-Farbe. */
    private function placeholderSvg(string $name, string $category, string $color): string
    {
        $top = $this->lighten($color, 0.45);
        $catLabel = htmlspecialchars(mb_strtoupper($category), ENT_QUOTES);

        // Namen in bis zu drei zentrierte Zeilen umbrechen (SVG kann nicht selbst).
        $lines = $this->wrap($name, 15, 3);
        $lineHeight = 34;
        $startY = 322 - (count($lines) - 1) * $lineHeight;
        $tspans = '';
        foreach ($lines as $i => $line) {
            $y = $startY + $i * $lineHeight;
            $text = htmlspecialchars($line, ENT_QUOTES);
            $tspans .= '<tspan x="200" y="'.$y.'">'.$text.'</tspan>';
        }

        return <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 400">
          <defs>
            <linearGradient id="g" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0" stop-color="{$top}"/>
              <stop offset="1" stop-color="{$color}"/>
            </linearGradient>
            <linearGradient id="scrim" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0" stop-color="#0f172a" stop-opacity="0"/>
              <stop offset="1" stop-color="#0f172a" stop-opacity="0.55"/>
            </linearGradient>
          </defs>
          <rect width="400" height="400" fill="url(#g)"/>
          <circle cx="200" cy="150" r="82" fill="#ffffff" opacity="0.16"/>
          <circle cx="200" cy="150" r="56" fill="#ffffff" opacity="0.12"/>
          <rect y="200" width="400" height="200" fill="url(#scrim)"/>
          <text x="200" y="66" text-anchor="middle" fill="#ffffff" opacity="0.85"
                font-family="Arial, sans-serif" font-size="20" letter-spacing="3">{$catLabel}</text>
          <text text-anchor="middle" fill="#ffffff"
                font-family="Arial, sans-serif" font-size="28" font-weight="bold">{$tspans}</text>
        </svg>
        SVG;
    }

    /** Text in maximal $max Zeilen à ~$width Zeichen umbrechen (wortweise). */
    private function wrap(string $text, int $width, int $max): array
    {
        $lines = [];
        $current = '';
        foreach (explode(' ', $text) as $word) {
            $candidate = $current === '' ? $word : $current.' '.$word;
            if (mb_strlen($candidate) > $width && $current !== '') {
                $lines[] = $current;
                $current = $word;
            } else {
                $current = $candidate;
            }
        }
        if ($current !== '') {
            $lines[] = $current;
        }

        if (count($lines) > $max) {
            $lines = array_slice($lines, 0, $max);
            $lines[$max - 1] .= '…';
        }

        return $lines;
    }

    /** Hex-Farbe Richtung Weiß aufhellen ($amount 0..1). */
    private function lighten(string $hex, float $amount): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6) {
            return '#'.$hex;
        }
        $mix = fn (int $c) => (int) round($c + (255 - $c) * $amount);
        [$r, $g, $b] = [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];

        return sprintf('#%02x%02x%02x', $mix($r), $mix($g), $mix($b));
    }
}
