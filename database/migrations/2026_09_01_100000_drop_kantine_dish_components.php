<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sparmenüs (Bündel-Gerichte) werden aus dem Modul entfernt. Diese Migration
 * baut die zugehörige Datenbasis ab:
 *
 *  1. Übrig gebliebene Sparmenü-Gerichte werden deaktiviert (is_active = false),
 *     damit sie nicht als normales – dann allergenfreies! – Gericht neu bestellt
 *     werden. In bereits erfassten Bestellungen bleiben sie erhalten.
 *  2. Die Bestandteil-Tabelle wird gelöscht.
 *
 * Nicht angetastet: die Kategorie „Sparmenü" (an ihr können Gerichte/Bestellungen
 * hängen) und bestehende Speiseplan-/Bestell-/Ausgabe-Zeilen. Ein noch auf einem
 * künftigen Speiseplan-Tag liegendes Sparmenü bitte dort von Hand entfernen.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('kantine_dish_components')) {
            DB::table('kantine_dishes')
                ->whereIn('id', fn ($q) => $q->from('kantine_dish_components')->select('bundle_dish_id'))
                ->update(['is_active' => false]);

            Schema::dropIfExists('kantine_dish_components');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('kantine_dish_components')) {
            Schema::create('kantine_dish_components', function (Blueprint $table) {
                $table->foreignId('bundle_dish_id')->constrained('kantine_dishes')->cascadeOnDelete();
                $table->foreignId('part_dish_id')->constrained('kantine_dishes')->cascadeOnDelete();
                $table->unsignedInteger('sort_order')->default(0);
                $table->primary(['bundle_dish_id', 'part_dish_id']);
                $table->index('part_dish_id');
            });
        }
    }
};
