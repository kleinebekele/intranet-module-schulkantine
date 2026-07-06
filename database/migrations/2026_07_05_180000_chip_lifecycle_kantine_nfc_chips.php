<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chip-Lebenszyklus (Pfand). Ein Esser kann jetzt MEHRERE Chips haben – einen
 * Schul-Chip (mit Pfand) und/oder eigene. Für die Abrechnung wird festgehalten:
 *  - lent_at      wann ein Schul-Chip ausgegeben wurde (Pfand-Monat).
 *  - returned_at  wann er zurückgegeben wurde (Rückgabe-Monat; danach inaktiv).
 *
 * Die frühere „ein Chip pro Nutzer"- und „UID global eindeutig"-Beschränkung
 * entfällt. Die Eindeutigkeit einer AKTIVEN UID (returned_at = null) wird in der
 * Anwendung sichergestellt (siehe NfcChip::activeForUid()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kantine_nfc_chips', function (Blueprint $table) {
            // MySQL: Der Fremdschlüssel auf user_id braucht den Unique-Index –
            // darum erst den FK lösen, sonst „Cannot drop index ... needed in a
            // foreign key constraint". Danach FK wieder herstellen; er nutzt dann
            // den zusammengesetzten Index (user_id ist dessen erste Spalte).
            $table->dropForeign(['user_id']);
            $table->dropUnique(['uid']);
            $table->dropUnique(['user_id']);
            $table->date('lent_at')->nullable()->after('deposit');
            $table->date('returned_at')->nullable()->after('lent_at');
            $table->index('uid');
            $table->index(['user_id', 'returned_at']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('kantine_nfc_chips', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropIndex(['user_id', 'returned_at']);
            $table->dropIndex(['uid']);
            $table->dropColumn(['lent_at', 'returned_at']);
            $table->unique('uid');
            $table->unique('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
