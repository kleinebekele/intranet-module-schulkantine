<?php

namespace Intranet\Modules\Schulkantine\Console\Commands;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Intranet\Modules\Schulkantine\Models\CustomerGroup;
use Intranet\Modules\Schulkantine\Models\NfcChip;

/**
 * Einmalige Übernahme der Chip-Kennungen aus dem Altsystem.
 *
 * Datei: CSV mit zwei Spalten – AdrNr (= `users.externe_id`) und Chip-Code.
 * Kopfzeile erlaubt, Trennzeichen (;/,/Tab) und Kodierung werden erkannt.
 *
 * Ohne `--live` nur Probelauf: zeigt, was passieren würde, schreibt nichts.
 * Regeln:
 *  - Code wird wie beim Scannen normalisiert (NfcChip::normalize).
 *  - Bereits aktiv registrierte Codes werden übersprungen (Konflikt gemeldet,
 *    wenn sie an einer anderen Person hängen).
 *  - Unbekannte AdrNr und OGS-Kinder werden gemeldet und übersprungen.
 *  - Angelegt als Schul-Chip ohne Pfand (Alt-Chips sind schon bezahlt);
 *    `--quelle=eltern` bzw. `--pfand` ändern das für die ganze Datei.
 */
class ImportChips extends Command
{
    protected $signature = 'kantine:import-chips
        {datei : Pfad zur CSV (AdrNr;Code)}
        {--live : Wirklich schreiben (sonst nur Probelauf)}
        {--quelle=schule : schule oder eltern}
        {--pfand : Schul-Chips mit Pfand (5,00 €) und heutigem Ausgabedatum anlegen}';

    protected $description = 'Übernimmt Chip-Codes aus dem Altsystem (CSV: AdrNr;Code) in die Kantine.';

    public function handle(): int
    {
        $pfad = (string) $this->argument('datei');
        if (! is_file($pfad)) {
            $this->error('Datei nicht gefunden: '.$pfad);

            return self::FAILURE;
        }

        $quelle = (string) $this->option('quelle');
        if (! in_array($quelle, [NfcChip::SOURCE_SCHULE, NfcChip::SOURCE_ELTERN], true)) {
            $this->error('--quelle muss schule oder eltern sein.');

            return self::FAILURE;
        }

        $live = (bool) $this->option('live');
        $mitPfand = $quelle === NfcChip::SOURCE_SCHULE && $this->option('pfand');

        $zeilen = $this->zeilenLesen($pfad);
        if ($zeilen === []) {
            $this->error('Keine verwertbaren Zeilen (erwartet: AdrNr;Code).');

            return self::FAILURE;
        }

        $benutzer = User::whereIn('externe_id', array_unique(array_column($zeilen, 'adrnr')))
            ->get()->keyBy('externe_id');

        $anlegen = [];
        $meldungen = [];
        $gesehen = [];

        foreach ($zeilen as $z) {
            $adrNr = $z['adrnr'];
            $uid = NfcChip::normalize($z['code']);
            $label = "AdrNr {$adrNr} / Code {$z['code']}";

            if ($uid === '') {
                $meldungen[] = "{$label}: Code leer/ungültig – übersprungen.";

                continue;
            }
            if (isset($gesehen[$uid])) {
                $meldungen[] = "{$label}: Code kommt in der Datei doppelt vor (zuerst AdrNr {$gesehen[$uid]}) – übersprungen.";

                continue;
            }
            $gesehen[$uid] = $adrNr;

            $user = $benutzer[$adrNr] ?? null;
            if ($user === null) {
                $meldungen[] = "{$label}: kein Benutzer mit dieser externe_id – übersprungen.";

                continue;
            }
            if (CustomerGroup::forUser($user)?->ordering_mode === CustomerGroup::MODE_JA_NEIN) {
                $meldungen[] = "{$label} ({$user->name}): OGS-Kind, bekommt keinen Chip – übersprungen.";

                continue;
            }
            if ($vorhanden = NfcChip::activeForUid($uid)) {
                $meldungen[] = $vorhanden->user_id === $user->id
                    ? "{$label} ({$user->name}): schon registriert – übersprungen."
                    : "{$label} ({$user->name}): KONFLIKT, Code hängt aktiv an '{$vorhanden->user?->name}' – übersprungen.";

                continue;
            }

            $anlegen[] = ['user' => $user, 'uid' => $uid, 'code' => $z['code']];
        }

        foreach ($meldungen as $m) {
            $this->warn($m);
        }

        $this->line('');
        $this->info(sprintf('%d Zeile(n) gelesen, %d Chip(s) %s, %d übersprungen.',
            count($zeilen), count($anlegen), $live ? 'angelegt' : 'würden angelegt', count($meldungen)));

        if ($anlegen === []) {
            return self::SUCCESS;
        }

        if (! $live) {
            $this->table(['AdrNr', 'Name', 'Code (normalisiert)'], array_map(
                fn ($a) => [$a['user']->externe_id, $a['user']->name, $a['uid']], $anlegen));
            $this->line('Probelauf – nichts geschrieben. Zum Schreiben: --live anhängen.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($anlegen, $quelle, $mitPfand): void {
            foreach ($anlegen as $a) {
                NfcChip::create([
                    'user_id' => $a['user']->id,
                    'uid' => $a['uid'],
                    'source' => $quelle,
                    'deposit' => $mitPfand ? NfcChip::SCHULE_DEPOSIT : 0.0,
                    'lent_at' => $mitPfand ? Carbon::today()->toDateString() : null,
                ]);
            }
        });

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{adrnr:string, code:string}>
     */
    private function zeilenLesen(string $pfad): array
    {
        $inhalt = (string) file_get_contents($pfad);
        if (str_starts_with($inhalt, "\xEF\xBB\xBF")) {
            $inhalt = substr($inhalt, 3);
        }
        if (! mb_check_encoding($inhalt, 'UTF-8')) {
            $inhalt = mb_convert_encoding($inhalt, 'UTF-8', 'Windows-1252');
        }

        $trenner = ';';
        foreach ([';', "\t", ','] as $t) {
            if (substr_count($inhalt, $t) > 0) {
                $trenner = $t;
                break;
            }
        }

        $zeilen = [];
        $erste = true;
        foreach (preg_split('/\R/', $inhalt) ?: [] as $zeile) {
            if (trim($zeile) === '') {
                continue;
            }
            $spalten = array_map('trim', str_getcsv($zeile, $trenner, '"', '\\'));
            $adrNr = (string) ($spalten[0] ?? '');
            $code = (string) ($spalten[1] ?? '');

            if ($erste) {
                $erste = false;
                if (! ctype_digit($adrNr)) {
                    continue; // Kopfzeile
                }
            }
            if ($adrNr === '' || $code === '') {
                continue;
            }
            $zeilen[] = ['adrnr' => ltrim($adrNr, '0') ?: '0', 'code' => $code];
        }

        return $zeilen;
    }
}
