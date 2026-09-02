<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menü-Vorlagen (Ersatz für die früheren Sparmenüs). Eine Vorlage beschreibt EIN
 * Menü einer Saison: Name, Preis, an welchen Wochentagen es verfügbar ist und –
 * über die Slots – aus welcher Kategorie WIE VIELE Gerichte es enthält.
 *
 * WELCHE Gerichte konkret enthalten sind, steht NICHT hier, sondern wird je
 * Öffnungstag im Speiseplan gefüllt (folgt). So entfällt das Anlegen jeder
 * einzelnen Kombination – das Menü ist die Hülle, der Tag füllt sie.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kantine_menu_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained('kantine_seasons')->cascadeOnDelete();
            $table->string('name');
            $table->decimal('price', 8, 2)->default(0);
            // ISO-Wochentage (1=Mo … 7=So), an denen das Menü angeboten wird.
            // Leer/NULL = an allen Öffnungstagen der Saison.
            $table->json('weekdays')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('kantine_menu_template_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_template_id')->constrained('kantine_menu_templates')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('kantine_categories')->cascadeOnDelete();
            // Wie viele Gerichte dieser Kategorie das Menü enthält (z. B. 1 Hauptspeise).
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedInteger('sort_order')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kantine_menu_template_slots');
        Schema::dropIfExists('kantine_menu_templates');
    }
};
