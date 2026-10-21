<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pro Saison: Zeigt das Bestell-Terminal Preise und Kosten – getrennt für Kinder
 * (über die Eltern abgerechnet) und alle anderen. Default an = bisheriges Verhalten.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kantine_seasons', function (Blueprint $table) {
            if (! Schema::hasColumn('kantine_seasons', 'kosten_kinder')) {
                $table->boolean('kosten_kinder')->default(true)->after('budget_aktiv');
            }
            if (! Schema::hasColumn('kantine_seasons', 'kosten_andere')) {
                $table->boolean('kosten_andere')->default(true)->after('kosten_kinder');
            }
        });
    }

    public function down(): void
    {
        Schema::table('kantine_seasons', function (Blueprint $table) {
            foreach (['kosten_kinder', 'kosten_andere'] as $spalte) {
                if (Schema::hasColumn('kantine_seasons', $spalte)) {
                    $table->dropColumn($spalte);
                }
            }
        });
    }
};
