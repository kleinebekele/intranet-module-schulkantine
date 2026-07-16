<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `allows_preorder` = darf diese Kategorie vorbestellt werden?
 *
 * Zusammen mit dem vorhandenen `allows_walkin` beschreiben zwei symmetrische Flags,
 * WOHER ein Gericht kommen darf:
 *
 *   preorder | walkin | Bedeutung
 *   ---------+--------+------------------------------------------------------
 *      ja    |  nein  | Hauptmenü – muss vorbestellt werden (bisheriger Default)
 *      ja    |   ja   | Getränke/Eis – vorbestellbar UND spontan am Tresen
 *      nein  |   ja   | NEU: „nur spontan“ – steht auf dem Plan, ist am Tresen
 *                       zu haben, taucht aber in der Vorbestellung nicht auf
 *      nein  |  nein  | verboten (Kategorie wäre nirgends erhältlich) →
 *                       CategoryController::validated() lehnt das ab
 *
 * Bewusst kein eigenes Flag „nur spontan“: Das könnte zusammen mit
 * allows_walkin=false stehen und wäre dann sinnlos. So ist der Zustand gar nicht
 * darstellbar.
 *
 * Default true = alle bestehenden Kategorien verhalten sich unverändert.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kantine_categories', function (Blueprint $table) {
            $table->boolean('allows_preorder')->default(true)->after('allows_walkin');
        });
    }

    public function down(): void
    {
        Schema::table('kantine_categories', function (Blueprint $table) {
            $table->dropColumn('allows_preorder');
        });
    }
};
