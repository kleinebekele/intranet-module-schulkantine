<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chip-Lesungen der Ausgabe-Terminals: Ein Chip, der an einem Terminal gelesen
 * wird, wirkt auf allen offenen Terminals, als wäre er dort gelesen worden. Die
 * Terminals fragen diese Tabelle alle zwei Sekunden ab. Einträge leben nur kurz
 * (älter als ein Tag wird beim nächsten Scan gelöscht).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('kantine_terminal_scans')) {
            return;
        }
        Schema::create('kantine_terminal_scans', function (Blueprint $table) {
            $table->id();
            $table->string('uid', 255);
            $table->string('terminal', 64);
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kantine_terminal_scans');
    }
};
