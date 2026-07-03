<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sonderkost je Benutzer: hinterlegte Allergien (darf Allergen X nicht enthalten)
 * und Diäten (muss geeignet sein). Grundlage für die Warnungen beim Bestellen.
 *
 * Jeder Esser ist ein Benutzer – die Sonderkost hängt daher an `user_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kantine_user_allergen', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('allergen_id')->constrained('kantine_allergens')->cascadeOnDelete();
            $table->primary(['user_id', 'allergen_id']);
        });

        Schema::create('kantine_user_diet', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('diet_id')->constrained('kantine_diets')->cascadeOnDelete();
            $table->primary(['user_id', 'diet_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kantine_user_diet');
        Schema::dropIfExists('kantine_user_allergen');
    }
};
