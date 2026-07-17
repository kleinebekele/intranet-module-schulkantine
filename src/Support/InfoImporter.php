<?php

namespace Intranet\Modules\Schulkantine\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Intranet\Modules\Schulkantine\Models\UserInfo;

/**
 * Liest Teilnehmer-Infos (z. B. „Klasse 5") aus CSV-Dateien ein, die das
 * Schulverwaltungs-System in storage/app/kantinen-import ablegt.
 *
 * Format: zwei Spalten – `user_id` (= die externe ID aus dem Quellsystem, steht
 * am Intranet-Benutzer als `externe_id`) und `Info`. Eine Kopfzeile darf sein,
 * muss aber nicht. Trennzeichen (;/,/Tab) und Kodierung (UTF-8/Windows-1252)
 * erkennt der Importer selbst – Excel-Exporte laufen damit ohne Vorarbeit durch.
 *
 * Regeln:
 *  - Vorhandene Infos werden hart überschrieben.
 *  - Wer in der CSV FEHLT, behält seine bisherige Info (eine halbe Datei darf
 *    keine Daten vernichten). Zum Entfernen: Zeile mit leerer Info schicken.
 *  - Eine erfolgreich eingelesene Datei wird gelöscht, damit sie nicht bei jedem
 *    Lauf erneut verarbeitet wird. Unlesbare Dateien bleiben liegen und werden
 *    gemeldet.
 */
class InfoImporter
{
    /** Ablage-Ordner, relativ zu storage/app (bewusst NICHT die 'local'-Disk: die zeigt auf app/private). */
    public const ORDNER = 'kantinen-import';

    /** Spaltennamen einer optionalen Kopfzeile – dann wird die Zeile übersprungen. */
    private const KOPFZEILE = ['user_id', 'userid', 'id'];

    /** @var array<int, string> Meldungen, die der Aufrufer anzeigen kann. */
    private array $fehler = [];

    public function verzeichnis(): string
    {
        return storage_path('app/'.self::ORDNER);
    }

    /**
     * Alle wartenden CSV-Dateien einlesen (älteste zuerst, damit bei mehreren
     * Ständen der neueste gewinnt).
     *
     * @return array{dateien:int, gesetzt:int, geleert:int, unbekannt:int, fehler:array<int, string>}
     */
    public function run(): array
    {
        $this->fehler = [];

        $ergebnis = ['dateien' => 0, 'gesetzt' => 0, 'geleert' => 0, 'unbekannt' => 0, 'fehler' => []];

        foreach ($this->wartendeDateien() as $pfad) {
            $datei = basename($pfad);

            try {
                $zeilen = $this->zeilenLesen($pfad);
            } catch (\Throwable $e) {
                $this->fehler[] = $datei.': '.$e->getMessage();
                Log::warning('Kantine-Info-Import: '.$datei.' konnte nicht gelesen werden.', ['fehler' => $e->getMessage()]);

                continue; // Datei liegen lassen – sie soll auffallen, nicht verschwinden.
            }

            $stand = $this->zeilenSchreiben($zeilen);

            $ergebnis['dateien']++;
            $ergebnis['gesetzt'] += $stand['gesetzt'];
            $ergebnis['geleert'] += $stand['geleert'];
            $ergebnis['unbekannt'] += $stand['unbekannt'];

            @unlink($pfad);

            Log::info('Kantine-Info-Import: '.$datei.' verarbeitet.', $stand);
        }

        $ergebnis['fehler'] = $this->fehler;

        return $ergebnis;
    }

    /**
     * CSV-Dateien im Ablage-Ordner, älteste zuerst.
     *
     * @return array<int, string>
     */
    public function wartendeDateien(): array
    {
        $verzeichnis = $this->verzeichnis();

        if (! is_dir($verzeichnis)) {
            return [];
        }

        $dateien = array_filter(
            glob($verzeichnis.'/*') ?: [],
            fn (string $p) => is_file($p) && strtolower(pathinfo($p, PATHINFO_EXTENSION)) === 'csv'
        );

        usort($dateien, fn (string $a, string $b) => filemtime($a) <=> filemtime($b));

        return array_values($dateien);
    }

