<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OGS-Fixpreis auf Saison-Ebene. OGS-Kinder wählen keine Gerichte, sondern
 * essen pauschal – ihr Essen kostet je Saison einen einheitlichen Fixpreis
 * (global für alle OGS-Kinder). Grundlage für die OGS-Abrechnung (Phase 5).
 * Nullable: darf zu Saisonbeginn noch offen sein.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kantine_seasons', function (Blueprint $table) {
            $table->decimal('ogs_price', 8, 2)->nullable()->after('bundesland');
        });
    }

    public function down(): void
    {
        Schema::table('kantine_seasons', function (Blueprint $table) {
            $table->dropColumn('ogs_price');
        });
    }
};
