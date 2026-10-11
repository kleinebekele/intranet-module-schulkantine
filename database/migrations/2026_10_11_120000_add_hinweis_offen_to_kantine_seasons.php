<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hinweis zu offenen Beträgen („Meine Abrechnung", Fragezeichen). NULL = Standardtext
 * (Season::HINWEIS_OFFEN) – so hat auch jede neue Saison ihn, ohne Kopieren.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('kantine_seasons', 'hinweis_offen')) {
            Schema::table('kantine_seasons', function (Blueprint $table) {
                $table->text('hinweis_offen')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('kantine_seasons', 'hinweis_offen')) {
            Schema::table('kantine_seasons', function (Blueprint $table) {
                $table->dropColumn('hinweis_offen');
            });
        }
    }
};
