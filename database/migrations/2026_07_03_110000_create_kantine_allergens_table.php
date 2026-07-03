<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Die 14 EU-Allergene (LMIV Anhang II). Werden gleich mit angelegt – sie sind
 * gesetzlich fix, das muss niemand abtippen. Kennung A–N wie in vielen Kantinen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kantine_allergens', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10);
            $table->string('name');
        });

        $allergene = [
            ['A', 'Glutenhaltiges Getreide'],
            ['B', 'Krebstiere'],
            ['C', 'Eier'],
            ['D', 'Fisch'],
            ['E', 'Erdnüsse'],
            ['F', 'Sojabohnen'],
            ['G', 'Milch / Laktose'],
            ['H', 'Schalenfrüchte (Nüsse)'],
            ['I', 'Sellerie'],
            ['J', 'Senf'],
            ['K', 'Sesam'],
            ['L', 'Schwefeldioxid / Sulfite'],
            ['M', 'Lupinen'],
            ['N', 'Weichtiere'],
        ];

        DB::table('kantine_allergens')->insert(
            array_map(fn ($a) => ['code' => $a[0], 'name' => $a[1]], $allergene)
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('kantine_allergens');
    }
};
