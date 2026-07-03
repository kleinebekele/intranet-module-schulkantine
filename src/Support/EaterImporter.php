<?php

namespace Intranet\Modules\Schulkantine\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Intranet\Modules\Schulkantine\Models\CustomerGroup;
use Intranet\Modules\Schulkantine\Models\Eater;
use Intranet\Modules\Schulkantine\Models\Season;

/**
 * Importiert Teilnehmer (Esser) aus einer CSV – gedacht für Schüler OHNE
 * eigenen Intranet-Account (kein Login). Erwartete Spalten (Reihenfolge egal):
 *
 *   external_id, name, group, parent1, parent2, parent3, parent4
 *
 *  - external_id: eindeutiger Schlüssel → Import ist idempotent (Upsert).
 *  - group:       Name einer bestehenden Kundengruppe (für die aktive Saison).
 *  - parentN:     Elternteil als Benutzer – erkannt per E-Mail (mit „@") ODER
 *                 exaktem Namen. Nicht gefundene werden als Hinweis gemeldet.
 */
class EaterImporter
{
    /** @return array{created:int, updated:int, skipped:int, warnings:array<int,string>} */
    public function import(string $content): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $warnings = [];

        $lines = preg_split('/\r\n|\r|\n/', trim($content)) ?: [];

        if (count($lines) < 2 || trim($lines[0]) === '') {
            return ['created' => 0, 'updated' => 0, 'skipped' => 0, 'warnings' => ['Die Datei enthält keine Datenzeilen.']];
        }

        // Trennzeichen automatisch erkennen (Komma oder Semikolon).
        $delimiter = substr_count($lines[0], ';') > substr_count($lines[0], ',') ? ';' : ',';
        $header = array_map(fn ($h) => strtolower(trim($h)), str_getcsv(array_shift($lines), $delimiter, '"', ''));
        $idx = array_flip($header);

        $activeSeason = Season::where('is_active', true)->first();
        $groupsByName = CustomerGroup::all()->keyBy(fn ($g) => mb_strtolower($g->name));

        $rowNo = 1;
        foreach ($lines as $line) {
            $rowNo++;

            if (trim($line) === '') {
                continue;
            }

            $cols = str_getcsv($line, $delimiter, '"', '');
            $get = fn (string $key) => isset($idx[$key], $cols[$idx[$key]]) ? trim((string) $cols[$idx[$key]]) : '';

            $extId = $get('external_id');
            $name = $get('name');

            if ($extId === '' || $name === '') {
                $warnings[] = "Zeile {$rowNo}: external_id oder name fehlt – übersprungen.";
                $skipped++;

                continue;
            }

            $eater = Eater::firstOrNew(['external_id' => $extId]);
            $existed = $eater->exists;
            $eater->name = $name;

            if (! $existed) {
                $eater->is_active = true;
            }

            $eater->save();
            $existed ? $updated++ : $created++;

            // Gruppe für die aktive Saison
            $groupName = $get('group');
            if ($groupName !== '') {
                $group = $groupsByName->get(mb_strtolower($groupName));

                if (! $group) {
                    $warnings[] = "Zeile {$rowNo} ({$name}): Gruppe „{$groupName}“ nicht gefunden.";
                } elseif (! $activeSeason) {
                    $warnings[] = "Zeile {$rowNo} ({$name}): keine aktive Saison – Gruppe nicht gesetzt.";
                } else {
                    DB::table('kantine_eater_season_group')->updateOrInsert(
                        ['eater_id' => $eater->id, 'season_id' => $activeSeason->id],
                        ['customer_group_id' => $group->id],
                    );
                }
            }

            // Vormunde (parent1..parent4) – per E-Mail oder Name auf Benutzer mappen
            $guardianIds = [];
            foreach (['parent1', 'parent2', 'parent3', 'parent4'] as $pk) {
                $val = $get($pk);

                if ($val === '') {
                    continue;
                }

                $user = str_contains($val, '@')
                    ? User::where('email', $val)->first()
                    : User::where('name', $val)->first();

                if ($user) {
                    $guardianIds[] = $user->id;
                } else {
                    $warnings[] = "Zeile {$rowNo} ({$name}): Elternteil „{$val}“ nicht als Benutzer gefunden.";
                }
            }

            if ($guardianIds !== []) {
                $eater->guardians()->syncWithoutDetaching($guardianIds);
            }
        }

        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped, 'warnings' => $warnings];
    }
}
