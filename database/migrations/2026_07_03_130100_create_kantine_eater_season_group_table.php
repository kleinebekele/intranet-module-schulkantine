<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gruppen-Zugehörigkeit je Saison: Ein Kind gehört PRO Saison zu genau einer
 * Kundengruppe (z. B. dieses Jahr OGS, nächstes Jahr Schule ab Klasse 5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kantine_eater_season_group', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eater_id')->constrained('kantine_eaters')->cascadeOnDelete();
            $table->foreignId('season_id')->constrained('kantine_seasons')->cascadeOnDelete();
            $table->foreignId('customer_group_id')->constrained('kantine_customer_groups')->cascadeOnDelete();
            $table->unique(['eater_id', 'season_id']); // genau eine Gruppe je Esser & Saison
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kantine_eater_season_group');
    }
};
