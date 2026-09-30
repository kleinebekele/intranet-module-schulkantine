<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Preis je Vertragsart am Gericht ({"27": 3.5, …}). Fehlt eine Art, gilt der
 * Hauptpreis (`price`).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('kantine_dishes', 'contract_prices')) {
            Schema::table('kantine_dishes', function (Blueprint $table) {
                $table->json('contract_prices')->nullable()->after('price');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('kantine_dishes', 'contract_prices')) {
            Schema::table('kantine_dishes', function (Blueprint $table) {
                $table->dropColumn('contract_prices');
            });
        }
    }
};
