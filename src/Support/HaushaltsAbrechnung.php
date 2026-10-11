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
     * @return array Zeitraum (siehe zeitraum())
     */
    public function saison(Season $season, Collection $haushalt): array
    {
        return $this->zeitraum($season, $season->start_date->copy(), $season->end_date->copy(),
            'Saison „'.$season->name.'"', 's'.$season->id, $haushalt);
    }

    /**
     * Monate VOR der ersten Saison, für die das Altsystem Abrechnungen geliefert hat –
     * je Schuljahr (August–Juli) ein Zeitraum, neuestes zuerst.
     *
     * @return list<array>
     */
    public function altZeitraeume(Collection $haushalt, ?Carbon $vor): array
    {
        $monate = $this->linearCharges($haushalt)->keys()
            ->map(fn ($k) => explode('|', $k))
            ->map(fn ($t) => Carbon::create((int) $t[1], (int) $t[2], 1))
            ->filter(fn (Carbon $c) => ! $vor || $c->lt($vor->copy()->startOfMonth()))
            ->unique(fn (Carbon $c) => $c->format('Y-m'));

        return $monate->groupBy(fn (Carbon $c) => $c->month >= 8 ? $c->year : $c->year - 1)
            ->sortKeysDesc()
            ->map(function ($liste, $jahr) use ($haushalt, $vor) {
                $von = Carbon::create((int) $jahr, 8, 1);
                $bis = Carbon::create((int) $jahr + 1, 7, 31);
                if ($vor && $bis->gte($vor)) {
                    $bis = $vor->copy()->subDay();
                }

                return $this->zeitraum(null, $von, $bis,
                    'Schuljahr '.$jahr.'/'.substr((string) ($jahr + 1), 2).' (aus dem Altsystem)', 'a'.$jahr, $haushalt, nurMitDaten: true);
            })->values()->all();
    }

    /** Der Zeitraum (Saison oder Altsystem-Schuljahr), in dem ein Monat liegt – für das PDF. */
    public function monat(Collection $haushalt, int $y, int $m): ?array
    {
        $erster = Carbon::create($y, $m, 1);
        $season = Season::whereDate('start_date', '<=', $erster->copy()->endOfMonth())->whereDate('end_date', '>=', $erster)
            ->orderByDesc('start_date')->first();
        $z = $season
            ? $this->zeitraum($season, $erster, $erster->copy()->endOfMonth(), '', '', $haushalt)
            : $this->zeitraum(null, $erster, $erster->copy()->endOfMonth(), '', '', $haushalt);

        return ($z['monate'][0] ?? null) ? $z + ['monat' => $z['monate'][0]] : null;
    }

    /**
     * Ein Zeitraum: je Monat (neuester zuerst) die Zellen je Esser + Summe/Status.
     *
     * @param  Collection<int, User>  $haushalt
     * @return array{season: ?Season, titel: string, schluessel: string, hinweis: string, esser: list<User>, monate: list<array>}
     */
    private function zeitraum(?Season $season, Carbon $von, Carbon $bis, string $titel, string $schluessel, Collection $haushalt, bool $nurMitDaten = false): array
    {
        $charges = $this->linearCharges($haushalt);
        $diesMonat = Carbon::today()->startOfMonth();

        $monate = [];
        $cursor = $von->copy()->startOfMonth();
        while ($cursor->lte($bis)) {
            [$y, $m] = [(int) $cursor->year, (int) $cursor->month];
            $zellen = [];
            foreach ($haushalt as $u) {
                $zellen[$u->id] = $this->zelle($season, $u, $y, $m, $charges);
            }
            $summe = $this->summe($zellen, $cursor->gte($diesMonat));
            if (! $nurMitDaten || $summe['gesamt'] !== null) {
                $monate[] = [
                    'jahr' => $y, 'monat' => $m, 'wert' => sprintf('%04d-%02d', $y, $m),
                    'label' => self::monatLabel($y, $m),
                    'zukunft' => $cursor->gt($diesMonat),
                    // Abgeschlossen = vorbei – nur dann gibt es das PDF.
                    'abgeschlossen' => $cursor->lt($diesMonat),
                    // Nur Werte aus dem Altsystem (keine Einzelposten) – dafür gibt es kein PDF.
                    'nurAlt' => collect($zellen)->every(fn ($z) => $z['quelle'] !== 'intranet'),
                    'zellen' => $zellen,
                ] + $summe;
            }
            $cursor->addMonth();
        }
        $monate = array_reverse($monate); // neuester Monat oben

        // Spalten nur für Esser, die im Zeitraum überhaupt etwas haben – sonst alle.
        $esser = $haushalt->filter(fn (User $u) => collect($monate)->contains(fn ($mo) => $mo['zellen'][$u->id]['betrag'] !== null))->values();
        if ($esser->isEmpty()) {
            $esser = $haushalt->values();
        }

        return [
            'season' => $season, 'titel' => $titel, 'schluessel' => $schluessel,
            'hinweis' => ($season ?? Season::where('is_active', true)->first() ?? new Season)->hinweisOffen(),
            'esser' => $esser->all(), 'monate' => $monate,
        ];
    }

    /** Eine Zelle: Betrag, Herkunft, Status und – falls vorhanden – die Einzelposten. */
    private function zelle(?Season $season, User $u, int $y, int $m, Collection $charges): array
    {
        $charge = $charges->get($u->externe_id.'|'.$y.'|'.$m);
        $ab = Carbon::parse(self::intranetAb())->startOfDay();
        // Ab dem Start der Intranet-Kantine wissen wir alles: nichts gefunden = nichts angefallen.
        // Davor zählt allein, was Linear (aus dem alten System) abgerechnet hat – Intranet-
        // Posten von vorher wären geraten (z. B. OGS-Tage rückwirkend aus dem heutigen Abo).
        $intranetWeiss = $season !== null && Carbon::create($y, $m, 1)->endOfMonth()->gte($ab);

        $d = null;
        $alt = 0.0;
        $gesendet = 0.0;
        if ($intranetWeiss) {
            $d = $this->nurAb($this->billing->detailsForUser($season, $u->id, $y, $m), $ab);
            // Was Linear für den Monat darüber hinaus abgerechnet hat, kam aus dem alten
            // System (z. B. Oktober bis zum Umstieg) – das vom Intranet Gesendete abziehen.
            $gesendet = Schema::hasTable('kantine_linear_exports')
                ? (float) DB::table('kantine_linear_exports')->where('user_id', $u->id)->where('year', $y)->where('month', $m)->sum('betrag')
                : 0.0;
            if ($charge) {
                $alt = max(0.0, round($charge['betrag'] - $gesendet, 2));
            }
            $betrag = round($d['total'] + $alt, 2);
            $quelle = 'intranet';
        } elseif ($charge) {
            $betrag = (float) $charge['betrag'];
            $quelle = 'linear';
        } else {
            $betrag = null;
            $quelle = null;
        }

        // Intranet-Posten, die noch nicht an Linear gingen: der Monat ist nicht fertig
        // abgerechnet – auch wenn der Altsystem-Teil schon bezahlt ist.
        $intranetOffen = $d !== null && abs($d['total']) > 0.005 && $gesendet < 0.005;

        if ($intranetOffen) {
            $status = 'nicht_abgerechnet';
            $offen = 0.0;
        } elseif ($charge) {
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
            'details' => $d, 'alt' => $alt,
        ];
    }

    /** Einzelposten auf die Tage ab $ab beschränken und die Summen neu bilden. */
    private function nurAb(array $d, Carbon $ab): array
    {
        $ok = fn ($datum) => Carbon::parse($datum)->gte($ab);
        $d['menu'] = array_values(array_filter($d['menu'], fn ($r) => $ok($r['date'])));
        $d['spontan'] = array_values(array_filter($d['spontan'], fn ($r) => $ok($r['date'])));
        $d['chips'] = array_values(array_filter($d['chips'], fn ($r) => $ok($r['date'])));
        $d['ogs']['dates'] = array_values(array_filter($d['ogs']['dates'], fn ($r) => $ok($r['date'])));
        $d['ogs']['days'] = count($d['ogs']['dates']);
        $d['ogs']['total'] = $d['ogs']['days'] * (float) $d['ogs']['price'];
        $d['menu_total'] = round(array_sum(array_column($d['menu'], 'price')), 2);
        $d['spontan_total'] = round(array_sum(array_column($d['spontan'], 'price')), 2);
        $d['pfand_net'] = round(array_sum(array_column($d['chips'], 'amount')), 2);
        $d['total'] = round($d['menu_total'] + $d['ogs']['total'] + $d['spontan_total'] + $d['pfand_net'], 2);

        return $d;
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

        // Teil des Monats, den noch das alte System abgerechnet hat (ohne Einzelposten).
        if (($zelle['alt'] ?? 0) > 0.005) {
            array_unshift($posten, ['datum' => Carbon::parse($posten[0]['datum'] ?? self::intranetAb())->startOfMonth(),
                'was' => 'Abrechnung aus dem alten System (bis zum Umstieg)', 'hinweis' => 'Einzelposten liegen nicht vor',
                'betrag' => (float) $zelle['alt']]);
        }

        return $posten;
    }

    /**
     * Erster Tag, ab dem das Intranet abrechnet (vorher rechnete Menü&Serve ab – bis
     * dahin Testphase). Davor gilt allein, was aus dem Altsystem übernommen wurde, und
     * der Linear-Export sendet nichts.
     */
    public static function intranetAb(): string
    {
        return (string) config('schulkantine.intranet_ab', '2026-11-01');
    }

    public static function monatLabel(int $y, int $m): string
    {
        static $namen = [1 => 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];

        return ($namen[$m] ?? (string) $m).' '.$y;
    }
}
