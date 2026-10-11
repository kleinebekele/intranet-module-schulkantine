<?php

namespace Intranet\Modules\Schulkantine\Support;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Intranet\Modules\Schulkantine\Models\Season;
use Intranet\Modules\Schulkantine\Models\Settlement;

/**
 * „Meine Abrechnung": je Saison und Monat die Beträge des Haushalts (Eltern + Kinder).
 *
 * Quellen je Esser und Monat:
 *  - Einzelposten aus dem Intranet (BillingService::detailsForUser) – Menüs, OGS-Tage,
 *    No-Shows, Spontankäufe, Pfand. Gibt es sie, sind sie der Betrag.
 *  - Abrechnung aus Linear (kantine_linear_charges, nachts importiert; ab 2026, auch
 *    aus dem alten Menü&Serve) – Betrag ohne Einzelposten, dazu der Zahlungsstand.
 *  - Sonst: keine Daten (das alte System hat nur die Abrechnungen ab 2026 geliefert).
 *
 * Status je Esser: aus Linear (offen/bezahlt), sonst „bezahlt"-Häkchen der Auswertung,
 * sonst „noch nicht abgerechnet". Je Monat zusammengefasst – es gibt je Esser einen
 * Lastschrifteinzug, deshalb „bezahlt", „Teilbetrag … offen" oder „Komplettbetrag … offen".
 */
class HaushaltsAbrechnung
{
    private BillingService $billing;

    public function __construct()
    {
        $this->billing = new BillingService;
    }

    /**
     * @param  Collection<int, User>  $haushalt
     * @return array{season: Season, esser: list<User>, monate: list<array>}
     */
    public function saison(Season $season, Collection $haushalt): array
    {
        $charges = $this->linearCharges($haushalt);
        $heute = Carbon::today();

        $monate = [];
        $cursor = $season->start_date->copy()->startOfMonth();
        while ($cursor->lte($season->end_date)) {
            [$y, $m] = [(int) $cursor->year, (int) $cursor->month];
            $zellen = [];
            foreach ($haushalt as $u) {
                $zellen[$u->id] = $this->zelle($season, $u, $y, $m, $charges);
            }
            $monate[] = [
                'jahr' => $y, 'monat' => $m, 'wert' => sprintf('%04d-%02d', $y, $m),
                'label' => self::monatLabel($y, $m),
                'zukunft' => $cursor->gt($heute->copy()->startOfMonth()),
                'zellen' => $zellen,
            ] + $this->summe($zellen, $cursor->gte($heute->copy()->startOfMonth()));
            $cursor->addMonth();
        }

        // Spalten nur für Esser, die in der Saison überhaupt etwas haben – sonst alle Kinder.
        $esser = $haushalt->filter(fn (User $u) => collect($monate)->contains(fn ($mo) => $mo['zellen'][$u->id]['betrag'] !== null))->values();
        if ($esser->isEmpty()) {
            $esser = $haushalt->values();
        }

        return ['season' => $season, 'esser' => $esser->all(), 'monate' => $monate];
    }

    /** Eine Zelle: Betrag, Herkunft, Status und – falls vorhanden – die Einzelposten. */
    private function zelle(Season $season, User $u, int $y, int $m, Collection $charges): array
    {
        $d = $this->billing->detailsForUser($season, $u->id, $y, $m);
        $hatPosten = $d['menu'] || $d['ogs']['dates'] || $d['spontan'] || $d['chips'];
        $charge = $charges->get($u->externe_id.'|'.$y.'|'.$m);

        // Ab dem Start der Intranet-Kantine wissen wir alles: nichts gefunden = nichts angefallen.
        $intranetWeiss = Carbon::create($y, $m, 1)->endOfMonth()->gte(Carbon::parse(self::intranetAb()));

        if ($hatPosten || ($intranetWeiss && ! $charge)) {
            $betrag = (float) $d['total'];
            $quelle = 'intranet';
        } elseif ($charge) {
            $betrag = (float) $charge['betrag'];
            $quelle = 'linear';
        } else {
            $betrag = null;
            $quelle = null;
        }

        if ($charge) {
            $status = $charge['offen'] > 0.005 ? 'offen' : 'bezahlt';
            $offen = $charge['offen'];
        } elseif ($betrag !== null && Settlement::where('user_id', $u->id)->where('year', $y)->where('month', $m)->exists()) {
            $status = 'bezahlt';
            $offen = 0.0;
        } else {
            $status = $betrag !== null && abs($betrag) > 0.005 ? 'nicht_abgerechnet' : null;
            $offen = 0.0;
        }

        return [
            'betrag' => $betrag, 'quelle' => $quelle, 'status' => $status, 'offen' => round($offen, 2),
            'details' => $quelle === 'intranet' ? $d : null,
        ];
    }

