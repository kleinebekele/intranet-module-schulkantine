<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hinweistext für die Esser, wenn sie ihre Allergien nicht selbst pflegen
 * (daten_selbst aus): erscheint bei „Essen bestellen" hinter der Kachel
 * „Fragen zu Allergenen?". Leer = keine Kachel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kantine_seasons', function (Blueprint $table) {
            if (! Schema::hasColumn('kantine_seasons', 'allergie_hinweis')) {
                $table->text('allergie_hinweis')->nullable()->after('daten_selbst');
            }
        });
    }

    public function down(): void
    {
        Schema::table('kantine_seasons', function (Blueprint $table) {
            if (Schema::hasColumn('kantine_seasons', 'allergie_hinweis')) {
                $table->dropColumn('allergie_hinweis');
            }
        });
    }
};
