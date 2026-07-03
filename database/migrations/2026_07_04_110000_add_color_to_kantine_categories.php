<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Farbe je Kategorie (#rrggbb). Wird im Speiseplan als Hintergrund/Rahmen der
 * Kategorie-Gruppe genutzt, damit man Kategorien auf einen Blick erkennt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kantine_categories', function (Blueprint $table) {
            $table->string('color', 7)->nullable()->after('sort_order');
        });

        // Sinnvolle Startfarben für die (Demo-)Kategorien, falls vorhanden.
        $defaults = [
            'Hauptmenü' => '#f59e0b',
            'Nachtisch' => '#ec4899',
            'Getränk' => '#3b82f6',
            'Eis' => '#06b6d4',
            'Snack' => '#84cc16',
        ];
        foreach ($defaults as $name => $color) {
            DB::table('kantine_categories')->where('name', $name)->whereNull('color')->update(['color' => $color]);
        }
    }

    public function down(): void
    {
        Schema::table('kantine_categories', function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }
};
