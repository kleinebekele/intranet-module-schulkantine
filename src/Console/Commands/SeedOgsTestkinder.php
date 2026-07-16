<?php

namespace Intranet\Modules\Schulkantine\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Intranet\Modules\Schulkantine\Models\Season;
use Intranet\Modules\Schulkantine\Models\Subscription;

/**
 * Legt (idempotent) eine Reihe OGS-Testkinder für die OGS-Ansicht des Ausgabe-
 * Terminals an: Rolle kantine_ogs + aktives Saison-Abo (= isst an allen
 * Öffnungstagen). Ein paar bekommen Sonderkost, um die ⚠-Anzeige zu testen.
 *
 * Nur für Test-/Beta-Umgebungen. Aufräumen: --remove.
 */
class SeedOgsTestkinder extends Command
{
    protected $signature = 'kantine:seed-ogs-testkinder
        {--count=50 : Anzahl anzulegender OGS-Testkinder}
        {--password=test1234 : Passwort für alle Test-Konten}
        {--remove : Testkinder (und ihre Abos) wieder entfernen}';

    protected $description = 'Legt OGS-Testkinder mit aktivem Abo an (für die OGS-Terminal-Ansicht).';

    /** Bewusst über den Vornamen gestreut (A–Z), damit sich die Spalten-Aufteilung testen lässt. */
    private const FIRST = [
        'Anna', 'Ben', 'Clara', 'David', 'Emma', 'Finn', 'Greta', 'Hannah', 'Ida', 'Jonas',
        'Klara', 'Leon', 'Mia', 'Noah', 'Ole', 'Paul', 'Quirin', 'Rosa', 'Sophie', 'Tom',
        'Ute', 'Valentin', 'Wanda', 'Xaver', 'Yara', 'Zoe', 'Amelie', 'Bruno', 'Carla', 'Dennis',
        'Elias', 'Frieda', 'Georg', 'Helena', 'Isabel', 'Jakob', 'Katharina', 'Lukas', 'Marie', 'Nele',
        'Oskar', 'Pia', 'Rafael', 'Selina', 'Theo', 'Ulrich', 'Vincent', 'Wilma', 'Yannick', 'Zara',
    ];

    private const LAST = [
        'Müller', 'Schmidt', 'Schneider', 'Fischer', 'Weber', 'Meyer', 'Wagner', 'Becker', 'Schulz', 'Hoffmann',
        'Koch', 'Bauer', 'Richter', 'Klein', 'Wolf', 'Neumann', 'Schwarz', 'Zimmermann', 'Braun', 'Krüger',
        'Hofmann', 'Hartmann', 'Lange', 'Werner', 'Krause', 'Lehmann', 'Schmitt', 'Maier', 'Köhler', 'Herrmann',
        'König', 'Walter', 'Peters', 'Jung', 'Möller', 'Hahn', 'Keller', 'Vogel', 'Frank', 'Berger',
        'Roth', 'Beck', 'Lorenz', 'Baumann', 'Franke', 'Albrecht', 'Winter', 'Winkler', 'Vogt', 'Sommer',
    ];

    public function handle(): int
    {
        $count = max(1, min((int) $this->option('count'), count(self::FIRST)));

        if ($this->option('remove')) {
            return $this->remove($count);
        }

        $season = Season::where('is_active', true)->first();
        if (! $season) {
            $this->error('Keine aktive Saison – ein Abo braucht eine Saison. Erst eine Saison aktivieren.');

            return self::FAILURE;
        }

        $this->ensureRole();
        $password = (string) $this->option('password');

        // Ein paar Kinder mit Sonderkost, um die ⚠-Anzeige zu testen (Name des Allergens/der Diät).
        $allergenIds = DB::table('kantine_allergens')
            ->whereIn('name', ['Milch / Laktose', 'Erdnüsse', 'Glutenhaltiges Getreide'])
            ->pluck('id', 'name');
        $dietIds = DB::table('kantine_diets')
            ->whereIn('name', ['vegetarisch', 'vegan', 'glutenfrei'])
            ->pluck('id', 'name');

        $created = 0;
        for ($i = 0; $i < $count; $i++) {
            $name = self::FIRST[$i].' '.self::LAST[$i];
            $email = 'ogs-test-'.($i + 1).'@kantine.test';

            $user = User::updateOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => Hash::make($password)],
            );
            $user->is_admin = false;
            $user->save();

            $user->roles()->syncWithoutDetaching(['kantine_ogs']);

            Subscription::updateOrCreate(
                ['season_id' => $season->id, 'user_id' => $user->id],
                ['active' => true],
            );

            // Sonderkost für einen Teil der Kinder (Test der ⚠-Anzeige).
            $this->syncPivot('kantine_user_allergen', 'allergen_id', $user->id,
                $this->pickFor($i, 6, $allergenIds));
            $this->syncPivot('kantine_user_diet', 'diet_id', $user->id,
                $this->pickFor($i, 5, $dietIds));

            $created++;
        }

        $this->info("Fertig: {$created} OGS-Testkinder mit aktivem Abo in Saison „{$season->name}".
            "\" (alle Öffnungstage). Passwort: {$password}");

        return self::SUCCESS;
    }

    /** Jedes n-te Kind bekommt einen Wert aus der Liste (der Rest: keine Sonderkost). */
    private function pickFor(int $i, int $every, \Illuminate\Support\Collection $ids): array
    {
        if ($ids->isEmpty() || $i % $every !== 0) {
            return [];
        }
        $values = $ids->values();

        return [$values[intdiv($i, $every) % $values->count()]];
    }

    /** Pivot-Einträge eines Users für eine Sonderkost-Art setzen (idempotent, ersetzend). */
    private function syncPivot(string $table, string $column, int $userId, array $ids): void
    {
        DB::table($table)->where('user_id', $userId)->delete();
        foreach ($ids as $id) {
            DB::table($table)->insert(['user_id' => $userId, $column => $id]);
        }
    }

    private function ensureRole(): void
    {
        if (! DB::table('roles')->where('role_id', 'kantine_ogs')->exists()) {
            DB::table('roles')->insert([
                'role_id' => 'kantine_ogs',
                'name' => 'Kantine: OGS-Kind',
                'is_system' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function remove(int $count): int
    {
        $emails = [];
        for ($i = 0; $i < count(self::FIRST); $i++) {
            $emails[] = 'ogs-test-'.($i + 1).'@kantine.test';
        }
        $users = User::whereIn('email', $emails)->get();
        foreach ($users as $u) {
            DB::table('kantine_user_allergen')->where('user_id', $u->id)->delete();
            DB::table('kantine_user_diet')->where('user_id', $u->id)->delete();
            Subscription::where('user_id', $u->id)->delete();
            $u->roles()->detach();
            $u->delete();
        }
        $this->info('Entfernt: '.$users->count().' OGS-Testkinder (inkl. Abos & Sonderkost).');

        return self::SUCCESS;
    }
}
