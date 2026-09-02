<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bestellungen können jetzt zu einem Menü gehören. Ein Menü wird beim Bestellen
 * in seine Bestandteil-Gerichte zerlegt: je Slot eine normale Menü-Bestellung
 * (mit Kategorie + Gericht), alle mit derselben menu_day_id gruppiert. Der
 * Menü-Festpreis wird auf die Gerichte verteilt (Summe = Menü-Preis).
 *
 * Vorteil: Küche/Ausgabe/Abrechnung/Allergene behandeln diese Zeilen wie normale
 * Gericht-Bestellungen – es sind ganz normale Vorbestellungen. menu_day_id dient
 * nur der Gruppierung (Anzeige, Ab-/Bestellen als Ganzes).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kantine_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('kantine_orders', 'menu_day_id')) {
                $table->foreignId('menu_day_id')->nullable()->after('menu_id')
                    ->constrained('kantine_menu_days')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('kantine_orders', function (Blueprint $table) {
            if (Schema::hasColumn('kantine_orders', 'menu_day_id')) {
                $table->dropConstrainedForeignId('menu_day_id');
            }
        });
    }
};
