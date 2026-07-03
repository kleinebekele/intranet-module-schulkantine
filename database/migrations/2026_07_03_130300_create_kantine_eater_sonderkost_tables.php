<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sonderkost je Esser: hinterlegte Allergien (darf Allergen X nicht enthalten)
 * und Diäten (muss geeignet sein). Grundlage für die Warnungen beim Bestellen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kantine_eater_allergen', function (Blueprint $table) {
            $table->foreignId('eater_id')->constrained('kantine_eaters')->cascadeOnDelete();
            $table->foreignId('allergen_id')->constrained('kantine_allergens')->cascadeOnDelete();
            $table->primary(['eater_id', 'allergen_id']);
        });

        Schema::create('kantine_eater_diet', function (Blueprint $table) {
            $table->foreignId('eater_id')->constrained('kantine_eaters')->cascadeOnDelete();
            $table->foreignId('diet_id')->constrained('kantine_diets')->cascadeOnDelete();
            $table->primary(['eater_id', 'diet_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kantine_eater_diet');
        Schema::dropIfExists('kantine_eater_allergen');
    }
};
