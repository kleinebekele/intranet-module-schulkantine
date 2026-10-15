<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pro Saison einstellbar, ob die Esser (bzw. Eltern) ihre Daten – Allergien,
 * Diäten, Freigaben – selbst pflegen. Aus = der Menüpunkt „Meine Daten" ist
 * ausgeblendet und gesperrt. Default an = bisheriges Verhalten.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kantine_seasons', function (Blueprint $table) {
            if (! Schema::hasColumn('kantine_seasons', 'daten_selbst')) {
                $table->boolean('daten_selbst')->default(true)->after('ratings_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('kantine_seasons', function (Blueprint $table) {
            if (Schema::hasColumn('kantine_seasons', 'daten_selbst')) {
                $table->dropColumn('daten_selbst');
            }
        });
    }
};
