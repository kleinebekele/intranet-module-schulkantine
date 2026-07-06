<?php

namespace Intranet\Modules\Schulkantine\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Legt Test-Benutzer für ALLE Rollen an, die das Schulkantine-Modul kennt –
 * Betrieb (Koch, Kellner, OGS-Betreuer), Esser (Schüler, OGS, Sonstige),
 * ein Elternteil mit zwei Kindern sowie einen Admin.
 *
 * Idempotent: mehrfaches Ausführen legt nichts doppelt an (updateOrCreate über
 * die E-Mail). Nur für Test-/Beta-Umgebungen gedacht.
 */
class SeedTestUsers extends Command
{
    protected $signature = 'kantine:seed-testusers {--password=test1234 : Passwort für alle Test-Konten}';

    protected $description = 'Legt Test-Benutzer für alle Kantine-Rollen an (Betrieb, Esser, Eltern, Admin).';

    /**
     * Betriebs-Rollen, die das Modul zwar in Access.php kennt, aber (anders als
     * kantine_ogs / kantine_student) nicht per Migration anlegt. Hier absichern.
     */
    private const OPERATOR_ROLES = [
        'kantine_koch' => 'Kantine: Koch',
        'kantine_kellner' => 'Kantine: Kellner',
        'kantine_ogs_betreuer' => 'Kantine: OGS-Betreuer',
    ];

    public function handle(): int
    {
        $password = (string) $this->option('password');

        $this->info('Stelle Betriebs-Rollen sicher …');
        foreach (self::OPERATOR_ROLES as $roleId => $name) {
            $this->ensureRole($roleId, $name);
        }

        // [Anzeigename, E-Mail, Rolle (oder null = nur "user"/Sonstige), Admin?]
        $definitions = [
            ['Test Admin',                 'admin@kantine.test',         null,                   true],
            ['Test Koch',                  'koch@kantine.test',          'kantine_koch',         false],
            ['Test Kellner',               'kellner@kantine.test',       'kantine_kellner',      false],
            ['Test OGS-Betreuer',          'ogs-betreuer@kantine.test',  'kantine_ogs_betreuer', false],
            ['Test Elternteil',            'eltern@kantine.test',        null,                   false],
            ['Test Kind (Schüler)',        'kind-schueler@kantine.test', 'kantine_student',      false],
            ['Test Kind (OGS)',            'kind-ogs@kantine.test',      'kantine_ogs',          false],
            ['Test Schüler (eigenständig)', 'schueler@kantine.test',     'kantine_student',      false],
            ['Test Esser (Sonstige)',      'sonstige@kantine.test',      null,                   false],
        ];

        $this->info('Lege Test-Benutzer an …');
        $rows = [];
        foreach ($definitions as [$name, $email, $roleId, $isAdmin]) {
            $user = $this->ensureUser($name, $email, $password, $isAdmin);

            if ($roleId) {
                $user->roles()->syncWithoutDetaching([$roleId]);
            }

            $rows[] = [$email, $password, $isAdmin ? 'Admin' : ($roleId ?? 'Sonstige (nur Esser)')];
        }

        // Eltern-Kind-Verknüpfung: das Elternteil darf für beide Kinder bestellen.
        $parent = User::where('email', 'eltern@kantine.test')->first();
        $children = User::whereIn('email', ['kind-schueler@kantine.test', 'kind-ogs@kantine.test'])->pluck('id');
        $parent->children()->syncWithoutDetaching($children->all());
        $this->line("Verknüpft: <info>eltern@kantine.test</info> ist Elternteil von {$children->count()} Kindern.");

        $this->newLine();
        $this->table(['E-Mail', 'Passwort', 'Rolle / Funktion'], $rows);
        $this->newLine();
        $this->info('Fertig. Alle Konten nutzen das Passwort: '.$password);

        return self::SUCCESS;
    }

    /** Rolle anlegen, falls sie fehlt – als System-Rolle (unlöschbar). */
    private function ensureRole(string $roleId, string $name): void
    {
        if (DB::table('roles')->where('role_id', $roleId)->exists()) {
            return;
        }

        DB::table('roles')->insert([
            'role_id' => $roleId,
            'name' => $name,
            'is_system' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->line("  Rolle angelegt: <info>{$roleId}</info> ({$name})");
    }

    /**
     * Benutzer anlegen oder aktualisieren. is_admin wird bewusst explizit
     * gesetzt (nicht mass-assignable) – neutralisiert auch den „erster Benutzer
     * wird Admin"-Automatismus des Core-User-Models.
     */
    private function ensureUser(string $name, string $email, string $password, bool $isAdmin): User
    {
        $user = User::updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => Hash::make($password)],
        );

        $user->is_admin = $isAdmin;
        $user->save();

        return $user;
    }
}
