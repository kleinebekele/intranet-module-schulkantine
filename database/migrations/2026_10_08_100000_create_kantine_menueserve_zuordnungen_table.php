<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zuordnung der Menü&Serve-Menüs (je Tag ein Eintrag in MNUBAS) zu unseren Gerichten:
 * welche Hauptspeise und welche Nachspeise steckt dahinter. Nur gemerkt – der
 * Speiseplan wird davon nicht angefasst.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('kantine_menueserve_zuordnungen')) {
            return;
        }
        Schema::create('kantine_menueserve_zuordnungen', function (Blueprint $table) {
            $table->id();
            $table->string('ms_id', 36)->unique();           // MNUBAS_ID
            $table->date('datum');
            $table->string('titel', 100);
            $table->string('hauptspeise_text', 255)->nullable();
            $table->string('nachspeise_text', 255)->nullable();
            $table->foreignId('hauptspeise_id')->nullable()->constrained('kantine_dishes')->nullOnDelete();
            $table->foreignId('nachspeise_id')->nullable()->constrained('kantine_dishes')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kantine_menueserve_zuordnungen');
    }
};
