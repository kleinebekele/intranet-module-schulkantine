<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schließtage einer Saison (Ferien, Feiertage, Sonderfälle). Werden per API
 * gezogen ODER manuell gepflegt – deshalb speichern wir die Quelle mit.
 * Die "Öffnungstage" ergeben sich daraus: Saison-Zeitraum ∩ Öffnungs-
 * Wochentage − Schließtage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kantine_closed_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained('kantine_seasons')->cascadeOnDelete();
            $table->date('date');
            $table->string('reason')->nullable();            // z. B. "Sommerferien", "Tag der Deutschen Einheit"
            $table->string('type')->default('sonstiges');    // ferien | feiertag | sonstiges
            $table->string('source')->default('manuell');    // api | manuell
            $table->timestamps();

            $table->unique(['season_id', 'date']);           // ein Schließtag pro Datum je Saison
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kantine_closed_days');
    }
};
