<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Speiseplan: welche Gerichte werden an einem Öffnungstag angeboten (das
 * Tagesangebot). Es gibt genau EINE Spur – dasselbe Angebot für alle:
 *  - Menü-Gruppen (Schüler/Sonstige) wählen einzelne Gerichte daraus.
 *  - OGS isst „buffet-mäßig" mit (nur ja/nein), ohne eigenen Eintrag.
 *
 * Das Datum muss ein Öffnungstag der Saison sein (Season::isOpenOn) – das prüft
 * der Controller, nicht die DB. sort_order steuert die Reihenfolge.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kantine_menus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained('kantine_seasons')->cascadeOnDelete();
            $table->date('date');
            $table->foreignId('dish_id')->constrained('kantine_dishes')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            // Ein Gericht steht pro Tag nur einmal im Angebot.
            $table->unique(['season_id', 'date', 'dish_id']);
            $table->index(['season_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kantine_menus');
    }
};
