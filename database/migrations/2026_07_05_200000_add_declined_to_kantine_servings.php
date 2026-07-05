<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dokumentiert bei der Ausgabe, wenn ein bestelltes Gericht NICHT genommen wird:
 *  - declined        true = das Kind hat das (bestellte) Gericht nicht genommen.
 *  - decline_reason  Grund (z. B. „mag es nicht", „kein Hunger").
 *
 * Die Zeile bleibt eine Ausgabe-Zeile (verbindlich bestelltes Essen wird
 * unabhängig davon berechnet); declined ist reine Betriebs-Doku (Verschwendung).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kantine_servings', function (Blueprint $table) {
            $table->boolean('declined')->default(false)->after('spontaneous');
            $table->string('decline_reason')->nullable()->after('declined');
        });
    }

    public function down(): void
    {
        Schema::table('kantine_servings', function (Blueprint $table) {
            $table->dropColumn(['declined', 'decline_reason']);
        });
    }
};
