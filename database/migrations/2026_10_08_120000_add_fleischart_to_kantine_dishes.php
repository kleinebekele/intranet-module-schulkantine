<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fleischart am Gericht (Symbol im Speiseplan, wie früher am Menü&Serve-Terminal):
 * vegan, vegetarisch, fisch, gefluegel, rind, schwein, rind_schwein, lamm, fleisch.
 *
 * Bestehende Gerichte bekommen sie aus „nicht geeignet für" nachgetragen, soweit das
 * eindeutig ist (Rind/Geflügel/Lamm/Fisch sind daraus nicht unterscheidbar → „fleisch").
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('kantine_dishes', 'fleischart')) {
            Schema::table('kantine_dishes', function (Blueprint $table) {
                $table->string('fleischart', 20)->nullable()->after('description');
            });
        }

        $nichtFuer = DB::table('kantine_dish_diet')
            ->join('kantine_diets', 'kantine_diets.id', '=', 'kantine_dish_diet.diet_id')
            ->get(['kantine_dish_diet.dish_id', 'kantine_diets.name'])
            ->groupBy('dish_id')
            ->map(fn ($zeilen) => $zeilen->pluck('name')->map(fn ($n) => mb_strtolower($n))->all());

        foreach ($nichtFuer as $dishId => $namen) {
            $art = match (true) {
                in_array('schweinefleischfrei', $namen) => 'schwein',
                // Fisch ließe sich nur über ein fehlendes „halal" erkennen – das kreuzt
                // bei Hand angelegten Fleischgerichten aber kaum jemand an.
                in_array('vegetarisch', $namen) => 'fleisch',
                in_array('vegan', $namen) => 'vegetarisch',
                default => null,
            };
            if ($art) {
                DB::table('kantine_dishes')->where('id', $dishId)->whereNull('fleischart')->update(['fleischart' => $art]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('kantine_dishes', 'fleischart')) {
            Schema::table('kantine_dishes', function (Blueprint $table) {
                $table->dropColumn('fleischart');
            });
        }
    }
};
