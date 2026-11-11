<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * „Ausgabe OGS" wurde von modules:sync ohne Rollen angelegt – damit kam außer
 * Admins niemand hinein (403). Hat der Punkt noch keine Rolle, übernimmt er die
 * Freigaben (samt Stufe) von „Ausgabe". Bereits gesetzte Rollen bleiben unberührt.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('module_menu_item_role') || ! Schema::hasTable('modules')) {
            return;
        }
        $modulId = DB::table('modules')->where('key', 'schulkantine')->value('id');
        if (! $modulId) {
            return;
        }

        $ogs = DB::table('module_menu_items')->where('module_id', $modulId)->where('key', 'servings-ogs')->value('id');
        $ausgabe = DB::table('module_menu_items')->where('module_id', $modulId)->where('key', 'servings')->value('id');
        if (! $ogs || ! $ausgabe || DB::table('module_menu_item_role')->where('module_menu_item_id', $ogs)->exists()) {
            return;
        }

        $mitStufe = Schema::hasColumn('module_menu_item_role', 'stufe');
        foreach (DB::table('module_menu_item_role')->where('module_menu_item_id', $ausgabe)->get() as $r) {
            $zeile = ['module_menu_item_id' => $ogs, 'role_id' => $r->role_id];
            if ($mitStufe) {
                $zeile['stufe'] = $r->stufe;
            }
            DB::table('module_menu_item_role')->insert($zeile);
        }
    }

    public function down(): void
    {
        // Rollenzuordnung ist Admin-Sache – kein Zurückdrehen.
    }
};
