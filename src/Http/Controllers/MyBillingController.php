<?php

namespace Intranet\Modules\Schulkantine\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Intranet\Modules\Schulkantine\Models\CustomerGroup;
use Intranet\Modules\Schulkantine\Models\Order;
use Intranet\Modules\Schulkantine\Models\Season;
use Intranet\Modules\Schulkantine\Support\BillingService;

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
    /** Haushalts-Übersicht des Monats: je Person eine Zeile mit Teil- und Gesamtsumme. */
    public function index(Request $request)
    {
        $viewer = $request->user();

        $season = Season::where('is_active', true)->first();
        if (! $season) {
            return view('schulkantine::billing.index', ['season' => null]);
        }

        [$year, $month] = $this->resolveMonth($request, $season, $viewer);

        $billing = new BillingService;
        $groups = CustomerGroup::all()->keyBy('role_id');

        $members = [];
        $total = 0.0;
        foreach ($this->household($viewer) as $eater) {
            $d = $billing->detailsForUser($season, $eater->id, $year, $month);
            $members[] = [
                'user' => $eater,
                'group' => CustomerGroup::forUser($eater, $groups)?->name,
                'menu_total' => $d['menu_total'],
                'ogs_total' => $d['ogs']['total'],
                'ogs_days' => $d['ogs']['days'],
                'spontan_total' => $d['spontan_total'],
                'pfand_net' => $d['pfand_net'],
                'total' => $d['total'],
            ];
            $total += $d['total'];
        }

        return view('schulkantine::billing.index', [
            'season' => $season,
            'members' => $members,
            'total' => round($total, 2),
            'year' => $year,
            'month' => $month,
            'monthLabel' => $this->monthLabel($year, $month),
            'monthValue' => sprintf('%04d-%02d', $year, $month),
            'months' => $this->seasonMonths($season),
        ]);
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
