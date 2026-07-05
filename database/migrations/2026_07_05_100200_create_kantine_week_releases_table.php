<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Manuelle Übersteuerung der Wochen-Freigabe (HYBRID).
 *
 * Grundregel ist automatisch: Eine Woche ist zum Bestellen freigegeben, sobald
 * sie in den Vorlauf (settings.release_lead_weeks) fällt. Ein Datensatz hier
 * existiert NUR, wenn der Admin diese Automatik für eine bestimmte Woche
 * übersteuert:
 *   state = 'released'  → früher freigeben (obwohl noch außerhalb des Vorlaufs)
 *   state = 'held'      → zurückhalten (obwohl schon im Vorlauf)
 * Kein Datensatz = Automatik gilt.
 *
 * `week_start` ist immer der Montag der betroffenen Woche (Granularität = Woche).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kantine_week_releases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained('kantine_seasons')->cascadeOnDelete();
            $table->date('week_start');
            $table->string('state'); // released | held
            $table->timestamps();

            $table->unique(['season_id', 'week_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kantine_week_releases');
    }
};
