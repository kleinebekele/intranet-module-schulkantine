<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ausgaben (servings) – die TATSÄCHLICHE Essensausgabe vor Ort (Phase 4).
 *
 * Quelle der Wahrheit „wer hat was bekommen". Bewusst getrennt von den
 * Vorbestellungen (kantine_orders):
 *  - Eine Ausgabe-Zeile entsteht erst, wenn beim Abhaken tatsächlich ausgegeben
 *    wird. Existiert keine Zeile, wurde (noch) nicht ausgegeben → No-Show.
 *  - Vorbestellte Ausgabe: order_id gesetzt, spontaneous = false.
 *  - Spontane Abholung: order_id null, spontaneous = true (Kind ohne/nach Frist).
 *
 * Die Ausgabe ist NICHT die Abrechnungsbasis (das sind die verbindlichen
 * Bestellungen) – sie dient dem Betrieb und macht No-Shows sichtbar. Der
 * Preis-Snapshot wird dennoch festgehalten (nützlich für spontane Abholungen,
 * die es sonst nirgends gibt – Feinschliff der Abrechnung folgt in Phase 5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kantine_servings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained('kantine_seasons')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); // der Esser
            $table->date('date');

            // Verweis auf die erfüllte Vorbestellung (null = spontane Abholung).
            $table->foreignId('order_id')->nullable()->constrained('kantine_orders')->nullOnDelete();

            // Was wurde ausgegeben. Bei OGS (ja/nein) bleibt beides null.
            $table->foreignId('dish_id')->nullable()->constrained('kantine_dishes')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('kantine_categories')->nullOnDelete();

            $table->boolean('spontaneous')->default(false);
            $table->decimal('price_snapshot', 8, 2)->nullable();

            // Wer hat abgehakt (Ausgabepersonal). created_at = Ausgabe-Zeitpunkt.
            $table->foreignId('served_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['season_id', 'date']);
            $table->index(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kantine_servings');
    }
};
