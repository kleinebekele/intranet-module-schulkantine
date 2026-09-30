<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menü-Vorlage: Preis aus Linear je Vertragsgruppe statt Festpreis
 * (siehe Support\LinearPreise).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('kantine_menu_templates', 'linear_price')) {
            return;
        }
        Schema::table('kantine_menu_templates', function (Blueprint $table) {
            $table->boolean('linear_price')->default(false)->after('price');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('kantine_menu_templates', 'linear_price')) {
            Schema::table('kantine_menu_templates', function (Blueprint $table) {
                $table->dropColumn('linear_price');
            });
        }
    }
};
