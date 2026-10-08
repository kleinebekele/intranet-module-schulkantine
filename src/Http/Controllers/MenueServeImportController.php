<?php

namespace Intranet\Modules\Schulkantine\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Intranet\Modules\Schulkantine\Models\Diet;
use Intranet\Modules\Schulkantine\Models\Dish;
use Intranet\Modules\Schulkantine\Models\Season;
use Intranet\Modules\Schulkantine\Support\Access;
use Intranet\Modules\Schulkantine\Support\MenueServeGerichte;
use Intranet\Modules\Schulkantine\Support\MenueServeSpeiseplan;

/**
 * Menü&Serve-Menüs unseren Gerichten zuordnen: je Menü (Tag + Menülinie) eine
 * Hauptspeise und eine Nachspeise. Die Zuordnung wird nur gemerkt
 * (kantine_menueserve_zuordnungen) – der Speiseplan bleibt unberührt.
 */
class MenueServeImportController
{
    /** Erster Tag, an dem die Intranet-Kantine Menü&Serve ablöst. */
    private const START = '2026-10-12';

    public function index(Request $request)
    {
        $this->authorize($request);
        $ab = $this->ab($request);

        try {
            $menues = (new MenueServeGerichte)->menuesAb($ab);
            $fehler = null;
        } catch (\Throwable $e) {
            report($e);
            $menues = [];
            $fehler = $e->getMessage();
        }

        $gerichte = Dish::with('category')->orderBy('name')->get();
        $gemerkt = DB::table('kantine_menueserve_zuordnungen')
            ->whereIn('ms_id', array_column($menues, 'ms_id'))->get()->keyBy('ms_id');

        foreach ($menues as &$m) {
            $z = $gemerkt->get($m['ms_id']);
            $m['gemerkt'] = $z !== null;
            $m['hauptspeise_id'] = $z ? $z->hauptspeise_id : MenueServeGerichte::vorschlag($m['hauptspeise'], $gerichte);
            $m['nachspeise_id'] = $z ? $z->nachspeise_id : MenueServeGerichte::vorschlag($m['nachspeise'], $gerichte);
        }
        unset($m);

        return view('schulkantine::dishes.menueserve', [
            'menues' => $menues,
            'fehler' => $fehler,
            'ab' => $ab,
            'gruppen' => $gerichte->groupBy(fn ($d) => $d->category?->name ?? 'ohne Kategorie')->sortKeys(),
            'arten' => MenueServeGerichte::ARTEN,
        ]);
    }

