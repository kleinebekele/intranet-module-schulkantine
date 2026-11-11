<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * „Ausgabe OGS" (v1.102.0) hat modules:sync ans Ende einsortiert – mit derselben
 * Position wie andere Punkte. Hier rückt er direkt hinter „Ausgabe"; alle übrigen
 * Punkte des Moduls behalten ihre Reihenfolge und werden nur fortlaufend nummeriert.
 * Fehlt der Punkt (noch nicht abgeglichen), passiert nichts.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('module_menu_items') || ! Schema::hasTable('modules')) {
            return;
        }
        $modulId = DB::table('modules')->where('key', 'schulkantine')->value('id');
        if (! $modulId) {
            return;
        }

        $punkte = DB::table('module_menu_items')->where('module_id', $modulId)
            ->orderBy('position')->orderBy('id')->get(['id', 'key']);
        $ogs = $punkte->firstWhere('key', 'servings-ogs');
        if (! $ogs || ! $punkte->firstWhere('key', 'servings')) {
            return;
        }

        $reihe = [];
        foreach ($punkte as $p) {
            if ($p->key === 'servings-ogs') {
                continue;
            }
            $reihe[] = $p->id;
            if ($p->key === 'servings') {
                $reihe[] = $ogs->id;
            }
        }

        foreach ($reihe as $pos => $id) {
            DB::table('module_menu_items')->where('id', $id)->update(['position' => $pos]);
        }
    }

    public function down(): void
    {
        // Reihenfolge ist Admin-Sache – kein Zurückdrehen.
    }
};
