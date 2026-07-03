<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vormundschaft: welcher Intranet-User (Elternteil) darf für welchen Esser
 * (Kind) bestellen. n:m – ein Elternteil kann mehrere Kinder haben, ein Kind
 * mehrere Vormunde.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kantine_guardianships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();          // Vormund / Elternteil
            $table->foreignId('eater_id')->constrained('kantine_eaters')->cascadeOnDelete(); // Kind
            $table->unique(['user_id', 'eater_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kantine_guardianships');
    }
};
