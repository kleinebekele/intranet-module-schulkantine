<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Freie Zusatz-Info je Teilnehmer (z. B. „Klasse 5"), gepflegt AUSSCHLIESSLICH
 * über den CSV-Import aus storage/app/kantinen-import – siehe InfoImporter.
 *
 * Eigene Tabelle statt einer Spalte an `users`: die Info gehört der Kantine,
 * nicht dem Core (Insel-Prinzip). Ein Benutzer hat höchstens eine Info, darum
 * ist user_id eindeutig; ein gelöschter Benutzer nimmt seine Info mit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kantine_user_infos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('info');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kantine_user_infos');
    }
};