    /**
     * Eine CSV in Paare [externe_id => info] auflösen. Bei doppelter ID gewinnt
     * die letzte Zeile.
     *
     * @return array<string, string>
     */
    private function zeilenLesen(string $pfad): array
    {
        $inhalt = @file_get_contents($pfad);
        if ($inhalt === false) {
            throw new \RuntimeException('Datei nicht lesbar.');
        }

        $inhalt = $this->nachUtf8($inhalt);
        $trenner = $this->trennzeichen($inhalt);

        $paare = [];
        $erste = true;

        foreach (preg_split('/\R/', $inhalt) ?: [] as $zeile) {
            if (trim($zeile) === '') {
                continue;
            }

            $spalten = str_getcsv($zeile, $trenner, '"', '\\');
            $externeId = trim((string) ($spalten[0] ?? ''));

            if ($erste) {
                $erste = false;
                if (in_array(mb_strtolower($externeId), self::KOPFZEILE, true)) {
                    continue;
                }
            }

            if ($externeId === '') {
                continue;
            }

            // Alles ab Spalte 2 unverändert zusammenfassen: so überlebt eine Info,
            // in der ein unmaskiertes Trennzeichen steckt (z. B. „Klasse 5; Ganztag").
            $info = trim(implode($trenner, array_slice($spalten, 1)), " \t\n\r\0\x0B".$trenner);

            $paare[$externeId] = $info;
        }

        if ($paare === []) {
            throw new \RuntimeException('Keine verwertbaren Zeilen gefunden (erwartet: user_id'.$trenner.'Info).');
        }

        return $paare;
    }

    /**
     * Die Paare in die DB schreiben: bekannte IDs setzen/leeren, unbekannte melden.
     *
     * @param  array<string, string>  $paare
     * @return array{gesetzt:int, geleert:int, unbekannt:int}
     */
    private function zeilenSchreiben(array $paare): array
    {
        // Eine Abfrage für alle IDs statt einer je Zeile.
        $benutzer = User::whereIn('externe_id', array_keys($paare))
            ->pluck('id', 'externe_id');

        $unbekannt = [];
        $gesetzt = 0;
        $geleert = 0;

        DB::transaction(function () use ($paare, $benutzer, &$unbekannt, &$gesetzt, &$geleert) {
            foreach ($paare as $externeId => $info) {
                $userId = $benutzer[$externeId] ?? null;

                if ($userId === null) {
                    $unbekannt[] = $externeId;

                    continue;
                }

                if ($info === '') {
                    $geleert += UserInfo::where('user_id', $userId)->delete();

                    continue;
                }

                UserInfo::updateOrCreate(['user_id' => $userId], ['info' => $info]);
                $gesetzt++;
            }
        });

        if ($unbekannt !== []) {
            $liste = array_slice($unbekannt, 0, 10);
            $this->fehler[] = count($unbekannt).' unbekannte user_id (kein Benutzer mit dieser externen ID): '
                .implode(', ', $liste).(count($unbekannt) > 10 ? ' …' : '');
        }

        return ['gesetzt' => $gesetzt, 'geleert' => $geleert, 'unbekannt' => count($unbekannt)];
    }

    /** BOM entfernen und notfalls von Windows-1252 (Excel) nach UTF-8 wandeln. */
    private function nachUtf8(string $inhalt): string
    {
        $inhalt = preg_replace('/^\xEF\xBB\xBF/', '', $inhalt) ?? $inhalt;

        if (! mb_check_encoding($inhalt, 'UTF-8')) {
            $inhalt = mb_convert_encoding($inhalt, 'UTF-8', 'Windows-1252');
        }

        return $inhalt;
    }

    /** Trennzeichen aus der ersten Zeile raten (Excel/DE liefert meist Semikolon). */
    private function trennzeichen(string $inhalt): string
    {
        $erste = strtok($inhalt, "\r\n") ?: '';

        $treffer = [
            ';' => substr_count($erste, ';'),
            ',' => substr_count($erste, ','),
            "\t" => substr_count($erste, "\t"),
        ];
        arsort($treffer);

        $bester = array_key_first($treffer);

        return $treffer[$bester] > 0 ? $bester : ';';
    }
}
