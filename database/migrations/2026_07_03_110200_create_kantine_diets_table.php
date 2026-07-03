<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Diäten / Ernährungsformen, für die ein Gericht geeignet sein kann.
 * Ein paar gängige direkt vorbefüllt (admin-erweiterbar später).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kantine_diets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        $diaeten = [
            'vegetarisch',
            'vegan',
            'halal',
            'schweinefleischfrei',
            'glutenfrei',
            'laktosefrei',
        ];

        DB::table('kantine_diets')->insert(
            array_map(fn ($name) => ['name' => $name], $diaeten)
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('kantine_diets');
    }
};
