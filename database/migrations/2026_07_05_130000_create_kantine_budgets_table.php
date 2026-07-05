<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wochenbudget je Schüler (von den Eltern gesetzt). Begrenzt, wie viel ein
 * Schüler pro Woche selbst bestellen darf (z. B. spontan ein Getränk oder Eis).
 *
 *  - week_start = NULL  → allgemeines Budget, gilt für JEDE Woche.
 *  - week_start = Montag → spezielles Budget nur für DIESE Woche (übersteuert
 *    das allgemeine).
 *
 * Nur für Schüler (Rolle kantine_student) – OGS/Sonstige haben kein Budget.
 * Eindeutigkeit (ein allgemeines + je Woche eines) wird in der Service-Schicht
 * per whereDate/whereNull sichergestellt (NULL-Spalten sind im UNIQUE-Index
 * nicht zuverlässig).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kantine_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('week_start')->nullable();
            $table->decimal('amount', 8, 2);
            $table->timestamps();

            $table->index(['user_id', 'week_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kantine_budgets');
    }
};
