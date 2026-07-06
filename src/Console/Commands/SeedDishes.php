<?php

namespace Intranet\Modules\Schulkantine\Console\Commands;

use Illuminate\Console\Command;
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
        foreach (self::CATEGORIES as [$name, $walkin, $sort, $color]) {
            $category = Category::updateOrCreate(
                ['name' => $name],
                ['allows_walkin' => $walkin, 'sort_order' => $sort, 'color' => $color, 'is_active' => true],
            );
            $categoryIds[$name] = $category->id;
        }

        // 2) Stammdaten-Lookups (Code/Name → ID). Diese Referenzlisten werden
        //    per Migration angelegt, existieren also bereits.
        $allergenByCode = Allergen::pluck('id', 'code');
        $additiveByCode = Additive::pluck('id', 'code');
        $dietByName = Diet::pluck('id', 'name');

        // 3) Gerichte + Verknüpfungen.
        $this->info('Lege Gerichte an …');
        $count = 0;
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
            $count++;
        }

        $this->newLine();
        $this->info('Fertig: '.count(self::CATEGORIES).' Kategorien und '.$count.' Gerichte.');
        $this->line('<comment>Hinweis:</comment> Fotos sind nicht enthalten (Bilddateien, keine DB-Daten). Bei Bedarf im UI hochladen.');

        return self::SUCCESS;
    }
}
