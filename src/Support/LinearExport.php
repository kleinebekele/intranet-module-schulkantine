<?php

namespace Intranet\Modules\Schulkantine\Support;

use App\Ekkon\Ekkon;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Intranet\Modules\Schulkantine\Models\Season;

/**
 * Monatsabrechnung für Linear (`MgEsGeld`): Vorschau, Versand und Gegenprüfung.
 *
 * Aufbau wie beim alten Menü&Serve: je Esser und Monat eine Zeile mit
 * Vertragsnehmer (AdrNr), Esser (AbwAdrNr), Vertragsart, Vertragsnummer, Anzahl 1,
 * Betrag = Gesamt = Monatssumme, Datum = DatumU = letzter Tag des Monats. Die
 * Spalten Soll/DatumSoll/ZeitSoll füllt Linear selbst beim Sollstellungslauf.
 * Der Betrag umfasst Menüs, OGS, spontane Abholungen UND Chip-Pfand (Ausgabe +,
 * Rückgabe −). Beschreibung „Kantine Intranet" – bewusst anders als Menü&Serve.
 *
 * Gesendet wird immer der ganze Monat auf einmal ({@see sendenAlle()}): automatisch
 * durch den Task Linear/KantineAbrechnung am letzten Kantinentag nach Abbestellschluss,
 * im Notfall per Knopf in der Auswertung. Jeder Esser höchstens einmal je Monat.
 * Nicht übertragbar (getrennt aufgeführt): Testkonten ohne Linear-Herkunft, Esser
 * ohne laufenden Vertrag, Beträge ≤ 0 (Gutschriften bitte von Hand in Linear).
 */
class LinearExport
{
    public const BESCHREIBUNG = 'Kantine Intranet';

    /** Frühester Sendezeitpunkt am letzten Kantinentag, falls der Abbestellschluss früher liegt. */
    public const SENDEZEIT = '16:00';

    /**
     * Ab wann darf der Monat an Linear? Am letzten Öffnungstag des Monats, sobald der
     * Abbestellschluss vorbei ist – frühestens zur $uhrzeit, damit spontane Abholungen
     * und Pfand des letzten Tages noch mitgehen. null = der Monat hat keinen Kantinentag.
     */
    public function sendezeitpunkt(Season $season, int $year, int $month, string $uhrzeit = self::SENDEZEIT): ?Carbon
    {
        $tag = Carbon::create($year, $month, 1)->endOfMonth()->startOfDay();
        $erster = Carbon::create($year, $month, 1)->startOfDay();
        while ($tag->gte($erster) && ! $season->isOpenOn($tag)) {
            $tag->subDay();
        }
        if ($tag->lt($erster)) {
            return null;
        }

        $abbestellschluss = (new DeadlineService)->cancelDeadline($season, $tag);
        [$h, $m] = array_map('intval', explode(':', $uhrzeit.':0'));
        $frueheste = $tag->copy()->setTime($h, $m);

        return $abbestellschluss->gt($frueheste) ? $abbestellschluss : $frueheste;
    }

