<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kategorien (Hauptmenü, Nachtisch, Getränk, Eis …). Ein Gericht gehört zu
 * genau einer Kategorie. Die Kategorie steuert:
 *  - ob eine spontane Abholung erlaubt ist (allows_walkin)
 *  - die Reihenfolge im Speiseplan (sort_order)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kantine_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('allows_walkin')->default(false); // spontane Abholung möglich?
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kantine_categories');
    }
};
