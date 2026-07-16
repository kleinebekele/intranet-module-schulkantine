<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `label` = freie Bezeichnung einer Ausgabe-Zeile ohne Gericht.
 *
 * Gebraucht für den „Nachschlag" am Ausgabe-Terminal: eine spontane Zeile
 * (spontaneous = true) mit Betrag, aber ohne dish_id/category_id. Damit der Posten
 * in der Abrechnung nicht als „—" erscheint, sondern als „Nachschlag", trägt er
 * hier seinen Namen. Reguläre Ausgabe- und Walk-in-Zeilen lassen das Feld null
 * (ihr Name kommt vom Gericht).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kantine_servings', function (Blueprint $table) {
            $table->string('label')->nullable()->after('category_id');
        });
    }

    public function down(): void
    {
        Schema::table('kantine_servings', function (Blueprint $table) {
            $table->dropColumn('label');
        });
    }
};