    /**
     * @return array{zeilen: list<array>, ausgeschlossen: list<array>, datum: Carbon, summe: float, vertraegeStand: bool}
     */
    public function vorschau(Season $season, int $year, int $month): array
    {
        $datum = Carbon::create($year, $month, 1)->endOfMonth()->startOfDay();
        $lines = (new BillingService)->forMonth($season, $year, $month);
        $users = User::whereIn('id', $lines->keys())->get()->keyBy('id');

        $vertraegeStand = Schema::hasTable('kantine_linear_contracts')
            && DB::table('kantine_linear_contracts')->exists();
        $vertraege = $vertraegeStand
            ? DB::table('kantine_linear_contracts')
                ->whereIn('esser_adrnr', $users->pluck('externe_id')->filter()->values())
                ->get()->groupBy('esser_adrnr')
            : collect();
        $preise = LinearPreise::aktuell() ?? [];
        $gesendet = Schema::hasTable('kantine_linear_exports')
            ? DB::table('kantine_linear_exports')->where('year', $year)->where('month', $month)->get()->keyBy('user_id')
            : collect();

        $zeilen = [];
        $ausgeschlossen = [];
        foreach ($lines as $uid => $l) {
            $user = $users->get($uid);
            $essen = round($l['menu_total'] + $l['ogs_total'] + $l['spontan_total'], 2);
            $pfand = round($l['pfand_out'] - $l['pfand_back'], 2);
            $betrag = round($essen + $pfand, 2);
            $basis = [
                'user' => $user,
                'betrag' => $betrag,
                'pfand' => $pfand,
                'menu' => $l['menu_total'],
                'ogs' => $l['ogs_total'],
                'spontan' => $l['spontan_total'],
                'export' => $gesendet->get($uid),
            ];
            if ($betrag == 0.0) {
                continue;
            }
            if (! $user || blank($user->externe_id)) {
                $ausgeschlossen[] = $basis + ['grund' => 'Konto ohne Linear-Herkunft (Testkonto)'];
                continue;
            }
            if ($betrag < 0) {
                $ausgeschlossen[] = $basis + ['grund' => 'Gutschrift (Pfand-Rückgabe) – bitte von Hand in Linear'];
                continue;
            }

            // Bei mehreren Verträgen den mit dem günstigsten Preis – wie bei der Preisfindung.
            $vertrag = collect($vertraege->get((string) $user->externe_id, []))
                ->sortBy(fn ($v) => [$preise[$v->art] ?? PHP_FLOAT_MAX, $v->art])
                ->first();
            if (! $vertrag) {
                $ausgeschlossen[] = $basis + ['grund' => $vertraegeStand ? 'kein laufender Vertrag in Linear' : 'Vertragsdaten noch nicht importiert'];
                continue;
            }

            $zeilen[] = $basis + [
                'AdrNr' => $vertrag->adrnr,
                'Art' => (int) $vertrag->art,
                'DatumU' => $datum,
                'AbwAdrNr' => (string) $user->externe_id,
                'VertragNr' => $vertrag->vertrag_nr,
                'Anzahl' => 1,
                'Betrag' => $betrag,
                'Gesamt' => $betrag,
                'Beschreibung' => self::BESCHREIBUNG,
                'Datum' => $datum,
            ];
        }

        usort($zeilen, fn ($a, $b) => [$a['AdrNr'], $a['AbwAdrNr']] <=> [$b['AdrNr'], $b['AbwAdrNr']]);

        return [
            'zeilen' => $zeilen,
            'ausgeschlossen' => $ausgeschlossen,
            'datum' => $datum,
            'summe' => round(array_sum(array_column($zeilen, 'Betrag')), 2),
            'vertraegeStand' => $vertraegeStand,
        ];
    }

