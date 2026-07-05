<?php

namespace Intranet\Modules\Schulkantine\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Intranet\Modules\Schulkantine\Models\Order;
use Intranet\Modules\Schulkantine\Models\Season;
use Intranet\Modules\Schulkantine\Models\Settlement;
use Intranet\Modules\Schulkantine\Support\BillingService;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Auswertung & Abrechnung (Phase 5). Nur für Administratoren.
 *
 * Das Modul rechnet nicht selbst ab – es liefert je Monat eine saubere
 * Aufstellung je Person (Menü, OGS, Spontankauf, Chip-Pfand) für die externe
 * Abrechnung: als Bildschirm-Liste, als CSV (Import) und als PDF (Ausdruck).
 * „Als bezahlt markieren" hält den Bezahlt-Status je Person & Monat fest.
 */
class ReportController
{
    // --------------------------------------------------------------- Ansicht

    public function index(Request $request)
    {
        $viewer = $request->user();

        $season = Season::where('is_active', true)->first();
        if (! $season) {
            return view('schulkantine::reports.index', ['season' => null, 'isAdmin' => (bool) $viewer?->isAdmin()]);
        }

        [$year, $month] = $this->resolveMonth($request, $season);

        // Admins sehen alle Teilnehmer; jeder andere nur sich selbst + seine Kinder.
        $isAdmin = (bool) $viewer->isAdmin();
        $allowedIds = $isAdmin ? null : $this->ownHouseholdIds($viewer);

        return view('schulkantine::reports.index', $this->buildReport($season, $year, $month, $allowedIds) + [
            'season' => $season,
            'isAdmin' => $isAdmin,
            'year' => $year,
            'month' => $month,
            'monthLabel' => $this->monthLabel($year, $month),
            'monthValue' => sprintf('%04d-%02d', $year, $month),
            'months' => $this->seasonMonths($season),
        ]);
    }

    /** Detailseite einer Person: alle Einzelposten im gewählten Monat. */
    public function show(Request $request, User $user)
    {
        $viewer = $request->user();
        abort_unless($this->canView($viewer, $user), 403, 'Kein Zugriff auf diese Auswertung.');

        $season = Season::where('is_active', true)->firstOrFail();
        [$year, $month] = $this->resolveMonth($request, $season);

        $details = (new BillingService)->detailsForUser($season, $user->id, $year, $month);
        $settlement = Settlement::where('user_id', $user->id)
            ->where('year', $year)->where('month', $month)->first();

        $group = \Intranet\Modules\Schulkantine\Models\CustomerGroup::forUser($user);
        $parent = $user->parents()->orderBy('id')->first();

        return view('schulkantine::reports.show', $details + [
            'season' => $season,
            'isAdmin' => (bool) $viewer->isAdmin(),
            'user' => $user,
            'group' => $group,
            'parent' => $parent,
            'year' => $year,
            'month' => $month,
            'monthLabel' => $this->monthLabel($year, $month),
            'monthValue' => sprintf('%04d-%02d', $year, $month),
            'paid' => $settlement !== null,
            'settlement' => $settlement,
        ]);
    }

    // ------------------------------------------------------ Bezahlt-Status

    public function markPaid(Request $request, User $user)
    {
        $this->authorizeAdmin($request);
        $season = Season::where('is_active', true)->firstOrFail();
        [$year, $month] = $this->resolveMonth($request, $season);

        // Betrag zum Zeitpunkt der Markierung festhalten (Audit-Snapshot).
        $line = (new BillingService)->forMonth($season, $year, $month)->get($user->id);
        $amount = $line['total'] ?? 0.0;

        Settlement::updateOrCreate(
            ['user_id' => $user->id, 'year' => $year, 'month' => $month],
            [
                'season_id' => $season->id,
                'amount' => $amount,
                'paid_at' => Carbon::now(),
                'marked_by' => $request->user()->id,
            ]
        );

        return back()->with('status', $user->name.' – '.$this->monthLabel($year, $month).' als bezahlt markiert.');
    }

    public function unmarkPaid(Request $request, User $user)
    {
        $this->authorizeAdmin($request);
        $season = Season::where('is_active', true)->firstOrFail();
        [$year, $month] = $this->resolveMonth($request, $season);

        Settlement::where('user_id', $user->id)->where('year', $year)->where('month', $month)->delete();

        return back()->with('status', 'Bezahlt-Markierung für '.$user->name.' zurückgenommen.');
    }

    // ------------------------------------------------------------- Exporte

