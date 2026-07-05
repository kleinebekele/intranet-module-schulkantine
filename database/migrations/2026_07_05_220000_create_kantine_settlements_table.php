<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Abrechnung (settlements) – der Bezahlt-Status je Person & Monat (Phase 5).
 *
 * Das Modul rechnet NICHT selbst ab (keine Zahlungsanbieter). Die eigentliche
 * Abrechnung passiert extern; hier wird nur festgehalten, dass der Monatsbetrag
 * einer Person als bezahlt markiert wurde – als Häkchen für die Verwaltung.
 *
 *  - Ein Eintrag existiert erst, wenn „als bezahlt" markiert wird
 *    (kein Eintrag = offen).
 *  - amount = der berechnete Monatsbetrag ZUM ZEITPUNKT der Markierung
 *    (Snapshot fürs Nachvollziehen; die laufende Auswertung rechnet live).
 *  - Zurücknehmen löscht den Eintrag wieder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kantine_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained('kantine_seasons')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); // der Esser
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month'); // 1–12
            $table->decimal('amount', 10, 2)->default(0); // Betrag zum Zeitpunkt der Markierung
            $table->timestamp('paid_at');
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Genau ein Bezahlt-Status je (Person, Monat).
            $table->unique(['user_id', 'year', 'month']);
            $table->index(['season_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kantine_settlements');
    }
};