    /**
     * Zahlungsstand der gesendeten Zeilen aus Linear (`MgSolln`) anhängen und den
     * Bezahlt-Status der Auswertung (`kantine_settlements`) danach ausrichten.
     *
     * Je Vertragsnehmer, Esser, Art, Vertrag und Monat legt Linear beim
     * Sollstellungslauf genau eine Forderung an: keine Zeile = noch nicht in Rechnung
     * gestellt, Offen > 0 = offen (auch eine geplatzte Lastschrift), Offen = 0 = bezahlt.
     * Ohne Forderung wird gegengeprüft, ob die Zeile noch in `MgEsGeld` steht – sonst 'fehlt'.
     * `MgLastRuck` wird bei der Schule nicht geführt. Linear nicht lesbar → Zustand null.
     *
     * @param  array{zeilen: list<array>}  $vorschau
     * @return array  $vorschau mit 'linear' je Zeile und 'linearLesbar'
     */
    public function mitZahlungsstand(array $vorschau, Season $season, int $year, int $month): array
    {
        $gesendet = array_filter($vorschau['zeilen'], fn ($z) => $z['export'] !== null);
        $vorschau['linearLesbar'] = true;
        if ($gesendet === []) {
            return $vorschau;
        }

        try {
            if (! Ekkon::mssqlKonfiguriert()) {
                throw new \RuntimeException('keine Linear-Verbindung');
            }
            $tabelle = (string) config('schulkantine.linear_solln_tabelle', 'Linear2.dbo.MgSolln');
            $arten = implode(', ', array_keys(LinearPreise::ARTEN));
            // Nur Zahlen/Texte lesen (ODBC-Datetime-Falle) – Jahr/Monat als Filter.
            $soll = collect(DB::connection(Ekkon::mssqlConnection())->select(
                "SELECT AdrNr, AbwAdrNr, Art, VertragNr, Betrag, Bezahlt, Offen FROM {$tabelle}
                  WHERE Jahr = ? AND Monat = ? AND Art IN ({$arten})",
                [$year, $month],
            ))->groupBy(fn ($s) => ((int) $s->AdrNr).'|'.((int) $s->AbwAdrNr).'|'.((int) $s->Art).'|'.trim((string) $s->VertragNr));

            // Noch nicht abgerechnet? Dann muss die gesendete Zeile wenigstens in MgEsGeld stehen.
            $esgeld = (string) config('schulkantine.linear_esgeld_tabelle', 'Linear2.dbo.MgEsGeld');
            $uebermittelt = collect(DB::connection(Ekkon::mssqlConnection())->select(
                "SELECT AbwAdrNr FROM {$esgeld} WHERE Beschreibung = ? AND CONVERT(date, Datum) = ?",
                [self::BESCHREIBUNG, $vorschau['datum']->format('Y-m-d')],
            ))->map(fn ($r) => (int) $r->AbwAdrNr)->flip();
        } catch (\Throwable $e) {
            report($e);
            $vorschau['linearLesbar'] = false;

            return $vorschau;
        }

        foreach ($vorschau['zeilen'] as $i => $z) {
            $export = $z['export'];
            if ($export === null) {
                continue;
            }
            $treffer = $soll->get(((int) $export->adrnr).'|'.((int) $export->abw_adrnr).'|'.((int) $export->art).'|'.trim((string) $export->vertrag_nr));
            if (! $treffer) {
                // 'nicht' = übermittelt, noch nicht abgerechnet · 'fehlt' = in Linear nirgends zu finden
                $zustand = $uebermittelt->has((int) $export->abw_adrnr) ? 'nicht' : 'fehlt';
                $status = ['zustand' => $zustand, 'betrag' => null, 'offen' => null];
            } else {
                $betrag = round($treffer->sum(fn ($s) => (float) $s->Betrag), 2);
                $offen = round($treffer->sum(fn ($s) => (float) $s->Offen), 2);
                $status = ['zustand' => $offen > 0 ? 'offen' : 'bezahlt', 'betrag' => $betrag, 'offen' => $offen];
            }
            $vorschau['zeilen'][$i]['linear'] = $status;
            $this->bezahltAusrichten($season, $z['user']->id, $year, $month, $status);
        }

        return $vorschau;
    }

    /** Bezahlt-Häkchen der Auswertung = „in Linear bezahlt" (nur für gesendete Zeilen). */
    private function bezahltAusrichten(Season $season, int $userId, int $year, int $month, array $status): void
    {
        $schluessel = ['user_id' => $userId, 'year' => $year, 'month' => $month];
        if ($status['zustand'] === 'bezahlt') {
            $vorhanden = DB::table('kantine_settlements')->where($schluessel)->first();
            if (! $vorhanden) {
                DB::table('kantine_settlements')->insert($schluessel + [
                    'season_id' => $season->id, 'amount' => $status['betrag'], 'paid_at' => now(),
                    'marked_by' => null, 'created_at' => now(), 'updated_at' => now(),
                ]);
            } elseif (round((float) $vorhanden->amount, 2) !== $status['betrag']) {
                DB::table('kantine_settlements')->where('id', $vorhanden->id)->update(['amount' => $status['betrag'], 'updated_at' => now()]);
            }
        } else {
            DB::table('kantine_settlements')->where($schluessel)->delete();
        }
    }

