<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Materialisierte Menüs je Öffnungstag (Ergebnis des „Push"). Eine Menü-Vorlage
 * (kantine_menu_templates) wird beim Ausrollen für jeden passenden, nicht
 * freigegebenen Öffnungstag zu einem konkreten Menü-Tag – mit Snapshot von Name
 * und Preis, damit spätere Vorlagen-Änderungen bereits ausgerollte Tage nicht
 * rückwirkend verändern.
 *
 * Die Slots (kantine_menu_day_slots) sind die einzeln füllbaren Plätze: je
 * Kategorie-Slot der Vorlage `quantity` Plätze. Welches Gericht drinsteckt, wählt
 * die Küche im Speiseplan (dish_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kantine_menu_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained('kantine_seasons')->cascadeOnDelete();
            // Quell-Vorlage; bleibt erhalten (NULL), falls die Vorlage später gelöscht wird.
            $table->foreignId('menu_template_id')->nullable()->constrained('kantine_menu_templates')->nullOnDelete();
            $table->date('date');
            $table->string('name');            // Snapshot der Vorlage zum Push-Zeitpunkt
            $table->decimal('price', 8, 2)->default(0);
            $table->timestamps();

            // Je Tag höchstens einmal dieselbe Vorlage.
            $table->unique(['season_id', 'date', 'menu_template_id']);
            $table->index('date');
        });

        Schema::create('kantine_menu_day_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_day_id')->constrained('kantine_menu_days')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('kantine_categories')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            // Das gewählte Gericht (leer = noch zu füllen).
            $table->foreignId('dish_id')->nullable()->constrained('kantine_dishes')->nullOnDelete();

            $table->index('menu_day_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kantine_menu_day_slots');
        Schema::dropIfExists('kantine_menu_days');
    }
};
