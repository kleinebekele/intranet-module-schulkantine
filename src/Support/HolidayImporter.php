<?php

namespace Intranet\Modules\Schulkantine\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Intranet\Modules\Schulkantine\Models\Season;

/**
 * Holt Feiertage und Schulferien des Saison-Bundeslandes von der öffentlichen
 * OpenHolidays-API (openholidaysapi.org, kostenlos, ohne Key) und legt sie als
 * Schließtage der Saison an.
 *
 * Feiertage sind einzelne Tage, Schulferien sind Zeiträume – die Zeiträume
 * werden auf einzelne Kalendertage aufgelöst (begrenzt auf die Saison), damit
 * die Öffnungstag-Logik einfach mit einzelnen Daten arbeiten kann.
 */
class HolidayImporter
{
    private string $base = 'https://openholidaysapi.org';

    /**
     * @return array{feiertage:int, ferien:int}  Anzahl übernommener Tage je Art
     */
    public function importForSeason(Season $season): array
    {
        $from = $season->start_date->toDateString();
        $to = $season->end_date->toDateString();

        // Erst die API abfragen (kann dauern) – DANN in einer Transaktion schreiben,
        // damit ein Fehler nie einen halben Import-Stand hinterlässt.
        $feiertagItems = $this->fetch('PublicHolidays', $season->bundesland, $from, $to);
        $ferienItems = $this->fetch('SchoolHolidays', $season->bundesland, $from, $to);

        return DB::transaction(function () use ($season, $feiertagItems, $ferienItems) {
            $feiertage = 0;
            foreach ($feiertagItems as $item) {
                $feiertage += $this->storeRange($season, $item, 'feiertag');
            }

            $ferien = 0;
            foreach ($ferienItems as $item) {
                $ferien += $this->storeRange($season, $item, 'ferien');
            }

            return ['feiertage' => $feiertage, 'ferien' => $ferien];
        });
    }

    /** @return array<int, array<string, mixed>> */
    private function fetch(string $endpoint, string $subdivision, string $from, string $to): array
    {
        $response = Http::acceptJson()
            ->timeout(20)
            ->get("{$this->base}/{$endpoint}", [
                'countryIsoCode' => 'DE',
                'subdivisionCode' => $subdivision,
                'languageIsoCode' => 'DE',
                'validFrom' => $from,
                'validTo' => $to,
            ]);

        $response->throw();

        return $response->json() ?? [];
    }

    /**
     * Legt für einen (evtl. mehrtägigen) Eintrag je Kalendertag einen Schließtag
     * an – begrenzt auf den Saison-Zeitraum. Gibt die Anzahl Tage zurück.
     *
     * @param  array<string, mixed>  $item
     */
    private function storeRange(Season $season, array $item, string $type): int
    {
        $start = Carbon::parse($item['startDate']);
        $end = Carbon::parse($item['endDate'] ?? $item['startDate']);

        if ($start->lt($season->start_date)) {
            $start = $season->start_date->copy();
        }
        if ($end->gt($season->end_date)) {
            $end = $season->end_date->copy();
        }

        $name = $this->germanName($item);
        $count = 0;

        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $this->upsertClosedDay($season, $day->toDateString(), $name, $type, 'api');
            $count++;
        }

        return $count;
    }

    /**
     * Legt einen Schließtag an oder aktualisiert ihn. Der Datums-Abgleich läuft
     * über whereDate – so wird ein bereits vorhandener Tag (der intern mit
     * Uhrzeit 00:00:00 gespeichert ist) zuverlässig gefunden, statt ihn erneut
     * anzulegen und in die Unique-Sperre zu laufen.
     */
    private function upsertClosedDay(Season $season, string $date, ?string $reason, string $type, string $source): void
    {
        $existing = $season->closedDays()->whereDate('date', $date)->first();

        if ($existing) {
            $existing->update(['reason' => $reason, 'type' => $type, 'source' => $source]);
        } else {
            $season->closedDays()->create([
                'date' => $date,
                'reason' => $reason,
                'type' => $type,
                'source' => $source,
            ]);
        }
    }

    /** Der deutschsprachige Name eines API-Eintrags (mit Fallback). */
    private function germanName(array $item): ?string
    {
        foreach ($item['name'] ?? [] as $n) {
            if (($n['language'] ?? null) === 'DE') {
                return $n['text'] ?? null;
            }
        }

        return $item['name'][0]['text'] ?? null;
    }
}
