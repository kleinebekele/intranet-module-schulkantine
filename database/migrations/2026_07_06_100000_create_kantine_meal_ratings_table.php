<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bewertungen (Daumen hoch/runter) – Phase 6 (Feedback).
 *
 * Ein Esser bewertet ein Essen, das er TATSÄCHLICH bekommen hat: es hängt an
 * genau einer Ausgabe (kantine_servings) mit echtem Gericht. No-Shows
 * (declined) und OGS-Ausgaben ohne Gericht (dish_id = null) sind nicht
 * bewertbar – deshalb der eindeutige Bezug über serving_id.
 *
 * Datenschutz: Für das Personal wird NUR aggregiert je Gericht ausgewertet
 * (Anzahl 👍/👎). Die Einzelzeile speichert user_id nur, um Doppel-/Änderungs-
 * stimmen zu verhindern (jederzeit änderbar) – sie wird nie personenbezogen
 * angezeigt.
 *
 *   rating: 1 = Daumen runter   |   2 = Daumen hoch
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kantine_meal_ratings', function (Blueprint $table) {
            $table->id();

            // Genau eine Bewertung je Ausgabe (unique). Fällt die Ausgabe weg,
            // fällt auch die Bewertung weg.
            $table->foreignId('serving_id')->unique()->constrained('kantine_servings')->cascadeOnDelete();

            // Der Esser (nur zum Verhindern von Doppelstimmen – nie angezeigt).
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Denormalisiert aus der Ausgabe, damit der aggregierte Report je
            // Gericht/Zeitraum ohne teure Joins läuft.
            $table->foreignId('dish_id')->nullable()->constrained('kantine_dishes')->nullOnDelete();
            $table->date('date');

            $table->unsignedSmallInteger('rating'); // 1 = 👎 | 2 = 👍

            $table->timestamps();

            $table->index(['dish_id', 'date']);
            $table->index(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kantine_meal_ratings');
    }
};
