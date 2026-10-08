<?php

namespace Intranet\Modules\Schulkantine\Support;

use App\Ekkon\Ekkon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Intranet\Modules\Schulkantine\Models\Dish;

/**
 * Liest die Menüs aus der alten Menü&Serve-Datenbank (nur lesend).
 *
 * Ein Menü&Serve-Menü ist EIN Eintrag in `MNUBAS` je Kalendertag (`CALBAS_DT_DATE`)
 * und Menülinie (`MNUPRP`): ein Kurztitel („Pizza") und eine Notiz, deren erste
 * Zeile die Hauptspeise und deren letzte Zeile die Nachspeise ist
 * („Pizza mit Salat … Obst"). Snack-Linien (Typ 5) nur auf Wunsch ($mitSnacks).
 *
 * `MNUBAS_DT_ATTRIB01` ist die Fleischart, die das Menü&Serve-Terminal anzeigt. Die
 * Klartexte stehen nicht in der Datenbank, sondern im Programm (Auswahlliste im
 * Gericht-Dialog von MenuAndServe.exe, Reihenfolge 1–7); 0 = keine Angabe.
 */
class MenueServeGerichte
{
    /** Fleischart (ATTRIB01) → Klartext. */
    public const ARTEN = [1 => 'Rind', 2 => 'Schwein', 3 => 'Rind/Schwein', 4 => 'Lamm', 5 => 'Geflügel', 6 => 'Fisch', 7 => 'Vegetarisch'];

    /** Menülinien-Typ der Snacks in Menü&Serve (MNUPRP_DT_TYPE). */
    public const TYP_SNACK = 5;

    /** Unser Gericht für alle Menü&Serve-Snacks (Live-ID; per Config änderbar). */
    public static function snackGericht(): int
    {
        return (int) config('schulkantine.menueserve_snack_gericht', 42);
    }

    /** Fleischart (ATTRIB01) → unsere Fleischart am Gericht (Dish::FLEISCHARTEN). */
    public const ART_ZU_FLEISCHART = [1 => 'rind', 2 => 'schwein', 3 => 'rind_schwein', 4 => 'lamm', 5 => 'gefluegel', 6 => 'fisch', 7 => 'vegetarisch'];

    /**
     * Fleischart je Gericht aus der ganzen Menü&Serve-Historie: über die gespeicherte
     * Zuordnung (Hauptspeise), sonst über den eindeutigen Namens-Treffer der Hauptspeise
     * bzw. des Titels. Je Gericht zählt die häufigste Angabe.
     *
     * @param  iterable<Dish>  $gerichte
     * @return array<int, string> dish_id → Fleischart
     */
    public function fleischartJeGericht(iterable $gerichte): array
    {
        $zuordnung = DB::table('kantine_menueserve_zuordnungen')->whereNotNull('hauptspeise_id')->pluck('hauptspeise_id', 'ms_id');
        $treffer = [];
        $zaehler = [];
        foreach ($this->menuesAb(Carbon::parse('2000-01-01')) as $m) {
            if (! $m['art']) {
                continue;
            }
            $id = $zuordnung[$m['ms_id']] ?? null;
            if (! $id) {
                $schluessel = $m['hauptspeise'].'|'.$m['titel'];
                if (! array_key_exists($schluessel, $treffer)) {
                    $treffer[$schluessel] = self::vorschlag($m['hauptspeise'], $gerichte) ?? self::vorschlag($m['titel'], $gerichte);
                }
                $id = $treffer[$schluessel];
            }
            if ($id) {
                $zaehler[$id][$m['art']] = ($zaehler[$id][$m['art']] ?? 0) + 1;
            }
        }

        $ergebnis = [];
        foreach ($zaehler as $id => $arten) {
            arsort($arten);
            $ergebnis[$id] = self::ART_ZU_FLEISCHART[array_key_first($arten)];
        }

        return $ergebnis;
    }

