<?php

namespace Intranet\Modules\Schulkantine\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Intranet\Modules\Schulkantine\Models\CustomerGroup;
use Intranet\Modules\Schulkantine\Models\Order;
use Intranet\Modules\Schulkantine\Models\Season;
use Intranet\Modules\Schulkantine\Support\BillingService;
use Intranet\Modules\Schulkantine\Support\HaushaltsAbrechnung;

/**
 * „Meine Abrechnung" – Selbstbedienung für jeden eingeloggten Nutzer: die Kosten
 * des eigenen Haushalts (er selbst + seine Kinder) je Monat. Bewusst getrennt von
 * der Admin-„Auswertung" (ReportController): eigener Menüpunkt, eigenes Routen-
 * Präfix (abrechnung.*), damit die Sichtbarkeit unabhängig ist und Eltern hier
 * nur ihren eigenen Haushalt sehen.
 *
 * Rechnet nichts selbst ab – die Zahlen kommen aus dem BillingService, dieselbe
 * Grundlage wie die Admin-Auswertung.
 */
class MyBillingController
{
    /**
     * Übersicht: je Saison eine Tabelle Monat × Esser (Betrag) + Gesamt + Status +
     * PDF. Die aktive Saison oben, frühere darunter (aufklappbar).
     */
    public function index(Request $request)
    {
        $viewer = $request->user();
        $aktiv = Season::where('is_active', true)->first();
        if (! $aktiv) {
            return view('schulkantine::billing.index', ['season' => null]);
        }

        $haushalt = $this->household($viewer);
        $rechner = new HaushaltsAbrechnung;
        $frueher = Season::where('id', '!=', $aktiv->id)->whereDate('start_date', '<', $aktiv->start_date)
            ->orderByDesc('start_date')->get();
        // Was das Altsystem vor der ersten Saison abgerechnet hat (ab 2026) – je Schuljahr
        // darunter, wie eine frühere Saison.
        $ersteSaison = Season::orderBy('start_date')->value('start_date');

        return view('schulkantine::billing.index', [
            'season' => $aktiv,
            'aktuell' => $rechner->saison($aktiv, $haushalt),
            'frueher' => array_merge(
                $frueher->map(fn (Season $s) => $rechner->saison($s, $haushalt))->all(),
                $rechner->altZeitraeume($haushalt, $ersteSaison ? Carbon::parse($ersteSaison) : null),
            ),
            'linearStand' => \Intranet\Modules\Schulkantine\Models\Setting::current()->linear_charges_at,
        ]);
    }

    /** Monatsabrechnung des Haushalts als PDF zum Abheften – nur für abgeschlossene Monate. */
    public function pdf(Request $request)
    {
        $viewer = $request->user();
        abort_unless(preg_match('/^(\d{4})-(\d{2})$/', (string) $request->query('monat'), $mm), 404);
        [$y, $m] = [(int) $mm[1], (int) $mm[2]];

        $daten = (new HaushaltsAbrechnung)->monat($this->household($viewer), $y, $m);
        abort_unless($daten && $daten['monat']['abgeschlossen'] && ! $daten['monat']['nurAlt'], 404,
            'Ein PDF gibt es nur für abgeschlossene Monate mit Einzelposten.');

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('schulkantine::billing.pdf', [
            'titel' => $daten['season']?->name ? 'Saison „'.$daten['season']->name.'"' : 'aus dem Altsystem',
            'hinweis' => $daten['hinweis'], 'esser' => $daten['esser'], 'monat' => $daten['monat'],
            'viewer' => $viewer, 'erstellt' => Carbon::now(),
        ])->setPaper('a4', 'portrait');
        $monat = $daten['monat'];

        return $pdf->download('kantine-abrechnung-'.$monat['wert'].'.pdf');
    }