    public function save(Request $request)
    {
        $this->authorize($request);
        $request->validate([
            'menue' => ['array'],
            'menue.*.hauptspeise_id' => ['nullable', 'integer', 'exists:kantine_dishes,id'],
            'menue.*.nachspeise_id' => ['nullable', 'integer', 'exists:kantine_dishes,id'],
        ]);
        $ab = $this->ab($request);

        // Datum, Titel und Texte frisch aus Menü&Serve – das Formular liefert nur die Auswahl.
        $menues = collect((new MenueServeGerichte)->menuesAb($ab))->keyBy('ms_id');
        $gespeichert = 0;
        foreach ((array) $request->input('menue', []) as $msId => $wahl) {
            $m = $menues->get($msId);
            if (! $m) {
                continue;
            }
            DB::table('kantine_menueserve_zuordnungen')->updateOrInsert(['ms_id' => $msId], [
                'datum' => $m['datum'],
                'titel' => mb_substr($m['titel'], 0, 100),
                'hauptspeise_text' => $m['hauptspeise'] !== null ? mb_substr($m['hauptspeise'], 0, 255) : null,
                'nachspeise_text' => $m['nachspeise'] !== null ? mb_substr($m['nachspeise'], 0, 255) : null,
                'hauptspeise_id' => ($wahl['hauptspeise_id'] ?? null) ?: null,
                'nachspeise_id' => ($wahl['nachspeise_id'] ?? null) ?: null,
                'updated_by' => $request->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            // Fleischart des Menüs an die Hauptspeise, wenn dort noch keine eigene steht.
            if ($m['art'] && ! empty($wahl['hauptspeise_id'])) {
                $haupt = Dish::with('unsuitableDiets')->find($wahl['hauptspeise_id']);
                if ($haupt && in_array($haupt->fleischart, [null, 'fleisch'], true)) {
                    $this->fleischartSetzen($haupt, MenueServeGerichte::ART_ZU_FLEISCHART[$m['art']]);
                }
            }
            $gespeichert++;
        }

        $zurueck = redirect()->route('module.schulkantine.dishes.menueserve', ['ab' => $ab->format('Y-m-d')]);
        if ($request->input('aktion') !== 'speiseplan') {
            return $zurueck->with('status', "Zuordnung für {$gespeichert} Menüs gespeichert.");
        }

        // Zusätzlich in den Speiseplan der aktiven Saison eintragen.
        $season = Season::where('is_active', true)->first();
        if (! $season) {
            return $zurueck->withErrors(['speiseplan' => 'Es ist keine Saison aktiv.']);
        }
        $zuordnungen = DB::table('kantine_menueserve_zuordnungen')
            ->whereIn('ms_id', $menues->keys())->orderBy('datum')->get()
            ->each(fn ($z) => $z->linie = $menues->get($z->ms_id)['linie'] ?? null);
        $ergebnis = (new MenueServeSpeiseplan)->uebernehmen($season, $zuordnungen);
        $ok = count(array_filter($ergebnis, fn ($e) => $e['ok']));

        return $zurueck
            ->with('status', "Zuordnung gespeichert, {$ok} von ".count($ergebnis).' Menüs in den Speiseplan übernommen.')
            ->with('speiseplan_ergebnis', $ergebnis);
    }

    /**
     * Fleischarten (Symbole) aus der Menü&Serve-Historie nachtragen – nur bei Gerichten
     * ohne Angabe oder mit dem allgemeinen „Fleisch" (vom Nachtrag aus „nicht geeignet für").
     */
    public function symbole(Request $request)
    {
        $this->authorize($request);

        $gerichte = Dish::with(['allergens', 'unsuitableDiets'])->orderBy('name')->get();
        try {
            $arten = (new MenueServeGerichte)->fleischartJeGericht($gerichte);
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['symbole' => 'Menü&Serve ist nicht lesbar: '.$e->getMessage()]);
        }

        $gesetzt = [];
        $behalten = 0;
        foreach ($gerichte->whereIn('id', array_keys($arten)) as $dish) {
            if ($dish->fleischart !== null && $dish->fleischart !== 'fleisch') {
                $behalten++;

                continue;
            }
            $this->fleischartSetzen($dish, $arten[$dish->id]);
            $gesetzt[] = ['name' => $dish->name, 'symbol' => $dish->symbol()];
        }

        return back()
            ->with('status', count($gesetzt).' Fleischarten aus Menü&Serve nachgetragen.')
            ->with('symbole_ergebnis', ['gesetzt' => $gesetzt, 'behalten' => $behalten]);
    }

    /** Fleischart setzen und die Diäten danach neu ableiten (Handauswahl bleibt). */
    private function fleischartSetzen(Dish $dish, string $art): void
    {
        $geeignet = Diet::pluck('id')->diff($dish->unsuitableDiets->pluck('id'))->values()->all();
        $dish->update(['fleischart' => $art]);
        $dish->dietenSetzen($geeignet);
    }

    private function ab(Request $request): Carbon
    {
        $wert = (string) $request->input('ab', '');

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $wert)) {
            return Carbon::parse($wert)->startOfDay();
        }

        // Standard: ab dem Start der Intranet-Kantine, danach ab heute.
        return Carbon::today()->max(Carbon::parse(self::START));
    }

    private function authorize(Request $request): void
    {
        abort_unless(Access::darfMenuepunkt($request->user(), 'dishes'), 403, 'Kein Zugriff auf diese Kantinen-Seite.');
    }
}
