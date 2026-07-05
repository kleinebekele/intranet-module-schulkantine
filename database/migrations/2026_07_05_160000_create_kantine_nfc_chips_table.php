<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NFC-Chips der Esser (Phase 4 – Ausgabe per Chip). Ordnet die eindeutige
 * Chip-Kennung (UID, normalisiert) einem Benutzer zu.
 *
 *  - uid unique      → ein Chip zeigt auf genau einen Esser.
 *  - user_id unique  → ein Esser hat genau einen (aktiven) Chip. Verliert er ihn,
 *                      wird beim Neuzuweisen der Eintrag ersetzt, die alte UID frei.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kantine_nfc_chips', function (Blueprint $table) {
            $table->id();
            $table->string('uid')->unique();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kantine_nfc_chips');
    }
};
