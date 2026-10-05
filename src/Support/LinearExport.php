<?php

namespace Intranet\Modules\Schulkantine\Support;

use App\Ekkon\Ekkon;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Intranet\Modules\Schulkantine\Models\Season;

/**
 * Monatsabrechnung für Linear (`MgEsGeld`): Vorschau und Einzelversand.
 *
 * Aufbau wie beim alten Menü&Serve: je Esser und Monat eine Zeile mit
 * Vertragsnehmer (AdrNr), Esser (AbwAdrNr), Vertragsart, Vertragsnummer, Anzahl 1,
 * Betrag = Gesamt = Monatssumme, Datum = DatumU = letzter Tag des Monats. Die
 * Spalten Soll/DatumSoll/ZeitSoll füllt Linear selbst beim Sollstellungslauf.
 * Der Betrag umfasst Menüs, OGS, spontane Abholungen UND Chip-Pfand (Ausgabe +,
 * Rückgabe −). Beschreibung „Kantine Intranet" – bewusst anders als Menü&Serve.
 *
 * Gesendet wird ausschließlich per Knopf je Zeile ({@see senden()}), nie automatisch.
 * Nicht übertragbar (getrennt aufgeführt): Testkonten ohne Linear-Herkunft, Esser
 * ohne laufenden Vertrag, Beträge ≤ 0 (Gutschriften bitte von Hand in Linear).
 */
class LinearExport
{
    public const BESCHREIBUNG = 'Kantine Intranet';

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
                $status = ['zustand' => 'nicht', 'betrag' => null, 'offen' => null];
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
     * EINE Zeile an Linear senden (INSERT in MgEsGeld). Rechnet den Monat frisch,
     * sendet je Esser und Monat höchstens einmal und prüft vorher in Linear, ob dort
     * schon eine Zeile dieses Essers mit derselben Beschreibung und demselben Datum steht.
     *
     * @return string Erfolgsmeldung
     *
     * @throws \RuntimeException mit verständlicher Meldung
     */
    public function senden(Season $season, User $esser, int $year, int $month, User $durch): string
    {
        if (DB::table('kantine_linear_exports')->where('user_id', $esser->id)->where('year', $year)->where('month', $month)->exists()) {
            throw new \RuntimeException("{$esser->name} ist für {$month}/{$year} bereits an Linear gesendet.");
        }
        $zeile = collect($this->vorschau($season, $year, $month)['zeilen'])
            ->first(fn ($z) => $z['user']?->id === $esser->id);
        if (! $zeile) {
            throw new \RuntimeException("Für {$esser->name} gibt es in {$month}/{$year} keine übertragbare Zeile.");
        }
        if (! Ekkon::mssqlKonfiguriert()) {
            throw new \RuntimeException('Keine Verbindung zu Linear konfiguriert.');
        }

        $tabelle = (string) config('schulkantine.linear_esgeld_tabelle', 'Linear2.dbo.MgEsGeld');
        $datum = $zeile['Datum']->format('Y-m-d');
        $linear = DB::connection(Ekkon::mssqlConnection());

        $schon = (int) ($linear->selectOne(
            "SELECT COUNT(*) AS n FROM {$tabelle} WHERE AbwAdrNr = ? AND Beschreibung = ? AND CONVERT(date, Datum) = ?",
            [(int) $zeile['AbwAdrNr'], self::BESCHREIBUNG, $datum],
        )->n ?? 0);

        $protokoll = [
            'user_id' => $esser->id, 'year' => $year, 'month' => $month,
            'adrnr' => $zeile['AdrNr'], 'abw_adrnr' => $zeile['AbwAdrNr'], 'art' => $zeile['Art'],
            'vertrag_nr' => $zeile['VertragNr'], 'betrag' => $zeile['Betrag'], 'datum' => $datum,
            'sent_at' => now(), 'sent_by' => $durch->id, 'created_at' => now(), 'updated_at' => now(),
        ];

        if ($schon > 0) {
            DB::table('kantine_linear_exports')->insert($protokoll + ['hinweis' => 'stand bereits in Linear – nicht erneut geschrieben']);
            throw new \RuntimeException("In Linear steht für {$esser->name} ({$datum}) schon eine Zeile „".self::BESCHREIBUNG."\" – nicht erneut geschrieben, als gesendet vermerkt.");
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

        return sprintf('%s: %s € für %02d/%d an Linear gesendet.', $esser->name, number_format($zeile['Betrag'], 2, ',', '.'), $month, $year);
    }
}