    /** Monatssumme und zusammengefasster Status. */
    private function summe(array $zellen, bool $laufend): array
    {
        $mitBetrag = array_filter($zellen, fn ($z) => $z['betrag'] !== null);
        if ($mitBetrag === []) {
            return ['gesamt' => null, 'status' => 'keine_daten', 'offen' => 0.0];
        }
        $gesamt = round(array_sum(array_column($mitBetrag, 'betrag')), 2);
        $abgerechnet = array_filter($mitBetrag, fn ($z) => in_array($z['status'], ['offen', 'bezahlt'], true));
        $offen = round(array_sum(array_column($abgerechnet, 'offen')), 2);

        if (abs($gesamt) < 0.005) {
            $status = 'nichts';
        } elseif ($abgerechnet === []) {
            $status = $laufend ? 'laufend' : 'nicht_abgerechnet';
        } elseif ($offen < 0.005) {
            $status = count($abgerechnet) < count(array_filter($mitBetrag, fn ($z) => abs($z['betrag']) > 0.005)) ? 'teils_abgerechnet' : 'bezahlt';
        } else {
            $status = $offen >= round(array_sum(array_column($abgerechnet, 'betrag')), 2) - 0.005 ? 'komplett_offen' : 'teil_offen';
        }

        return ['gesamt' => $gesamt, 'status' => $status, 'offen' => $offen];
    }

    /** Linear-Forderungen der Esser, Schlüssel "externe_id|jahr|monat" (mehrere Verträge addiert). */
    private function linearCharges(Collection $haushalt): Collection
    {
        $nummern = $haushalt->pluck('externe_id')->filter()->map(fn ($n) => (string) $n)->values();
        if ($nummern->isEmpty() || ! Schema::hasTable('kantine_linear_charges')) {
            return collect();
        }

        return DB::table('kantine_linear_charges')->whereIn('esser_adrnr', $nummern->all())->get()
            ->groupBy(fn ($c) => $c->esser_adrnr.'|'.(int) $c->jahr.'|'.(int) $c->monat)
            ->map(fn ($g) => ['betrag' => round($g->sum(fn ($c) => (float) $c->betrag), 2), 'offen' => round($g->sum(fn ($c) => (float) $c->offen), 2)]);
    }

    /**
     * Alle kostenpflichtigen Posten einer Zelle, nach Datum: Menüs/Gerichte (mit
     * No-Show), OGS-Tage, Spontankäufe, Chip-Pfand. Leer, wenn es keine Einzelposten gibt.
     *
     * @return list<array{datum: Carbon, was: string, hinweis: ?string, betrag: float}>
     */
    public static function posten(array $zelle): array
    {
        $d = $zelle['details'] ?? null;
        if (! $d) {
            return [];
        }
        $noShow = 'No-Show – nicht abgeholt, wird trotzdem berechnet';
        $posten = [];
        foreach ($d['menu'] as $r) {
            $posten[] = ['datum' => Carbon::parse($r['date']), 'was' => ($r['is_menu'] ? $r['menu_name'].': ' : '').$r['dish'],
                'hinweis' => match ($r['outcome']) {
                    'none' => $noShow,
                    'declined' => 'nicht genommen'.($r['reason'] ? ': '.$r['reason'] : ''),
                    'alternative' => 'Alternativmenü genommen',
                    default => null,
                }, 'betrag' => (float) $r['price']];
        }
        foreach ($d['ogs']['dates'] as $o) {
            $posten[] = ['datum' => Carbon::parse($o['date']), 'was' => 'OGS-Mittagessen',
                'hinweis' => match ($o['status']) { 'none' => $noShow, 'declined' => 'nicht gegessen', default => null },
                'betrag' => (float) $d['ogs']['price']];
        }
        foreach ($d['spontan'] as $s) {
            $posten[] = ['datum' => Carbon::parse($s['date']), 'was' => $s['dish'], 'hinweis' => 'spontan an der Ausgabe', 'betrag' => (float) $s['price']];
        }
        foreach ($d['chips'] as $c) {
            $posten[] = ['datum' => Carbon::parse($c['date']), 'was' => 'Chip-Pfand '.($c['type'] === 'aus' ? 'Ausgabe' : 'Rückgabe'), 'hinweis' => null, 'betrag' => (float) $c['amount']];
        }
        usort($posten, fn ($a, $b) => $a['datum'] <=> $b['datum']);

        return $posten;
    }

    /** Erster Tag, an dem die Kantine über das Intranet lief (vorher: Menü&Serve). */
    public static function intranetAb(): string
    {
        return (string) config('schulkantine.intranet_ab', '2026-10-12');
    }

    public static function monatLabel(int $y, int $m): string
    {
        static $namen = [1 => 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];

        return ($namen[$m] ?? (string) $m).' '.$y;
    }
}
