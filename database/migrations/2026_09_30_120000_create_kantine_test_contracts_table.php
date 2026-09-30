<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Übergangslösung zum Testen: simulierter Essensvertrag (Vertragsart wie in
 * Linear) für Konten, die NICHT aus Linear stammen (`users.externe_id` leer).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('kantine_test_contracts')) {
            return;
        }
        Schema::create('kantine_test_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('art');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kantine_test_contracts');
    }
};
