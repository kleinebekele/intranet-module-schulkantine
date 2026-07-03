<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * n:m-Verknüpfungen: welches Gericht enthält welche Allergene / Zusatzstoffe
 * und ist für welche Diäten geeignet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kantine_dish_allergen', function (Blueprint $table) {
            $table->foreignId('dish_id')->constrained('kantine_dishes')->cascadeOnDelete();
            $table->foreignId('allergen_id')->constrained('kantine_allergens')->cascadeOnDelete();
            $table->primary(['dish_id', 'allergen_id']);
        });

        Schema::create('kantine_dish_additive', function (Blueprint $table) {
            $table->foreignId('dish_id')->constrained('kantine_dishes')->cascadeOnDelete();
            $table->foreignId('additive_id')->constrained('kantine_additives')->cascadeOnDelete();
            $table->primary(['dish_id', 'additive_id']);
        });

        Schema::create('kantine_dish_diet', function (Blueprint $table) {
            $table->foreignId('dish_id')->constrained('kantine_dishes')->cascadeOnDelete();
            $table->foreignId('diet_id')->constrained('kantine_diets')->cascadeOnDelete();
            $table->primary(['dish_id', 'diet_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kantine_dish_diet');
        Schema::dropIfExists('kantine_dish_additive');
        Schema::dropIfExists('kantine_dish_allergen');
    }
};
