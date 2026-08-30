<?php

namespace Intranet\Modules\Schulkantine\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Intranet\Modules\Schulkantine\Models\Order;
use Intranet\Modules\Schulkantine\Models\Subscription;

/**
 * Die EINE Wahrheit, ob ein OGS-Kind an einem Tag isst – genutzt von Anzeige
 * (Bestellung), Ausgabe-Liste und Abrechnung, damit alle dasselbe rechnen.
 *
 * Grundregel: Das Abo legt über sein Wochentags-Muster den Standard fest; eine
 * einzelne Bestell- oder Abbestell-Zeile für den Tag schlägt den Standard.
 *
 *   isst(Tag) = bestellt(Tag)              ? ja
 *             : abbestellt(Tag)            ? nein
 *             : Abo aktiv & Wochentag im Muster
 */
class OgsAttendance
{
    /** Einzeltag – die Overrides kommen als bereits ermittelte Booleans. */
    public static function attends(?Subscription $sub, Carbon $date, bool $hasOrdered, bool $hasCancelled): bool
    {
        if ($hasOrdered) {
            return true;
        }
        if ($hasCancelled) {
            return false;
        }

        return $sub !== null && $sub->active && $sub->eatsWeekday($date->dayOfWeekIso);
    }

    /**
     * Aus offenen Tagen + den OGS-Bestellzeilen (category_id NULL) eines Essers
     * die tatsächlich besuchten Tage bestimmen.
     *
     * @param  array<string,mixed>  $openDays  Öffnungstage, Schlüssel = Y-m-d
     * @param  Collection  $rows  OGS-Order-Zeilen des Essers (Felder date, status)
     * @return array<string>  besuchte Tage als Y-m-d
     */
    public static function attendedDates(?Subscription $sub, array $openDays, Collection $rows): array
    {
        $ordered = $rows->where('status', Order::STATUS_ORDERED)
            ->map(fn ($r) => $r->date->toDateString())->flip();
        $cancelled = $rows->where('status', Order::STATUS_CANCELLED)
            ->map(fn ($r) => $r->date->toDateString())->flip();

        $out = [];
        foreach (array_keys($openDays) as $ds) {
            if (self::attends($sub, Carbon::parse($ds), $ordered->has($ds), $cancelled->has($ds))) {
                $out[] = $ds;
            }
        }

        return $out;
    }
}
