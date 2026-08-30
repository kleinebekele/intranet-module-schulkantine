<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OGS-Standardtage: An welchen Wochentagen isst das Kind im Rahmen des Abos?
 * ISO-Wochentage (1 = Montag … 7 = Sonntag) als JSON-Liste. NULL/leer = alle
 * Öffnungstage (bisheriges Verhalten – bestehende Abos bleiben unverändert).
 * Einzelne An-/Abmeldungen je Tag überschreiben das Muster weiterhin.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('kantine_subscriptions', 'weekdays')) {
            return;
        }
        Schema::table('kantine_subscriptions', function (Blueprint $table) {
            $table->json('weekdays')->nullable()->after('active');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('kantine_subscriptions', 'weekdays')) {
            return;
        }
        Schema::table('kantine_subscriptions', function (Blueprint $table) {
            $table->dropColumn('weekdays');
        });
    }
};
