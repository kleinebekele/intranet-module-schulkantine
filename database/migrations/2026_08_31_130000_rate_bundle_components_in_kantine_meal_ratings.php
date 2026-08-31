<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ein Sparmenü wird beim Bewerten in seine Bestandteile zerlegt: der Esser
 * bewertet jedes Bestandteil-Gericht einzeln, nicht „das Sparmenü". Damit gibt
 * es je Ausgabe (Serving) mehrere Bewertungen – die Eindeutigkeit wandert von
 * serving_id auf das Paar (serving_id, dish_id).
 *
 * Alte Ganz-Bündel-Bewertungen (dish_id = Sparmenü) werden entfernt, da sie
 * nicht mehr vergeben werden können und den Küchen-Report verfälschen würden.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) Alte Bewertungen auf Sparmenüs selbst löschen (jetzt je Bestandteil).
        if (Schema::hasTable('kantine_meal_ratings') && Schema::hasTable('kantine_dish_components')) {
            DB::table('kantine_meal_ratings')
                ->whereIn('dish_id', fn ($q) => $q->from('kantine_dish_components')->select('bundle_dish_id'))
                ->delete();
        }

        // 2) Eindeutigkeit von serving_id auf (serving_id, dish_id) umstellen.
        Schema::table('kantine_meal_ratings', function (Blueprint $table) {
            $table->dropUnique(['serving_id']);
            $table->unique(['serving_id', 'dish_id']);
        });
    }

    public function down(): void
    {
        Schema::table('kantine_meal_ratings', function (Blueprint $table) {
            $table->dropUnique(['serving_id', 'dish_id']);
            $table->unique(['serving_id']);
        });
    }
};
