<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Intranet\Modules\Schulkantine\Models\Diet;
use Intranet\Modules\Schulkantine\Models\Dish;

/**
 * Diäten der bestehenden Gerichte einmal nach den neuen Regeln setzen: was aus
 * Fleischart und Allergenen folgt (z. B. Schwein → nicht vegetarisch/halal, Milch →
 * nicht vegan/laktosefrei), gilt fest. Alles andere bleibt, wie es war.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('kantine_dishes', 'fleischart')) {
            return;
        }
        $alle = Diet::pluck('id');

        Dish::with(['allergens', 'unsuitableDiets'])->each(function (Dish $dish) use ($alle) {
            $dish->dietenSetzen($alle->diff($dish->unsuitableDiets->pluck('id'))->values()->all());
        });
    }

    public function down(): void
    {
        // Nicht umkehrbar – die vorherige Auswahl ist nicht gesichert.
    }
};
