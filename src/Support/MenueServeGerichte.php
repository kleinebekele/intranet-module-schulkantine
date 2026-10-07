<?php

namespace Intranet\Modules\Schulkantine\Support;

use App\Ekkon\Ekkon;
use Illuminate\Support\Facades\DB;
use Intranet\Modules\Schulkantine\Models\Dish;

/**
 * Liest die Gerichte aus der alten Menü&Serve-Datenbank (nur lesend).
 *
 * Menü&Serve kennt keine Gerichte-Stammliste: In `MNUBAS` steht je Kalendertag
 * (`MNUBAS_FK_CALBAS` → `CALBAS`) und Menülinie (`MNUBAS_FK_MNUPRP` → `MNUPRP`)
 * ein freier Titel mit Notiz. Ein „Gericht" ist hier also ein verschiedener Titel;
 * gleiche Titel (Groß-/Kleinschreibung, Leerzeichen egal) werden zusammengefasst.
 * Die Menülinie trägt die Preise PRCCUR01..04 – in der Reihenfolge der
 * Vertragsarten aus der Menü&Serve-Konfiguration (27, 28, 29, 50).
 *
 * Snacks (Typ 5) und „Sonstiges" (Typ 70) bleiben außen vor.
 *
 * `MNUBAS_DT_ATTRIB01` ist die Fleischart, die das Menü&Serve-Terminal anzeigt. Die
 * Klartexte stehen nicht in der Datenbank, sondern im Programm (Auswahlliste im
 * Gericht-Dialog von MenuAndServe.exe, Reihenfolge 1–7); 0 = keine Angabe.
 */
class MenueServeGerichte
{
    /** Vertragsarten in der Reihenfolge der Preisspalten PRCCUR01..04. */
    public const PREIS_ARTEN = [27, 28, 29, 50];

    /** Fleischart (ATTRIB01) → Klartext. */
    public const ARTEN = [1 => 'Rind', 2 => 'Schwein', 3 => 'Rind/Schwein', 4 => 'Lamm', 5 => 'Geflügel', 6 => 'Fisch', 7 => 'Vegetarisch'];

    /**
     * Fleischart → Ernährungsformen, für die das Gericht NICHT geeignet ist (kantine_diets.name).
     * Halal vorsichtshalber bei jedem Fleisch – ob geschächtet wurde, weiß Menü&Serve nicht.
     */
    public const NICHT_FUER = [
        1 => ['vegetarisch', 'vegan', 'halal'],
        2 => ['vegetarisch', 'vegan', 'halal', 'schweinefleischfrei'],
        3 => ['vegetarisch', 'vegan', 'halal', 'schweinefleischfrei'],
        4 => ['vegetarisch', 'vegan', 'halal'],
        5 => ['vegetarisch', 'vegan', 'halal'],
        6 => ['vegetarisch', 'vegan'],
        7 => [],
    ];

    /** Menülinien-Typen, die keine Gerichte sind. */
    private const OHNE_TYPEN = [5, 70];

