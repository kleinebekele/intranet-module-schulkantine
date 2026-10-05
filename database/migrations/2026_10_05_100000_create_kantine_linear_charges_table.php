<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Essens-Forderungen aus Linear (MgSolln) ab 2026 – nachts vom Linear-Import
 * (Modul Verwaltung) vollständig ersetzt. Zeigt Eltern unter „Meine Abrechnung"
 * alle abgerechneten Monate samt bezahlt/offen, auch die aus Menü&Serve.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('kantine_linear_charges')) {
            Schema::create('kantine_linear_charges', function (Blueprint $table) {
                $table->id();
                $table->string('adrnr', 20);                 // Vertragsnehmer
                $table->string('esser_adrnr', 20)->index();  // Esser = users.externe_id
                $table->unsignedSmallInteger('art');
                $table->string('vertrag_nr', 30)->nullable();
                $table->unsignedSmallInteger('jahr');
                $table->unsignedTinyInteger('monat');
                $table->decimal('betrag', 10, 2);
                $table->decimal('bezahlt', 10, 2)->default(0);
                $table->decimal('offen', 10, 2)->default(0);
                $table->index('adrnr');
            });
        }
        if (! Schema::hasColumn('kantine_settings', 'linear_charges_at')) {
            Schema::table('kantine_settings', function (Blueprint $table) {
                $table->timestamp('linear_charges_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kantine_linear_charges');
        if (Schema::hasColumn('kantine_settings', 'linear_charges_at')) {
            Schema::table('kantine_settings', function (Blueprint $table) {
                $table->dropColumn('linear_charges_at');
            });
        }
    }
};
