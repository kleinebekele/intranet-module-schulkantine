<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pro Saison einstellbar, ob Eltern ein Wochenbudget für Spontankäufe deckeln
 * können. Aus = kein Budget, keine Prüfung an der Ausgabe, keine Budget-Kachel;
 * abgerechnet wird trotzdem alles. Default an = bisheriges Verhalten.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kantine_seasons', function (Blueprint $table) {
            if (! Schema::hasColumn('kantine_seasons', 'budget_aktiv')) {
                $table->boolean('budget_aktiv')->default(true)->after('daten_selbst');
            }
        });
    }

    public function down(): void
    {
        Schema::table('kantine_seasons', function (Blueprint $table) {
            if (Schema::hasColumn('kantine_seasons', 'budget_aktiv')) {
                $table->dropColumn('budget_aktiv');
            }
        });
    }
};
