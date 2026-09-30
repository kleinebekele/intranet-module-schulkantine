<?php

namespace Intranet\Modules\Schulkantine\Support;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Intranet\Modules\Schulkantine\Models\Season;

/**
 * VORSCHAU der Monatsabrechnung für Linear – es wird nichts gesendet.
 *
 * Aufbau wie beim alten Menü&Serve (`MgEsGeld`): je Esser und Monat eine Zeile mit
 * Vertragsnehmer (AdrNr), Esser (AbwAdrNr), Vertragsart, Vertragsnummer, Anzahl 1,
 * Betrag = Gesamt = Monatssumme, Datum = letzter Tag des Monats. Der Betrag umfasst
 * Menüs, OGS und spontane Abholungen; Chip-Pfand ist NICHT enthalten (hat auch
 * Menü&Serve nie übertragen) und steht nur zur Information daneben.
 *
 * Nicht übertragbar (und deshalb getrennt aufgeführt): Personen ohne Linear-Herkunft
 * (Testkonten) und Personen ohne laufenden Vertrag in den importierten Vertragsdaten.
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

        $zeilen = [];
        $ausgeschlossen = [];
        foreach ($lines as $uid => $l) {
            $user = $users->get($uid);
            $betrag = round($l['menu_total'] + $l['ogs_total'] + $l['spontan_total'], 2);
            $pfand = round($l['pfand_out'] - $l['pfand_back'], 2);
            $basis = [
                'user' => $user,
                'betrag' => $betrag,
                'pfand' => $pfand,
                'menu' => $l['menu_total'],
                'ogs' => $l['ogs_total'],
                'spontan' => $l['spontan_total'],
            ];
            if ($betrag <= 0) {
                continue; // wie Menü&Serve: nur Beträge über 0
            }
            if (! $user || blank($user->externe_id)) {
                $ausgeschlossen[] = $basis + ['grund' => 'Konto ohne Linear-Herkunft (Testkonto)'];
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
                'AbwAdrNr' => (string) $user->externe_id,
                'Art' => (int) $vertrag->art,
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
}
