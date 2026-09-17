<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verlorener Schul-Chip: wie eine Rückgabe INAKTIV (returned_at gesetzt), aber
 * OHNE Pfand-Rückerstattung. lost_at markiert genau das – die Abrechnung lässt
 * solche Chips bei der Rückgabe (−Pfand) aus.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('kantine_nfc_chips', 'lost_at')) {
            Schema::table('kantine_nfc_chips', function (Blueprint $table): void {
                $table->date('lost_at')->nullable()->after('returned_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('kantine_nfc_chips', 'lost_at')) {
            Schema::table('kantine_nfc_chips', function (Blueprint $table): void {
                $table->dropColumn('lost_at');
            });
        }
    }
};
