<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

/**
 * Nachzug zur vorigen Farb-Migration.
 *
 * Der Farbtausch dort gab dem Hauptmenü das Teal des Sparmenüs – damit war es
 * zwar nicht mehr limettengrün, aber immer noch grünlich, und Grün steht auf den
 * Bestellseiten für „bestellt". Hauptmenü bekommt deshalb eine klar unbelegte
 * Farbe, Snack einen warm-gedeckten Ton.
 *
 * Belegt bzw. bewusst gemieden sind: Grün (bestellt), Rot (Allergen-Warnung und
 * Gefahrenzone), Pink (Nachspeise), Blau (Getränk), Cyan (Eis) und das Amber,
 * das jetzt am Sparmenü hängt.
 *
 * Wie zuvor wird nur umgefärbt, was noch auf dem Wert der vorigen Migration
 * steht – wer inzwischen selbst eine Farbe gewählt hat, behält sie.
 */
return new class extends Migration
{
    /** @var array<string, array{from: list<string>, to: string}> */
    private const RECOLOR = [
        // Teal kam aus dem Tausch; #f59e0b für den Fall, dass es dort nie ein
        // Sparmenü zum Tauschen gab und die Startfarbe stehen blieb.
        'Hauptmenü' => ['from' => ['#0d9488', '#f59e0b'], 'to' => '#7c3aed'], // Violett
        // Violett kam aus der vorigen Migration, Limette ist die Startfarbe.
        'Snack' => ['from' => ['#8b5cf6', '#84cc16'], 'to' => '#a16207'],     // Ocker
    ];

    public function up(): void
    {
        foreach (self::RECOLOR as $name => $change) {
            DB::table('kantine_categories')
                ->where('name', $name)
                ->whereIn('color', $change['from'])
                ->update(['color' => $change['to']]);
        }
    }

    public function down(): void
    {
        foreach (self::RECOLOR as $name => $change) {
            DB::table('kantine_categories')
                ->where('name', $name)
                ->where('color', $change['to'])
                ->update(['color' => $change['from'][0]]);
        }
    }
};
