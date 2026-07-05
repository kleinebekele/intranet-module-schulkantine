<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Legt die BETRIEBS-Rollen der Kantine an (Phase 4 – Ausgabe & Betrieb).
 * Analog zu den Gruppen-Rollen (kantine_ogs/kantine_student) werden sie im Core
 * als System-Rollen (unlöschbar) sichergestellt.
 *
 *   kantine_koch          → Küche: sieht Ausgabelisten & Mengen (nicht abhaken).
 *   kantine_kellner       → Ausgabe: hakt Essen ab + erfasst spontane Abholung.
 *   kantine_ogs_betreuer  → sieht die OGS-Sammelliste (heute essende OGS-Kinder).
 *
 * Der Voll-Zugriff („kantinenadmin") läuft weiterhin über das Core-Admin-Flag
 * (User::isAdmin()) – eine feinere Trennung kommt bei Bedarf später.
 */
return new class extends Migration
{
    /** Rollen, die dieses Modul im Core sicherstellt. */
    private const ROLES = [
        'kantine_koch' => 'Kantine: Koch',
        'kantine_kellner' => 'Kantine: Ausgabe (Kellner)',
        'kantine_ogs_betreuer' => 'Kantine: OGS-Betreuer',
    ];

    public function up(): void
    {
        foreach (self::ROLES as $roleId => $name) {
            if (DB::table('roles')->where('role_id', $roleId)->exists()) {
                DB::table('roles')->where('role_id', $roleId)->update([
                    'is_system' => true,
                    'updated_at' => now(),
                ]);
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
    }

    public function down(): void
    {
        // cascade auf user_roles entfernt die Zuweisungen automatisch.
        DB::table('roles')->whereIn('role_id', array_keys(self::ROLES))->delete();
    }
};
