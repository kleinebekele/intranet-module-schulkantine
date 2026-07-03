<?php

namespace Intranet\Modules\Schulkantine\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Intranet\Modules\Schulkantine\Models\ClosedDay;
use Intranet\Modules\Schulkantine\Models\Season;
use Intranet\Modules\Schulkantine\Support\Bundeslaender;
use Intranet\Modules\Schulkantine\Support\HolidayImporter;

/**
 * Verwaltung der Saisons (Schuljahre) und ihres Öffnungskalenders.
 *
 * Vorerst nur für Administratoren (siehe authorizeAdmin). Die feinere
 * Rollen-Steuerung (kantinenadmin) kommt mit der Rechte-Phase.
 */
class SeasonController
{
    public function index(Request $request)
    {
        $this->authorizeAdmin($request);

        $seasons = Season::withCount('closedDays')->orderByDesc('start_date')->get();

        return view('schulkantine::seasons.index', compact('seasons'));
    }

    public function create(Request $request)
    {
        $this->authorizeAdmin($request);

        return view('schulkantine::seasons.form', [
            'season' => new Season(['opening_weekdays' => [1, 2, 3, 4]]),
            'bundeslaender' => Bundeslaender::all(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeAdmin($request);

        $season = Season::create($this->validated($request));
        $this->syncActiveFlag($season);

        return redirect()
            ->route('module.schulkantine.seasons.show', $season)
            ->with('status', 'Saison „'.$season->name.'" wurde angelegt.');
    }

    public function show(Request $request, Season $season)
    {
        $this->authorizeAdmin($request);

        $season->load(['closedDays' => fn ($q) => $q->orderBy('date')]);

        return view('schulkantine::seasons.show', [
            'season' => $season,
            'bundeslandName' => Bundeslaender::all()[$season->bundesland] ?? $season->bundesland,
        ]);
    }

    public function edit(Request $request, Season $season)
    {
        $this->authorizeAdmin($request);

        return view('schulkantine::seasons.form', [
            'season' => $season,
            'bundeslaender' => Bundeslaender::all(),
        ]);
    }

    public function update(Request $request, Season $season)
    {
        $this->authorizeAdmin($request);

        $season->update($this->validated($request));
        $this->syncActiveFlag($season);

        return redirect()
            ->route('module.schulkantine.seasons.show', $season)
            ->with('status', 'Saison wurde gespeichert.');
    }

    public function destroy(Request $request, Season $season)
    {
        $this->authorizeAdmin($request);

        $season->delete();

        return redirect()
            ->route('module.schulkantine.seasons.index')
            ->with('status', 'Saison wurde gelöscht.');
    }

    public function storeClosedDay(Request $request, Season $season)
    {
        $this->authorizeAdmin($request);

        $start = $season->start_date->toDateString();
        $end = $season->end_date->toDateString();

        $data = $request->validate([
            'date_from' => ['required', 'date', 'after_or_equal:'.$start, 'before_or_equal:'.$end],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from', 'before_or_equal:'.$end],
            'type' => ['required', Rule::in(['ferien', 'feiertag', 'sonstiges'])],
            'reason' => ['nullable', 'string', 'max:255'],
        ], [
            'date_from.after_or_equal' => 'Der Tag muss innerhalb der Saison liegen.',
            'date_from.before_or_equal' => 'Der Tag muss innerhalb der Saison liegen.',
            'date_to.before_or_equal' => 'Das End-Datum muss innerhalb der Saison liegen.',
            'date_to.after_or_equal' => 'Das End-Datum darf nicht vor dem Start liegen.',
        ]);

        $from = Carbon::parse($data['date_from']);
        $to = ! empty($data['date_to']) ? Carbon::parse($data['date_to']) : $from->copy();

        $count = 0;
        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            $this->upsertClosedDay($season, $day->toDateString(), $data['reason'] ?? null, $data['type'], 'manuell');
            $count++;
        }

        return back()->with('status', $count === 1
            ? 'Schließtag gespeichert.'
            : "{$count} Schließtage gespeichert.");
    }

    /** Schließtag anlegen oder aktualisieren (Datums-Abgleich per whereDate, siehe HolidayImporter). */
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

    public function destroyClosedDay(Request $request, Season $season, ClosedDay $closedDay)
    {
        $this->authorizeAdmin($request);

        abort_unless($closedDay->season_id === $season->id, 404);

        $closedDay->delete();

        return back()->with('status', 'Schließtag entfernt.');
    }

    public function importHolidays(Request $request, Season $season, HolidayImporter $importer)
    {
        $this->authorizeAdmin($request);

        if (! $season->bundesland) {
            return back()->with('error', 'Für den Import muss zuerst ein Bundesland in der Saison hinterlegt sein.');
        }

        try {
            $result = $importer->importForSeason($season);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Der Ferien-/Feiertags-Import ist fehlgeschlagen. Bitte später erneut versuchen.');
        }

        return back()->with('status', "Import abgeschlossen: {$result['feiertage']} Feiertags- und {$result['ferien']} Ferientage übernommen.");
    }

    // ---------------------------------------------------------------- Helfer

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'bundesland' => ['nullable', Rule::in(array_keys(Bundeslaender::all()))],
            'opening_weekdays' => ['array'],
            'opening_weekdays.*' => [Rule::in(['1', '2', '3', '4', '5', '6', '7'])],
        ]);

        return [
            'name' => $request->string('name')->toString(),
            'start_date' => $request->date('start_date'),
            'end_date' => $request->date('end_date'),
            'bundesland' => $request->input('bundesland') ?: null,
            'opening_weekdays' => array_map('intval', $request->input('opening_weekdays', [])),
            'is_active' => $request->boolean('is_active'),
        ];
    }

    /** Es darf immer nur eine Saison aktiv sein. */
    private function syncActiveFlag(Season $season): void
    {
        if ($season->is_active) {
            Season::whereKeyNot($season->getKey())
                ->where('is_active', true)
                ->update(['is_active' => false]);
        }
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Nur Administratoren dürfen die Kantine verwalten.');
    }
}