    /**
     * @return list<array{ms_id: string, datum: string, linie: string, titel: string, art: ?int, notiz: string, hauptspeise: ?string, nachspeise: ?string, snack: bool}>
     *
     * @throws \RuntimeException wenn Menü&Serve nicht lesbar ist
     */
    public function menuesAb(Carbon $ab, bool $mitSnacks = false): array
    {
        if (! Ekkon::mssqlKonfiguriert()) {
            throw new \RuntimeException('Keine Verbindung zum SQL-Server von Linear/Menü&Serve konfiguriert.');
        }
        $mus = (string) config('schulkantine.menueserve_db', 'MenuAndServe');

        // Datum nur als Text lesen (ODBC-Datetime-Falle), Notiz als nvarchar(400) statt max.
        $zeilen = DB::connection(Ekkon::mssqlConnection())->select(
            "SELECT CONVERT(varchar(36), b.MNUBAS_ID) id, CONVERT(varchar(10), k.CALBAS_DT_DATE, 23) tag,
                    p.MNUPRP_DT_TITLE linie, p.MNUPRP_DT_TYPE typ, b.MNUBAS_DT_ATTRIB01 art, LTRIM(RTRIM(b.MNUBAS_DT_TITLE)) titel,
                    CONVERT(nvarchar(400), b.MNUBAS_DT_NOTE) notiz
               FROM {$mus}.dbo.MNUBAS b
               JOIN {$mus}.dbo.CALBAS k ON k.CALBAS_ID = b.MNUBAS_FK_CALBAS
               JOIN {$mus}.dbo.MNUPRP p ON p.MNUPRP_ID = b.MNUBAS_FK_MNUPRP
              WHERE k.CALBAS_DT_DATE >= ? AND (? = 1 OR p.MNUPRP_DT_TYPE <> ?)
              ORDER BY k.CALBAS_DT_DATE, p.MNUPRP_DT_TYPE",
            [$ab->format('Y-m-d'), $mitSnacks ? 1 : 0, self::TYP_SNACK],
        );

        $menues = [];
        foreach ($zeilen as $z) {
            $notiz = trim((string) $z->notiz);
            $teile = array_values(array_filter(array_map('trim', preg_split('/\R/u', $notiz)), fn ($t) => $t !== ''));
            $menues[] = [
                'ms_id' => strtolower((string) $z->id),
                'datum' => (string) $z->tag,
                'linie' => trim((string) $z->linie),
                'titel' => (string) $z->titel,
                'art' => isset(self::ARTEN[(int) $z->art]) ? (int) $z->art : null,
                'notiz' => $notiz,
                'hauptspeise' => $teile[0] ?? ((string) $z->titel ?: null),
                'nachspeise' => count($teile) > 1 ? end($teile) : null,
                'snack' => (int) $z->typ === self::TYP_SNACK,
            ];
        }

        return $menues;
    }

    /**
     * Unser Gericht zu einem Menü&Serve-Text – nur wenn eindeutig: gleicher Name, sonst
     * genau ein Gericht, dessen Name im Text steckt oder den Text enthält.
     *
     * @param  iterable<Dish>  $gerichte
     */
    public static function vorschlag(?string $text, iterable $gerichte): ?int
    {
        $t = self::schluessel((string) $text);
        if ($t === '') {
            return null;
        }
        $gleich = [];
        $teil = [];
        foreach ($gerichte as $d) {
            $n = self::schluessel($d->name);
            if ($n === $t) {
                $gleich[] = $d->id;
            } elseif ($n !== '' && (str_contains($t, $n) || str_contains($n, $t))) {
                $teil[] = $d->id;
            }
        }
        if (count($gleich) === 1) {
            return $gleich[0];
        }

        return $gleich === [] && count($teil) === 1 ? $teil[0] : null;
    }

    /** Vergleichsschlüssel: Kleinbuchstaben, Leerzeichen zusammengefasst. */
    public static function schluessel(string $name): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($name)));
    }
}
