<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kategorie-Freigaben je Kind (von den Eltern gesetzt). Standard ohne Zeile =
 * ALLES erlaubt; eine Zeile hält nur die Einschränkungen fest:
 *  - may_preorder  darf das Kind Gerichte dieser Kategorie VORbestellen?
 *  - may_walkin    darf das Kind diese Kategorie spontan am Tresen kaufen?
 *
 * (Das Wochenbudget für Spontankäufe liegt weiter in kantine_budgets.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kantine_child_category_perms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('kantine_categories')->cascadeOnDelete();
            $table->boolean('may_preorder')->default(true);
            $table->boolean('may_walkin')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kantine_child_category_perms');
    }
};
