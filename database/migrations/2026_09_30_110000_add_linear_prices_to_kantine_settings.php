<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Essenspreise je Vertragsgruppe, nachts vom Linear-Import (Modul Verwaltung)
 * abgelegt, und der Schalter „OGS-Preis aus Linear" an der Saison.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('kantine_settings', 'linear_prices')) {
            Schema::table('kantine_settings', function (Blueprint $table) {
                $table->json('linear_prices')->nullable();
                $table->timestamp('linear_prices_at')->nullable();
            });
        }
        if (! Schema::hasColumn('kantine_seasons', 'ogs_linear_price')) {
            Schema::table('kantine_seasons', function (Blueprint $table) {
                $table->boolean('ogs_linear_price')->default(true)->after('ogs_price');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('kantine_settings', 'linear_prices')) {
            Schema::table('kantine_settings', function (Blueprint $table) {
                $table->dropColumn(['linear_prices', 'linear_prices_at']);
            });
        }
        if (Schema::hasColumn('kantine_seasons', 'ogs_linear_price')) {
            Schema::table('kantine_seasons', function (Blueprint $table) {
                $table->dropColumn('ogs_linear_price');
            });
        }
    }
};
