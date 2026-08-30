<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pro Saison einstellbar, was Eltern/Kinder beim Bestellen (Karte + Detail-Modal)
 * sehen: Zusatzstoffe/Inhaltsstoffe, Allergene und Diät-Eignung. Warnungen
 * („nicht geeignet") erscheinen nur für das, was eingeblendet ist. Default an =
 * bisheriges Verhalten (bestehende Saisons bleiben unverändert).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kantine_seasons', function (Blueprint $table) {
            if (! Schema::hasColumn('kantine_seasons', 'show_additives')) {
                $table->boolean('show_additives')->default(true)->after('is_active');
            }
            if (! Schema::hasColumn('kantine_seasons', 'show_allergens')) {
                $table->boolean('show_allergens')->default(true)->after('show_additives');
            }
            if (! Schema::hasColumn('kantine_seasons', 'show_diets')) {
                $table->boolean('show_diets')->default(true)->after('show_allergens');
            }
        });
    }

    public function down(): void
    {
        Schema::table('kantine_seasons', function (Blueprint $table) {
            foreach (['show_additives', 'show_allergens', 'show_diets'] as $col) {
                if (Schema::hasColumn('kantine_seasons', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
