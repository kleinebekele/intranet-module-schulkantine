<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Globale Einstellungen der Kantine (eine einzige Zeile – Singleton).
 *
 * Enthält die zeitlichen Stellschrauben der Vorbestellung:
 *  - order_deadline_time   Bestellschluss-Uhrzeit am *vorigen Öffnungstag* (Standard 14:00)
 *  - cancel_deadline_time  Abbestell-Uhrzeit am *selben Tag*            (Standard 09:00)
 *  - release_lead_weeks    Wie viele Wochen im Voraus eine Woche automatisch
 *                          zum Bestellen freigegeben wird (Standard 2).
 *
 * Der Vorlauf „voriger Öffnungstag" bleibt vorerst fix (nur die Uhrzeiten sind
 * einstellbar) – so wie im Konzept entschieden.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kantine_settings', function (Blueprint $table) {
            $table->id();
            $table->string('order_deadline_time', 5)->default('14:00');
            $table->string('cancel_deadline_time', 5)->default('09:00');
            $table->unsignedInteger('release_lead_weeks')->default(2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kantine_settings');
    }
};