    /**
     * @return array{linien: array<string, array>, gerichte: list<array>}
     *
     * @throws \RuntimeException wenn Menü&Serve nicht lesbar ist
     */
    public function lesen(): array
    {
        if (! Ekkon::mssqlKonfiguriert()) {
            throw new \RuntimeException('Keine Verbindung zum SQL-Server von Linear/Menü&Serve konfiguriert.');
        }
        $db = DB::connection(Ekkon::mssqlConnection());
        $mus = (string) config('schulkantine.menueserve_db', 'MenuAndServe');
        $ohne = implode(', ', self::OHNE_TYPEN);

        $linien = [];
        foreach ($db->select(
            "SELECT CONVERT(varchar(36), MNUPRP_ID) id, MNUPRP_DT_TITLE titel, MNUPRP_DT_TYPE typ,
                    MNUPRP_DT_PRCCUR01 p1, MNUPRP_DT_PRCCUR02 p2, MNUPRP_DT_PRCCUR03 p3, MNUPRP_DT_PRCCUR04 p4
               FROM {$mus}.dbo.MNUPRP WHERE MNUPRP_DT_TYPE NOT IN ({$ohne})"
        ) as $l) {
            $preise = [];
            foreach (self::PREIS_ARTEN as $i => $art) {
                $p = round((float) $l->{'p'.($i + 1)}, 2);
                if ($p > 0) {
                    $preise[$art] = $p;
                }
            }
            $linien[strtolower($l->id)] = ['id' => strtolower($l->id), 'titel' => trim((string) $l->titel), 'preise' => $preise, 'anzahl' => 0];
        }

        // Datum je Eintrag aus dem Kalender – die Datumsspalte suchen wir uns, statt sie zu raten.
        $datumSpalte = $db->selectOne(
            "SELECT TOP 1 COLUMN_NAME c FROM {$mus}.INFORMATION_SCHEMA.COLUMNS
              WHERE TABLE_NAME = 'CALBAS' AND DATA_TYPE IN ('date', 'datetime', 'smalldatetime', 'datetime2')
              ORDER BY ORDINAL_POSITION"
        )?->c;
        $datum = $datumSpalte ? "CONVERT(varchar(10), MAX(c.[{$datumSpalte}]), 23)" : 'NULL';
        $kalender = $datumSpalte ? "LEFT JOIN {$mus}.dbo.CALBAS c ON c.CALBAS_ID = b.MNUBAS_FK_CALBAS" : '';

        // Notiz als nvarchar(400) – nvarchar(max) über ODBC ist fehleranfällig.
        $zeilen = $db->select(
            "SELECT CONVERT(varchar(36), b.MNUBAS_FK_MNUPRP) linie, LTRIM(RTRIM(b.MNUBAS_DT_TITLE)) titel, b.MNUBAS_DT_ATTRIB01 art, COUNT(*) n,
                    MAX(CONVERT(nvarchar(400), b.MNUBAS_DT_NOTE)) notiz, {$datum} zuletzt
               FROM {$mus}.dbo.MNUBAS b {$kalender}
              WHERE LTRIM(RTRIM(ISNULL(b.MNUBAS_DT_TITLE, ''))) <> ''
              GROUP BY b.MNUBAS_FK_MNUPRP, LTRIM(RTRIM(b.MNUBAS_DT_TITLE)), b.MNUBAS_DT_ATTRIB01"
        );

        $vorhanden = Dish::pluck('name')->mapWithKeys(fn ($n) => [self::schluessel($n) => true]);

        $gerichte = [];
        foreach ($zeilen as $z) {
            $linie = strtolower((string) $z->linie);
            if (! isset($linien[$linie])) {
                continue; // Snack, Sonstiges oder verwaiste Linie
            }
            $titel = preg_replace('/\s+/u', ' ', trim((string) $z->titel));
            $key = self::schluessel($titel);
            $g = $gerichte[$key] ?? ['key' => $key, 'titel' => $titel, 'anzahl' => 0, 'linien' => [], 'arten' => [], 'notiz' => '', 'zuletzt' => null,
                'vorhanden' => isset($vorhanden[$key])];
            $g['anzahl'] += (int) $z->n;
            $g['linien'][$linie] = ($g['linien'][$linie] ?? 0) + (int) $z->n;
            if (isset(self::ARTEN[(int) $z->art])) {
                $g['arten'][(int) $z->art] = ($g['arten'][(int) $z->art] ?? 0) + (int) $z->n;
            }
            $notiz = trim((string) $z->notiz);
            if ($notiz !== '' && mb_strlen($notiz) > mb_strlen($g['notiz'])) {
                $g['notiz'] = $notiz;
            }
            if ($z->zuletzt && (! $g['zuletzt'] || $z->zuletzt > $g['zuletzt'])) {
                $g['zuletzt'] = (string) $z->zuletzt;
            }
            $gerichte[$key] = $g;
            $linien[$linie]['anzahl']++;
        }

        // Hauptlinie = die, in der das Gericht am häufigsten stand.
        foreach ($gerichte as &$g) {
            arsort($g['linien']);
            $g['linie'] = array_key_first($g['linien']);
            // Fleischart = die häufigste angegebene (ohne Angabe: null).
            arsort($g['arten']);
            $g['art'] = array_key_first($g['arten']);
        }
        unset($g);

        usort($gerichte, fn ($a, $b) => [$b['zuletzt'] ?? '', $a['titel']] <=> [$a['zuletzt'] ?? '', $b['titel']]);

        return [
            'linien' => array_filter($linien, fn ($l) => $l['anzahl'] > 0),
            'gerichte' => $gerichte,
        ];
    }

    /** Vergleichsschlüssel: Kleinbuchstaben, Leerzeichen zusammengefasst. */
    public static function schluessel(string $name): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($name)));
    }
}
