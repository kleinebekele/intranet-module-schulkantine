<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Protokoll der an Linear (MgEsGeld) gesendeten Abrechnungszeilen – je Esser und
 * Monat höchstens eine. Hält fest, WAS gesendet wurde (Snapshot), damit eine
 * spätere Änderung der Monatssumme auffällt statt still ein zweites Mal zu buchen.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('kantine_linear_exports')) {
            return;
        }
        Schema::create('kantine_linear_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); // der Esser
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('adrnr', 20);
            $table->string('abw_adrnr', 20);
            $table->unsignedSmallInteger('art');
            $table->string('vertrag_nr', 30)->nullable();
            $table->decimal('betrag', 10, 2);
            $table->date('datum');
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('hinweis', 255)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kantine_linear_exports');
    }
};
