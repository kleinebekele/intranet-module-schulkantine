<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

/**
 * Farb- und Reihenfolge-Korrektur an den Kategorien.
 *
 * Auf den Bestellseiten steht Grün bereits für „bestellt". Sparmenü (Teal) und
 * Snack (Limette) lagen genau daneben – ein grünes Gericht in einer grünen
 * Kachel liest sich wie ein Status, nicht wie eine Kategorie. Und: Betriebe wie
 * die Waldorfschule arbeiten NUR mit Sparmenüs, alle anderen Kategorien sind
 * dort nicht vorbestellbar. Das Sparmenü ist damit die Kategorie, die man
 * ständig sieht – sie gehört nach vorn und bekommt die kräftige Farbe.
 *
 * Deshalb:
 *  - Sparmenü und Hauptmenü TAUSCHEN ihre Farben (Tausch statt fester Werte,
 *    damit lokal angepasste Farbtöne erhalten bleiben).
 *  - Snack wird entgrünt – aber nur, wenn er noch auf der Startfarbe steht.
 *  - Sparmenü rutscht in der Sortierung an die erste Stelle.
 *
 * Die Kategorien werden über ihren Namen gefunden. Wurden sie umbenannt,
 * passiert schlicht nichts – das ist gewollt: Dann hat jemand die Farben
 * ohnehin selbst in der Hand.
 */
return new class extends Migration
{
    private const SNACK_DEFAULT = '#84cc16'; // Limette – zu nah am „bestellt"-Grün
    private const SNACK_NEW = '#8b5cf6';     // Violett

    public function up(): void
    {
        $bundle = DB::table('kantine_categories')->where('name', 'Sparmenü')->first();
        $main = DB::table('kantine_categories')->where('name', 'Hauptmenü')->first();

        if ($bundle && $main) {
            DB::table('kantine_categories')->where('id', $bundle->id)->update(['color' => $main->color]);
            DB::table('kantine_categories')->where('id', $main->id)->update(['color' => $bundle->color]);
        }

        DB::table('kantine_categories')
            ->where('name', 'Snack')
            ->where('color', self::SNACK_DEFAULT)
            ->update(['color' => self::SNACK_NEW]);

        // Vor die kleinste vorhandene Sortierung setzen, statt alle Kategorien
        // durchzunummerieren: Die übrige Reihenfolge bleibt so, wie sie jemand
        // eingestellt hat.
        if ($bundle) {
            $min = (int) DB::table('kantine_categories')->min('sort_order');
            DB::table('kantine_categories')
                ->where('id', $bundle->id)
                ->update(['sort_order' => max(0, $min - 1)]);
        }
    }

    public function down(): void
    {
        // Nur den Farbtausch zurückdrehen; die Sortierung bleibt, weil der frühere
        // Wert nicht rekonstruierbar ist (und Reihenfolge im UI pflegbar ist).
        $bundle = DB::table('kantine_categories')->where('name', 'Sparmenü')->first();
        $main = DB::table('kantine_categories')->where('name', 'Hauptmenü')->first();

        if ($bundle && $main) {
            DB::table('kantine_categories')->where('id', $bundle->id)->update(['color' => $main->color]);
            DB::table('kantine_categories')->where('id', $main->id)->update(['color' => $bundle->color]);
        }

        DB::table('kantine_categories')
            ->where('name', 'Snack')
            ->where('color', self::SNACK_NEW)
            ->update(['color' => self::SNACK_DEFAULT]);
    }
};