    /**
     * Den ganzen Monat an Linear senden: jede übertragbare Zeile, die noch nicht
     * gesendet ist (INSERT in MgEsGeld). Je Esser und Monat höchstens einmal; steht in
     * Linear schon eine Zeile dieses Essers mit derselben Beschreibung und demselben
     * Datum, wird nur vermerkt. Eine Sperre verhindert, dass Task und Knopf gleichzeitig senden.
     *
     * @param  int|null  $durch  Benutzer-ID beim Knopf, null beim Task
     * @return array{gesendet: int, summe: float, vermerkt: int, fehler: list<string>}
     *
     * @throws \RuntimeException wenn Linear nicht erreichbar ist oder gerade schon gesendet wird
     */
    public function sendenAlle(Season $season, int $year, int $month, ?int $durch = null): array
    {
        if (! Ekkon::mssqlKonfiguriert()) {
            throw new \RuntimeException('Keine Verbindung zu Linear konfiguriert.');
        }
        $sperre = Cache::lock("kantine-linear-senden-{$year}-{$month}", 600);
        if (! $sperre->get()) {
            throw new \RuntimeException('Dieser Monat wird gerade schon an Linear gesendet.');
        }

        try {
            $tabelle = (string) config('schulkantine.linear_esgeld_tabelle', 'Linear2.dbo.MgEsGeld');
            $linear = DB::connection(Ekkon::mssqlConnection());
            $ergebnis = ['gesendet' => 0, 'summe' => 0.0, 'vermerkt' => 0, 'fehler' => []];

            foreach ($this->vorschau($season, $year, $month)['zeilen'] as $zeile) {
                if ($zeile['export'] !== null) {
                    continue;
                }
                $name = $zeile['user']->name;
                $datum = $zeile['Datum']->format('Y-m-d');
                $protokoll = [
                    'user_id' => $zeile['user']->id, 'year' => $year, 'month' => $month,
                    'adrnr' => $zeile['AdrNr'], 'abw_adrnr' => $zeile['AbwAdrNr'], 'art' => $zeile['Art'],
                    'vertrag_nr' => $zeile['VertragNr'], 'betrag' => $zeile['Betrag'], 'datum' => $datum,
                    'sent_at' => now(), 'sent_by' => $durch, 'created_at' => now(), 'updated_at' => now(),
                ];

                try {
                    $schon = (int) ($linear->selectOne(
                        "SELECT COUNT(*) AS n FROM {$tabelle} WHERE AbwAdrNr = ? AND Beschreibung = ? AND CONVERT(date, Datum) = ?",
                        [(int) $zeile['AbwAdrNr'], self::BESCHREIBUNG, $datum],
                    )->n ?? 0);

                    if ($schon > 0) {
                        DB::table('kantine_linear_exports')->insert($protokoll + ['hinweis' => 'stand bereits in Linear – nicht erneut geschrieben']);
                        $ergebnis['vermerkt']++;

                        continue;
                    }

                    $linear->insert(
                        "INSERT INTO {$tabelle} (AdrNr, Art, DatumU, AbwAdrNr, VertragNr, Anzahl, Betrag, Gesamt, Beschreibung, Datum)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                        [
                            (int) $zeile['AdrNr'], $zeile['Art'], $datum, (int) $zeile['AbwAdrNr'],
                            (int) $zeile['VertragNr'], 1, $zeile['Betrag'], $zeile['Gesamt'],
                            self::BESCHREIBUNG, $datum,
                        ],
                    );
                    DB::table('kantine_linear_exports')->insert($protokoll);
                    $ergebnis['gesendet']++;
                    $ergebnis['summe'] = round($ergebnis['summe'] + $zeile['Betrag'], 2);
                } catch (\Throwable $e) {
                    report($e);
                    $ergebnis['fehler'][] = "{$name}: {$e->getMessage()}";
                }
            }

            return $ergebnis;
        } finally {
            $sperre->release();
        }
    }
}
