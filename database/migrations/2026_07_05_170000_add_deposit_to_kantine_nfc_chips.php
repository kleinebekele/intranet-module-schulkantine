<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ergänzt die NFC-Chips um Pfand & Herkunft:
 *  - source   'eltern' (eigener Chip, kein Pfand) oder 'schule' (Schul-Chip).
 *  - deposit  Pfandbetrag (Schul-Chip: 5,00 €). Fließt später in die Abrechnung.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kantine_nfc_chips', function (Blueprint $table) {
            $table->string('source')->nullable()->after('user_id');
            $table->decimal('deposit', 8, 2)->default(0)->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('kantine_nfc_chips', function (Blueprint $table) {
            $table->dropColumn(['source', 'deposit']);
        });
    }
};
