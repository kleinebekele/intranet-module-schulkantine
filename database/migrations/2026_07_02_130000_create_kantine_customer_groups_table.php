<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kundengruppen (Esser-Gruppen). Es gibt genau DREI feste Gruppen, die per
 * Seed-Migration angelegt werden (OGS, Schüler, Sonstige) – kein Anlegen/Löschen
 * weiterer. Die Gruppe steuert den Bestellmodus (ja/nein vs. Menü-Auswahl) und
 * hat ein eigenes Ausgabe-Zeitfenster.
 *
 * `role_id` ist die fest verknüpfte Rolle der Gruppe: Die Einstufung eines
 * Benutzers ergibt sich aus seinen Rollen (Priorität OGS → Schüler → Sonstige).
 * Eine Rolle gehört zu genau einer Gruppe (unique).
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
            $table->string('role_id', 64)->nullable()->unique(); // fest verknüpfte Rolle (roles.role_id)
            $table->timestamps();

            $table->foreign('role_id')->references('role_id')->on('roles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kantine_customer_groups');
    }
};
