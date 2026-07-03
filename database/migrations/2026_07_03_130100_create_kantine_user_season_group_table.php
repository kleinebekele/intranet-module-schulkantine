<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gruppen-Zugehörigkeit je Saison: Ein Benutzer gehört PRO Saison zu genau einer
 * Kundengruppe (z. B. dieses Jahr OGS, nächstes Jahr Schule ab Klasse 5).
 *
 * Jeder Esser IST ein Benutzer (eigener Account) – deshalb hängt die Zuordnung
 * direkt an `user_id`. Diese Pivot-Tabelle ist die „Wahrheit" der Gruppen-
 * zuordnung; eine evtl. Rolle→Gruppe-Automatik befüllt sie nur mit Vorgaben.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kantine_user_season_group', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('season_id')->constrained('kantine_seasons')->cascadeOnDelete();
            $table->foreignId('customer_group_id')->constrained('kantine_customer_groups')->cascadeOnDelete();
            $table->unique(['user_id', 'season_id']); // genau eine Gruppe je Benutzer & Saison
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kantine_user_season_group');
    }
};
