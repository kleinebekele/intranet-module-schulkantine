<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Die "Saison" (Schuljahr) ist der oberste Container: Kalender, Gruppen-
 * Zuordnung der Kinder und Bestellungen hängen alle darunter.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kantine_seasons', function (Blueprint $table) {
            $table->id();
            $table->string('name');                          // z. B. "Schuljahr 2026/2027"
            $table->date('start_date');
            $table->date('end_date');
            $table->string('bundesland', 10)->nullable();    // Code für den Ferien-/Feiertags-Import, z. B. "DE-NW"
            $table->json('opening_weekdays')->nullable();     // Öffnungs-Wochentage (ISO: 1=Mo … 7=So), z. B. [1,2,3,4]
            $table->boolean('is_active')->default(false);     // die aktuell aktive Saison (für Bestellung/Anzeige)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kantine_seasons');
    }
};
