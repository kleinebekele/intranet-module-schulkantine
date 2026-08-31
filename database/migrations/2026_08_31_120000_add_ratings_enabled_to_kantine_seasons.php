<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pro Saison einstellbar, ob Essen bewertet werden darf. Aus = der Menüpunkt
 * „Essen bewerten" wird ausgeblendet und die Bewertungs-Routen sind gesperrt.
 * Default an = bisheriges Verhalten (bestehende Saisons bleiben unverändert).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kantine_seasons', function (Blueprint $table) {
            if (! Schema::hasColumn('kantine_seasons', 'ratings_enabled')) {
                $table->boolean('ratings_enabled')->default(true)->after('show_diets');
            }
        });
    }

    public function down(): void
    {
        Schema::table('kantine_seasons', function (Blueprint $table) {
            if (Schema::hasColumn('kantine_seasons', 'ratings_enabled')) {
                $table->dropColumn('ratings_enabled');
            }
        });
    }
};