    /**
     * In Linear abgerechnete Essens-Monate des Haushalts (ab 2026, auch aus
     * Menü&Serve) – nachts vom Linear-Import abgelegt. Je Person, neueste zuerst.
     *
     * @return array{linearHistorie: array, linearStand: ?\Illuminate\Support\Carbon}
     */
    private function linearHistorie(User $viewer): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('kantine_linear_charges')) {
            return ['linearHistorie' => [], 'linearStand' => null];
        }
        $haushalt = $this->household($viewer)->filter(fn ($u) => filled($u->externe_id))->keyBy(fn ($u) => (string) $u->externe_id);
        $eigeneNr = filled($viewer->externe_id) ? (string) $viewer->externe_id : null;
        if ($haushalt->isEmpty() && $eigeneNr === null) {
            return ['linearHistorie' => [], 'linearStand' => null];
        }

        // Esser aus dem Haushalt ODER Forderungen, bei denen ich Vertragsnehmer bin.
        $zeilen = \Illuminate\Support\Facades\DB::table('kantine_linear_charges')
            ->where(function ($q) use ($haushalt, $eigeneNr) {
                $q->whereIn('esser_adrnr', $haushalt->keys()->all());
                if ($eigeneNr !== null) {
                    $q->orWhere('adrnr', $eigeneNr);
                }
            })
            ->orderByDesc('jahr')->orderByDesc('monat')
            ->get();

        $historie = [];
        foreach ($zeilen->groupBy('esser_adrnr') as $esserNr => $monate) {
            $historie[] = [
                'name' => $haushalt->get($esserNr)?->name ?? 'Esser '.$esserNr,
                'monate' => $monate->map(fn ($m) => [
                    'label' => $this->monthLabel((int) $m->jahr, (int) $m->monat),
                    'betrag' => (float) $m->betrag,
                    'offen' => (float) $m->offen,
                ])->all(),
            ];
        }

        return [
            'linearHistorie' => $historie,
            'linearStand' => \Intranet\Modules\Schulkantine\Models\Setting::current()->linear_charges_at,
        ];
    }

    /** Einzelposten einer Person des eigenen Haushalts im gewählten Monat. */
    public function show(Request $request, User $user)
    {
        $viewer = $request->user();
        abort_unless($this->mayView($viewer, $user), 403, 'Kein Zugriff auf diese Abrechnung.');

        $season = Season::where('is_active', true)->firstOrFail();
        [$year, $month] = $this->resolveMonth($request, $season, $viewer);

        $details = (new BillingService)->detailsForUser($season, $user->id, $year, $month);
        $group = CustomerGroup::forUser($user);

        return view('schulkantine::billing.show', $details + [
            'season' => $season,
            'user' => $user,
            'group' => $group,
            'year' => $year,
            'month' => $month,
            'monthLabel' => $this->monthLabel($year, $month),
            'monthValue' => sprintf('%04d-%02d', $year, $month),
        ]);
    }

    // ----------------------------------------------------------------- Helfer

    /** Die Esser des Haushalts – Kinder zuerst, der Nutzer selbst zuletzt. */
    private function household(User $user): \Illuminate\Support\Collection
    {
        $children = $user->children()->with('roles')->orderBy('name')->get();

        return $children->concat([$user])->unique('id')->values();
    }

    /** Darf der Betrachter diese Person sehen? Sie selbst oder ein eigenes Kind. */
    private function mayView(User $viewer, User $target): bool
    {
        return $viewer->id === $target->id || $viewer->children()->whereKey($target->id)->exists();
    }

    /**
     * Monat aus monat=YYYY-MM (Query) oder Standard: der jüngste Monat, in dem der
     * Haushalt eine Bestellung hatte – sonst der aktuelle Monat.
     *
     * @return array{0:int,1:int}
     */
    private function resolveMonth(Request $request, Season $season, User $viewer): array
    {
        $raw = (string) $request->query('monat', '');
        if (preg_match('/^(\d{4})-(\d{1,2})$/', $raw, $m)) {
            $year = (int) $m[1];
            $month = (int) $m[2];
            if ($month >= 1 && $month <= 12) {
                return [$year, $month];
            }
        }

        $ids = $this->household($viewer)->pluck('id');
        $latest = Order::where('season_id', $season->id)
            ->whereIn('user_id', $ids)
            ->max('date');
        $c = $latest ? Carbon::parse($latest) : Carbon::now();

        return [(int) $c->year, (int) $c->month];
    }

    /** Alle Monate der Saison als Auswahl (Wert YYYY-MM + deutsches Label). */
    private function seasonMonths(Season $season): array
    {
        $months = [];
        $cursor = $season->start_date->copy()->startOfMonth();
        $end = $season->end_date->copy()->startOfMonth();
        while ($cursor->lte($end)) {
            $months[] = [
                'value' => $cursor->format('Y-m'),
                'label' => $this->monthLabel((int) $cursor->year, (int) $cursor->month),
            ];
            $cursor->addMonth();
        }

        return $months;
    }

    private function monthLabel(int $year, int $month): string
    {
        static $names = [1 => 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni',
            'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];

        return ($names[$month] ?? (string) $month).' '.$year;
    }
}
