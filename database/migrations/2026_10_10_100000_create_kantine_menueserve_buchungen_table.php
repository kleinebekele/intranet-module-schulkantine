<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Welche Menü&Serve-Buchungen (BOOBAS) schon als Bestellung übernommen wurden –
 * damit ein zweiter Lauf nichts doppelt anlegt und eine bei uns später
 * abbestellte Bestellung nicht wieder auftaucht.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('kantine_menueserve_buchungen')) {
            return;
        }
        Schema::create('kantine_menueserve_buchungen', function (Blueprint $table) {
            $table->id();
            $table->string('boobas_id', 36)->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('datum');
            $table->foreignId('uebernommen_von')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kantine_menueserve_buchungen');
    }
};
