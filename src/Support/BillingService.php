<?php

namespace Intranet\Modules\Schulkantine\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Intranet\Modules\Schulkantine\Models\NfcChip;
use Intranet\Modules\Schulkantine\Models\Order;
use Intranet\Modules\Schulkantine\Models\Season;
use Intranet\Modules\Schulkantine\Models\Serving;
use Intranet\Modules\Schulkantine\Models\Subscription;

/**
 * Berechnet die Monatsabrechnung je Person (Phase 5).
 *
 * Abrechnungsbasis (siehe Konzept „Verbindlichkeit & Abrechnung"):
 *  - Menü-Vorbestellungen (Preis-Snapshot) – verbindlich, No-Show zahlt trotzdem.
 *    Rechtzeitig storniert = nicht berechnet (zählt hier nicht mit).
 *  - OGS-Teilnahme × Saison-Fixpreis (abgeleitet aus Abo minus Abbestellungen
 *    bzw. einzeln bestellten Tagen) – Logik identisch zum OrderController.
 *  - Spontane Abholungen (Preis-Snapshot) – hat gegessen, wird berechnet.
 *  - Chip-Pfand: Schul-Chip in diesem Monat ausgegeben → Pfand fällt an (+),
 *    in diesem Monat zurückgegeben → Pfand zurück (−).
 *
 * No-Shows werden nur informativ mitgezählt (bezahlt wird trotzdem).
 * Die Ausgabe (servings, außer spontan) ist NICHT die Abrechnungsbasis.
 */
class BillingService
{
    /**
     * Liefert je Person eine Abrechnungszeile für den Monat. Enthalten sind nur
     * Personen mit mindestens einem Posten (Menü, OGS, spontan oder Pfand).
     *
     * @return Collection<int, array> keyed by user_id
     */
    public function forMonth(Season $season, int $year, int $month): Collection
    {
        $monthStart = Carbon::create($year, $month, 1)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $from = $monthStart->toDateString();
        $to = $monthEnd->toDateString();

        // Öffnungstage des Monats (für die OGS-Standard-Teilnahme).
        $openDays = $this->openDaysInMonth($season, $monthStart, $monthEnd);
        $openCount = count($openDays);

        // Ergebnis je Person – Zeilen werden bei Bedarf angelegt.
        $lines = [];
        $line = function (int $userId) use (&$lines): array {
            return $lines[$userId] ?? [
                'user_id' => $userId,
                'menu_total' => 0.0, 'menu_count' => 0,
                'ogs_total' => 0.0, 'ogs_days' => 0,
                'spontan_total' => 0.0, 'spontan_count' => 0,
                'pfand_out' => 0.0, 'pfand_back' => 0.0,
                'no_show_count' => 0,
            ];
        };

        // 1) Menü-Bestellungen (Preis-Snapshot). Nur verbindliche = status bestellt.
        $menu = Order::where('season_id', $season->id)
            ->where('status', Order::STATUS_ORDERED)
            ->whereNotNull('category_id')
            ->whereBetween('date', [$from, $to])
            ->selectRaw('user_id, SUM(price_snapshot) as total, COUNT(*) as cnt')
            ->groupBy('user_id')
            ->get();
        foreach ($menu as $row) {
            $l = $line($row->user_id);
            $l['menu_total'] = (float) $row->total;
            $l['menu_count'] = (int) $row->cnt;
            $lines[$row->user_id] = $l;
        }

        // 2) Spontane Abholungen (Preis-Snapshot).
        $spontan = Serving::where('season_id', $season->id)
            ->where('spontaneous', true)
            ->whereBetween('date', [$from, $to])
            ->selectRaw('user_id, SUM(price_snapshot) as total, COUNT(*) as cnt')
            ->groupBy('user_id')
            ->get();
        foreach ($spontan as $row) {
            $l = $line($row->user_id);
            $l['spontan_total'] = (float) $row->total;
            $l['spontan_count'] = (int) $row->cnt;
            $lines[$row->user_id] = $l;
        }

        // 3) OGS-Kosten (abgeleitet). Identische Regel wie im OrderController:
        //    mit Abo  → Öffnungstage minus Abbestellungen; ohne Abo → bestellte Tage.
        $ogsPrice = (float) ($season->ogs_price ?? 0);
        if ($ogsPrice > 0 && $openCount > 0) {
            $subscribed = Subscription::where('season_id', $season->id)
                ->where('active', true)
                ->pluck('user_id')->flip();

            // OGS-Bestellzeilen des Monats (Tagesmenü hat category_id, OGS nicht).
            $ogsOrders = Order::where('season_id', $season->id)
                ->whereNull('category_id')
                ->whereBetween('date', [$from, $to])
                ->get(['user_id', 'date', 'status']);

            // Alle OGS-Esser: mit Abo ODER mit eigener OGS-Bestellzeile.
            $ogsUserIds = $subscribed->keys()
                ->merge($ogsOrders->pluck('user_id'))
                ->unique();

            foreach ($ogsUserIds as $uid) {
                $rows = $ogsOrders->where('user_id', $uid);
                if ($subscribed->has($uid)) {
                    $storno = $rows->where('status', Order::STATUS_CANCELLED)
                        ->map(fn ($r) => $r->date->toDateString())
                        ->filter(fn ($ds) => isset($openDays[$ds]))->unique()->count();
                    $days = max(0, $openCount - $storno);
                } else {
                    $days = $rows->where('status', Order::STATUS_ORDERED)
                        ->map(fn ($r) => $r->date->toDateString())
                        ->filter(fn ($ds) => isset($openDays[$ds]))->unique()->count();
                }
                if ($days > 0) {
                    $l = $line($uid);
                    $l['ogs_days'] = $days;
                    $l['ogs_total'] = $days * $ogsPrice;
                    $lines[$uid] = $l;
                }
            }
        }

        // 4) Chip-Pfand (nur Schul-Chips). Ausgabe in diesem Monat = Pfand (+),
        //    Rückgabe in diesem Monat = Pfand zurück (−).
        $lent = NfcChip::where('source', NfcChip::SOURCE_SCHULE)
            ->whereBetween('lent_at', [$from, $to])
            ->selectRaw('user_id, SUM(deposit) as total')
            ->groupBy('user_id')->get();
        foreach ($lent as $row) {
            $l = $line($row->user_id);
            $l['pfand_out'] = (float) $row->total;
            $lines[$row->user_id] = $l;
        }

        $returned = NfcChip::where('source', NfcChip::SOURCE_SCHULE)
            ->whereBetween('returned_at', [$from, $to])
            ->selectRaw('user_id, SUM(deposit) as total')
            ->groupBy('user_id')->get();
        foreach ($returned as $row) {
            $l = $line($row->user_id);
            $l['pfand_back'] = (float) $row->total;
            $lines[$row->user_id] = $l;
        }

        // 5) No-Shows (nur informativ): verbindliche Menü-Bestellung ohne Ausgabe.
        $servedOrderIds = Serving::where('season_id', $season->id)
            ->where('spontaneous', false)
            ->whereNotNull('order_id')
            ->whereBetween('date', [$from, $to])
            ->pluck('order_id')->flip();
        $menuOrders = Order::where('season_id', $season->id)
            ->where('status', Order::STATUS_ORDERED)
            ->whereNotNull('category_id')
            ->whereBetween('date', [$from, $to])
            ->get(['id', 'user_id']);
        foreach ($menuOrders as $o) {
            if (! $servedOrderIds->has($o->id) && isset($lines[$o->user_id])) {
                $lines[$o->user_id]['no_show_count']++;
            }
        }

        // Summe je Zeile berechnen und Nullzeilen (nur durch No-Show-Init) filtern.
        return collect($lines)
            ->map(function (array $l) {
                $l['pfand_net'] = $l['pfand_out'] - $l['pfand_back'];
                $l['total'] = round(
                    $l['menu_total'] + $l['ogs_total'] + $l['spontan_total'] + $l['pfand_net'],
                    2
                );

                return $l;
            });
    }

