<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Legt die DREI festen Esser-Gruppen samt zugehöriger Rollen an – die
 * Konvention des Schulkantine-Moduls. Kein Anlegen/Löschen weiterer Gruppen.
 *
 *   OGS      → Rolle kantine_ogs       (Priorität 1)
 *   Schüler  → Rolle kantine_student   (Priorität 2)
 *   Sonstige → Rolle user (Core-Fallback, jeder Benutzer hat sie → darf essen)
 *
 * Die Kantine-Rollen werden im Core angelegt, falls sie noch fehlen, und als
 * System-Rollen (unlöschbar) markiert – analog zu den Core-Rollen admin/user.
 */
return new class extends Migration
{
    /** Rollen, die dieses Modul im Core sicherstellt. */
    private const ROLES = [
        'kantine_ogs' => 'Kantine: OGS',
        'kantine_student' => 'Kantine: Schüler',
    ];

    /** Die drei festen Gruppen: [name, role_id, ordering_mode]. */
    private const GROUPS = [
        ['name' => 'OGS', 'role_id' => 'kantine_ogs', 'ordering_mode' => 'ja_nein'],
        ['name' => 'Schüler', 'role_id' => 'kantine_student', 'ordering_mode' => 'menue'],
        ['name' => 'Sonstige', 'role_id' => 'user', 'ordering_mode' => 'menue'],
    ];

    public function up(): void
    {
        // 1) Kantine-Rollen im Core sicherstellen (unlöschbar).
        foreach (self::ROLES as $roleId => $name) {
            if (DB::table('roles')->where('role_id', $roleId)->exists()) {
                DB::table('roles')->where('role_id', $roleId)->update(['is_system' => true, 'updated_at' => now()]);
            } else {
                DB::table('roles')->insert([
                    'role_id' => $roleId,
                    'name' => $name,
                    'is_system' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // 2) Die drei festen Gruppen sicherstellen (idempotent über role_id).
        foreach (self::GROUPS as $group) {
            if (DB::table('kantine_customer_groups')->where('role_id', $group['role_id'])->exists()) {
                continue;
            }
            DB::table('kantine_customer_groups')->insert($group + [
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('kantine_customer_groups')
            ->whereIn('role_id', array_column(self::GROUPS, 'role_id'))
            ->delete();

        // Nur die von diesem Modul angelegten Rollen entfernen (nicht 'user').
        DB::table('roles')->whereIn('role_id', array_keys(self::ROLES))->delete();
    }
};
