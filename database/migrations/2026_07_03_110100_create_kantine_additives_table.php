<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Zusatzstoffe (kennzeichnungspflichtig). Gängige Liste 1–13 direkt vorbefüllt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kantine_additives', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10);
            $table->string('name');
        });

        $zusatzstoffe = [
            ['1', 'mit Farbstoff'],
            ['2', 'mit Konservierungsstoff'],
            ['3', 'mit Antioxidationsmittel'],
            ['4', 'mit Geschmacksverstärker'],
            ['5', 'geschwefelt'],
            ['6', 'geschwärzt'],
            ['7', 'gewachst'],
            ['8', 'mit Phosphat'],
            ['9', 'mit Süßungsmitteln'],
            ['10', 'enthält eine Phenylalaninquelle'],
            ['11', 'koffeinhaltig'],
            ['12', 'chininhaltig'],
            ['13', 'mit Nitritpökelsalz'],
        ];

        DB::table('kantine_additives')->insert(
            array_map(fn ($z) => ['code' => $z[0], 'name' => $z[1]], $zusatzstoffe)
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('kantine_additives');
    }
};
