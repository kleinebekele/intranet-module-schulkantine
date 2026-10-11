<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wochenbudget, Teil 2:
 *  - kantine_budget_optionen: Einstellungen der Eltern je Kind (z. B. Budget am
 *    Ausgabe-Terminal anzeigen – Standard aus).
 *  - kantine_budget_ueberschreitungen: An der Ausgabe erlaubte Überschreitungen.
 *    Solange eine nicht von den Eltern bestätigt ist (akzeptiert_at NULL), ist
 *    keine weitere Überschreitung möglich.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('kantine_budget_optionen')) {
            Schema::create('kantine_budget_optionen', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
                $table->boolean('am_ausgabeterminal')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('kantine_budget_ueberschreitungen')) {
            Schema::create('kantine_budget_ueberschreitungen', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); // das Kind
                $table->date('woche');                                                // Montag
                $table->date('tag');
                $table->decimal('budget', 8, 2);
                $table->decimal('genutzt', 8, 2);                                     // inkl. dieses Kaufs
                $table->decimal('ueber', 8, 2);                                       // genutzt − budget
                $table->foreignId('erlaubt_von')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('akzeptiert_at')->nullable();
                $table->foreignId('akzeptiert_von')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['user_id', 'akzeptiert_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kantine_budget_ueberschreitungen');
        Schema::dropIfExists('kantine_budget_optionen');
    }
};