    /**
     * Einzelposten einer Person im Monat (für die Detailseite): jede Bestellung,
     * jeder OGS-Tag, jede spontane Abholung, jede Pfand-Buchung.
     */
    public function detailsForUser(Season $season, int $userId, int $year, int $month): array
    {
        $monthStart = Carbon::create($year, $month, 1)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $from = $monthStart->toDateString();
        $to = $monthEnd->toDateString();
        $openDays = $this->openDaysInMonth($season, $monthStart, $monthEnd);

        // Tatsächliche (nicht spontane) Ausgabe-Zeilen des Monats je Bestell-ID –
        // sagt aus, was beim Abhaken passiert ist (genommen / Alternative / nicht genommen).
        $servedByOrder = Serving::where('season_id', $season->id)
            ->where('user_id', $userId)
            ->where('spontaneous', false)
            ->whereNotNull('order_id')
            ->whereBetween('date', [$from, $to])
            ->get()->keyBy('order_id');

        // 1) Menü-Bestellungen (verbindlich) – je Datum/Gericht, inkl. Ausgabe-Ergebnis.
        $menuOrders = Order::where('season_id', $season->id)
            ->where('user_id', $userId)
            ->where('status', Order::STATUS_ORDERED)
            ->whereNotNull('category_id')
            ->whereBetween('date', [$from, $to])
            ->with(['dish.category', 'category'])
            ->orderBy('date')->get();
        $menu = $menuOrders->map(function (Order $o) use ($servedByOrder) {
            $sv = $servedByOrder->get($o->id);
            // outcome: none = nicht abgehakt (No-Show) · taken · alternative · declined
            $outcome = $sv === null
                ? 'none'
                : ($sv->declined ? 'declined' : ($sv->alternative ? 'alternative' : 'taken'));

            return [
                'date' => $o->date,
                'dish' => $o->dish?->name ?? '—',
                'category' => $o->dish?->category?->name ?? $o->category?->name ?? '—',
                'price' => (float) $o->price_snapshot,
                'outcome' => $outcome,
                'reason' => $sv?->decline_reason,
            ];
        })->all();
        $menuTotal = array_sum(array_column($menu, 'price'));

        // 2) OGS – teilgenommene Tage (Abo minus Abbestellungen bzw. bestellte Tage).
        $ogsPrice = (float) ($season->ogs_price ?? 0);
        $ogs = ['days' => 0, 'price' => $ogsPrice, 'total' => 0.0, 'dates' => [], 'cancelled' => [], 'subscribed' => false];
        if ($ogsPrice > 0 && ! empty($openDays)) {
            $subscribed = Subscription::where('season_id', $season->id)
                ->where('user_id', $userId)->where('active', true)->exists();
            $ogsRows = Order::where('season_id', $season->id)
                ->where('user_id', $userId)->whereNull('category_id')
                ->whereBetween('date', [$from, $to])->get(['date', 'status']);
            $ogs['subscribed'] = $subscribed;

            if ($subscribed) {
                $cancelled = $ogsRows->where('status', Order::STATUS_CANCELLED)
                    ->map(fn ($r) => $r->date->toDateString())
                    ->filter(fn ($ds) => isset($openDays[$ds]))->unique()->flip();
                foreach (array_keys($openDays) as $ds) {
                    if ($cancelled->has($ds)) {
                        $ogs['cancelled'][] = Carbon::parse($ds);
                    } else {
                        $ogs['dates'][] = Carbon::parse($ds);
                    }
                }
            } elseif ($ogsRows->isNotEmpty()) {
                $ordered = $ogsRows->where('status', Order::STATUS_ORDERED)
                    ->map(fn ($r) => $r->date->toDateString())
                    ->filter(fn ($ds) => isset($openDays[$ds]))->unique()->sort();
                foreach ($ordered as $ds) {
                    $ogs['dates'][] = Carbon::parse($ds);
                }
            }
            $ogs['days'] = count($ogs['dates']);
            $ogs['total'] = $ogs['days'] * $ogsPrice;
        }

        // 3) Spontane Abholungen.
        $spontanRows = Serving::where('season_id', $season->id)
            ->where('user_id', $userId)->where('spontaneous', true)
            ->whereBetween('date', [$from, $to])
            ->with('dish.category')->orderBy('date')->get();
        $spontan = $spontanRows->map(fn (Serving $s) => [
            'date' => $s->date,
            // Nachschlag & Co. haben kein Gericht, aber ein Label – so bleibt in der
            // Abrechnung nachvollziehbar, woraus sich der Betrag zusammensetzt.
            'dish' => $s->dish?->name ?? $s->label ?? '—',
            'category' => $s->dish?->category?->name ?? ($s->label ? 'Nachschlag' : '—'),
            'price' => (float) $s->price_snapshot,
        ])->all();
        $spontanTotal = array_sum(array_column($spontan, 'price'));

        // 4) Chip-Pfand – Ausgabe (+) und Rückgabe (−) in diesem Monat.
        $chips = [];
        $lent = NfcChip::where('user_id', $userId)->where('source', NfcChip::SOURCE_SCHULE)
            ->whereBetween('lent_at', [$from, $to])->get();
        foreach ($lent as $c) {
            $chips[] = ['uid' => $c->uid, 'type' => 'aus', 'date' => $c->lent_at, 'amount' => (float) $c->deposit];
        }
        $returned = NfcChip::where('user_id', $userId)->where('source', NfcChip::SOURCE_SCHULE)
            ->whereBetween('returned_at', [$from, $to])->get();
        foreach ($returned as $c) {
            $chips[] = ['uid' => $c->uid, 'type' => 'zurueck', 'date' => $c->returned_at, 'amount' => -(float) $c->deposit];
        }
        $pfandNet = array_sum(array_column($chips, 'amount'));

        return [
            'menu' => $menu,
            'menu_total' => round($menuTotal, 2),
            'ogs' => $ogs,
            'spontan' => $spontan,
            'spontan_total' => round($spontanTotal, 2),
            'chips' => $chips,
            'pfand_net' => round($pfandNet, 2),
            'total' => round($menuTotal + $ogs['total'] + $spontanTotal + $pfandNet, 2),
        ];
    }

    /** Öffnungstage eines Monats als [YYYY-MM-DD => true]. */
    private function openDaysInMonth(Season $season, Carbon $monthStart, Carbon $monthEnd): array
    {
        $openDays = [];
        for ($d = $monthStart->copy(); $d->lte($monthEnd); $d->addDay()) {
            if ($season->isOpenOn($d)) {
                $openDays[$d->toDateString()] = true;
            }
        }

        return $openDays;
    }
}
