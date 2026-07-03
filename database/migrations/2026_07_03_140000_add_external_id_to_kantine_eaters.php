<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Externe ID am Teilnehmer – Schlüssel für den CSV-Import (z. B. Schüler-Nr.
 * aus dem Schulverwaltungssystem). Macht den Import idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kantine_eaters', function (Blueprint $table) {
            $table->string('external_id')->nullable()->after('id');
        });

        // Getrennte Anweisung (SQLite erlaubt kein UNIQUE beim ADD COLUMN direkt).
        Schema::table('kantine_eaters', function (Blueprint $table) {
            $table->unique('external_id');
        });
    }

    public function down(): void
    {
        Schema::table('kantine_eaters', function (Blueprint $table) {
            $table->dropUnique(['external_id']);
            $table->dropColumn('external_id');
        });
    }
};
