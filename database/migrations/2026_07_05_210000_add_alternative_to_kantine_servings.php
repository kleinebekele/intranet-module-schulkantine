<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dritter Ausgabe-Zustand „Alternative gewählt":
 *  - alternative = true → das Kind hat statt des bestellten Gerichts eine
 *    Alternative genommen. Es wird WEITER wie bestellt berechnet, bekommt aber
 *    auf der Abrechnung das Tag „alternatives Gericht bevorzugt".
 *
 * Zustände je Ausgabe-Zeile (sich gegenseitig ausschließend):
 *  - genommen:          declined = false, alternative = false
 *  - Alternative:       declined = false, alternative = true
 *  - nicht genommen:    declined = true,  alternative = false (+ decline_reason)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kantine_servings', function (Blueprint $table) {
            $table->boolean('alternative')->default(false)->after('decline_reason');
        });
    }

    public function down(): void
    {
        Schema::table('kantine_servings', function (Blueprint $table) {
            $table->dropColumn('alternative');
        });
    }
};
