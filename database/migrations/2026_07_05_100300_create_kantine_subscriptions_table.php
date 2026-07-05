<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OGS-Saison-Abo (Dauerbestellung). ABGELEITET, nicht materialisiert:
 * Ein Datensatz je Esser/Saison bedeutet „isst standardmäßig an ALLEN
 * Öffnungstagen". Die tatsächlichen Tages-Teilnahmen werden NICHT vorab als
 * Zeilen erzeugt – sie ergeben sich aus:
 *
 *     teilnahme(tag) = Abo vorhanden  UND  keine Abbestellung (kantine_orders
 *                      status=storniert) für diesen Tag
 *
 * Eltern verwalten also nur Abbestellungen (Krankheit/Urlaub). Gilt für
 * ja/nein-Gruppen (OGS).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kantine_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained('kantine_seasons')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['season_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kantine_subscriptions');
    }
};
