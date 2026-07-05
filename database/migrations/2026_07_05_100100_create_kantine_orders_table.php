<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vorbestellungen. EINE Zeile je gewähltem Gericht (Menü-Modus) bzw. je Tag
 * (OGS ja/nein). Der Esser IST ein Benutzer (`user_id`).
 *
 * Zwei Ausprägungen, gleiche Tabelle:
 *  - Menü-Modus (Schüler/Sonstige): pro Kategorie max. 1 Gericht/Tag. `menu_id`
 *    zeigt auf den Speiseplan-Eintrag (daran hängt der Löschschutz), `dish_id`
 *    und `category_id` sind Snapshots; `price_snapshot` hält den Preis fest,
 *    damit spätere Preisänderungen alte Abrechnungen nicht verändern.
 *  - OGS (ja/nein): `menu_id`/`dish_id`/`category_id`/`price_snapshot` sind NULL;
 *    die Zeile bedeutet nur „isst an diesem Tag" (status=bestellt) bzw. bei
 *    aktivem Abo „isst NICHT" (status=storniert = Abbestellung).
 *
 * Status: `bestellt` | `storniert`. „Verbindlich" wird nicht gespeichert, sondern
 * aus der Abbestell-Frist abgeleitet (Storno ist nur vor der Frist erlaubt →
 * storniert = nie berechnet). Eindeutigkeit (max. 1 aktive Zeile je Esser/Tag/
 * Kategorie) wird in der Service-Schicht erzwungen, nicht per DB-Constraint
 * (sonst kollidieren stornierte Alt-Zeilen).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kantine_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained('kantine_seasons')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');

            // Menü-Modus: Verweise auf den Speiseplan-Eintrag + Snapshots.
            // OGS ja/nein: alle vier NULL.
            $table->foreignId('menu_id')->nullable()->constrained('kantine_menus')->nullOnDelete();
            $table->foreignId('dish_id')->nullable()->constrained('kantine_dishes')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('kantine_categories')->nullOnDelete();
            $table->decimal('price_snapshot', 8, 2)->nullable();

            $table->string('status')->default('bestellt'); // bestellt | storniert
            $table->timestamps();

            $table->index(['season_id', 'date']);
            $table->index(['user_id', 'date']);
            $table->index('menu_id'); // für den Löschschutz im Speiseplan
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kantine_orders');
    }
};
