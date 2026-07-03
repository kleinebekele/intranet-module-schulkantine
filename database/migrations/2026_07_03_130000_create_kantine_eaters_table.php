<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Esser / Teilnehmer – wer isst und abgerechnet wird. Kann optional an einen
 * Intranet-User gekoppelt sein (eigenes Login, z. B. größere Kinder/Personal);
 * kleinere Kinder haben keins und werden über Vormunde (Eltern) bedient.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kantine_eaters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // eigenes Login (optional)
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kantine_eaters');
    }
};