    public function csv(Request $request): StreamedResponse
    {
        $this->authorizeAdmin($request);
        $season = Season::where('is_active', true)->firstOrFail();
        [$year, $month] = $this->resolveMonth($request, $season);
        $report = $this->buildReport($season, $year, $month);

        $filename = 'kantine-abrechnung-'.sprintf('%04d-%02d', $year, $month).'.csv';

        return response()->streamDownload(function () use ($report) {
            $out = fopen('php://output', 'w');
            // UTF-8-BOM, damit Excel Umlaute korrekt anzeigt.
            fwrite($out, "\xEF\xBB\xBF");

            $head = ['Name', 'E-Mail', 'Haushalt', 'Gruppe',
                'Menü (€)', 'Menü Anzahl', 'OGS (€)', 'OGS Tage',
                'Spontan (€)', 'Spontan Anzahl', 'Pfand (€)', 'No-Shows',
                'Summe (€)', 'Bezahlt'];
            fputcsv($out, $head, ';');

            foreach ($report['households'] as $hh) {
                foreach ($hh['members'] as $m) {
                    $l = $m['line'];
                    fputcsv($out, [
                        $m['user']->name,
                        $m['user']->email,
                        $hh['name'],
                        $m['group'],
                        $this->num($l['menu_total']), $l['menu_count'],
                        $this->num($l['ogs_total']), $l['ogs_days'],
                        $this->num($l['spontan_total']), $l['spontan_count'],
                        $this->num($l['pfand_net']), $l['no_show_count'],
                        $this->num($l['total']),
                        $m['paid'] ? 'ja' : 'offen',
                    ], ';');
                }
            }
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function pdf(Request $request)
    {
        $this->authorizeAdmin($request);
        $season = Season::where('is_active', true)->firstOrFail();
        [$year, $month] = $this->resolveMonth($request, $season);
        $report = $this->buildReport($season, $year, $month);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('schulkantine::reports.pdf', $report + [
            'season' => $season,
            'monthLabel' => $this->monthLabel($year, $month),
            'generatedAt' => Carbon::now(),
        ])->setPaper('a4', 'portrait');

        return $pdf->download('kantine-abrechnung-'.sprintf('%04d-%02d', $year, $month).'.pdf');
    }

    // ----------------------------------------------------------- Interna

    /**
     * Baut die nach Haushalt gruppierte Monatsauswertung (für Ansicht & Export).
     */
    private function buildReport(Season $season, int $year, int $month, ?array $allowedIds = null): array
    {
        $lines = (new BillingService)->forMonth($season, $year, $month);

        // Nicht-Admins sehen nur ihren eigenen Haushalt (sich + Kinder).
        if ($allowedIds !== null) {
            $lines = $lines->only($allowedIds);
        }

        // Beteiligte Benutzer laden (inkl. Eltern & Rollen für Haushalt/Gruppe).
        $users = User::with(['parents', 'roles'])
            ->whereIn('id', $lines->keys())
            ->get()->keyBy('id');

        // Bezahlt-Status dieses Monats.
        $settlements = Settlement::where('year', $year)->where('month', $month)
            ->whereIn('user_id', $lines->keys())
            ->get()->keyBy('user_id');

        $groups = \Intranet\Modules\Schulkantine\Models\CustomerGroup::all()->keyBy('role_id');

        // Nach Haushalt gruppieren: Haushalts-Kopf = erstes Elternteil, sonst
        // die Person selbst (z. B. Personal ohne Eltern-Verknüpfung).
        $households = [];
        foreach ($lines as $userId => $line) {
            $user = $users->get($userId);
            if (! $user) {
                continue; // Benutzer gelöscht – überspringen.
            }

            $parent = $user->parents->sortBy('id')->first();
            $key = $parent?->id ?? $user->id;
            $name = $parent?->name ?? $user->name;

            $group = \Intranet\Modules\Schulkantine\Models\CustomerGroup::forUser($user, $groups);
            $settlement = $settlements->get($userId);

            $households[$key] ??= ['key' => $key, 'name' => $name, 'members' => [], 'subtotal' => 0.0, 'open' => 0.0];
            $households[$key]['members'][] = [
                'user' => $user,
                'line' => $line,
                'group' => $group?->name ?? '–',
                'paid' => $settlement !== null,
                'settlement' => $settlement,
            ];
            $households[$key]['subtotal'] += $line['total'];
            if ($settlement === null) {
                $households[$key]['open'] += $line['total'];
            }
        }

        // Haushalte nach Name, Mitglieder je Haushalt nach Name sortieren.
        $households = collect($households)
            ->map(function ($hh) {
                usort($hh['members'], fn ($a, $b) => strcmp($a['user']->name, $b['user']->name));

                return $hh;
            })
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();

        $grandTotal = $lines->sum('total');
        $paidTotal = $settlements->keys()
            ->reduce(fn ($c, $uid) => $c + ($lines->get($uid)['total'] ?? 0), 0.0);

        return [
            'households' => $households,
            'grandTotal' => round($grandTotal, 2),
            'paidTotal' => round($paidTotal, 2),
            'openTotal' => round($grandTotal - $paidTotal, 2),
            'personCount' => $lines->count(),
        ];
    }

    /** Monat aus monat=YYYY-MM (Query ODER Body) oder Standard (letzter Monat mit Daten). */
    private function resolveMonth(Request $request, Season $season): array
    {
        $raw = (string) $request->input('monat', '');
        if (preg_match('/^(\d{4})-(\d{1,2})$/', $raw, $m)) {
            $year = (int) $m[1];
            $month = (int) $m[2];
            if ($month >= 1 && $month <= 12) {
                return [$year, $month];
            }
        }

        // Standard: Monat der jüngsten Bestellung in dieser Saison, sonst „heute".
        $latest = Order::where('season_id', $season->id)->max('date');
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

    private function num(float $v): string
    {
        return number_format($v, 2, ',', '.');
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Nur Administratoren dürfen die Auswertung sehen.');
    }

    /** IDs, die ein normaler Nutzer sehen darf: er selbst + seine Kinder. */
    private function ownHouseholdIds(User $user): array
    {
        return $user->children->pluck('id')->push($user->id)->unique()->values()->all();
    }

    /** Darf der Betrachter die Auswertung dieser Person sehen? Admin, oder sie selbst / sein Kind. */
    private function canView(?User $viewer, User $target): bool
    {
        if (! $viewer) {
            return false;
        }

        return $viewer->isAdmin() || in_array($target->id, $this->ownHouseholdIds($viewer), true);
    }
}
