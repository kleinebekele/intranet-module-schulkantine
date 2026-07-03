<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kundengruppen (z. B. OGS, Schule ab Klasse 5, Personal, Kita).
 * Die Gruppe steuert den Bestellmodus (ja/nein vs. Menü-Auswahl) und hat ein
 * eigenes Ausgabe-Zeitfenster (z. B. OGS isst zu einer anderen Zeit).
 *
 * `role_id` ist die Standard-Rolle der Gruppe: Benutzer mit dieser Rolle werden
 * beim Import dieser Gruppe zugeordnet. Eine Rolle gehört zu HÖCHSTENS einer
 * Gruppe (unique), damit ein Nutzer nicht in zwei Gruppen gleichzeitig landet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kantine_customer_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('ordering_mode')->default('menue'); // ja_nein | menue
            $table->string('pickup_from', 5)->nullable();       // Ausgabe von, "HH:MM"
            $table->string('pickup_to', 5)->nullable();         // Ausgabe bis, "HH:MM"
            $table->boolean('is_active')->default(true);
            $table->string('role_id', 64)->nullable()->unique(); // Standard-Rolle (roles.role_id)
            $table->timestamps();

            $table->foreign('role_id')->references('role_id')->on('roles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kantine_customer_groups');
    }
};
