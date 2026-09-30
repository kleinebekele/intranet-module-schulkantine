<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laufende Essensverträge aus Linear (MgVert) – nachts vom Linear-Import
 * (Modul Verwaltung) vollständig ersetzt. Grundlage der Linear-Vorschau
 * (Vertragsnehmer + Vertragsnummer je Esser).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('kantine_linear_contracts')) {
            return;
        }
        Schema::create('kantine_linear_contracts', function (Blueprint $table) {
            $table->id();
            $table->string('esser_adrnr', 20)->index();   // MgVert.AbwAdrNr = users.externe_id
            $table->string('adrnr', 20);                  // Vertragsnehmer (z. B. Elternteil)
            $table->string('vertrag_nr', 30)->nullable();
            $table->unsignedSmallInteger('art');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kantine_linear_contracts');
    }
};
