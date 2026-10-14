<?php

namespace Intranet\Modules\Schulkantine\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Intranet\Modules\Schulkantine\Models\Diet;
use Intranet\Modules\Schulkantine\Models\Dish;
use Intranet\Modules\Schulkantine\Models\Season;
use Intranet\Modules\Schulkantine\Support\Access;
use Intranet\Modules\Schulkantine\Support\MenueServeBuchungen;
use Intranet\Modules\Schulkantine\Support\MenueServeGerichte;
use Intranet\Modules\Schulkantine\Support\MenueServeSpeiseplan;

/**
 * Menü&Serve-Menüs unseren Gerichten zuordnen: je Menü (Tag + Menülinie) eine
 * Hauptspeise und eine Nachspeise. Die Zuordnung wird nur gemerkt
 * (kantine_menueserve_zuordnungen) – der Speiseplan bleibt unberührt.
 */
class MenueServeImportController
{
    public function index(Request $request, Season $season)
    {
        $this->authorize($request, $season);
        $ab = $this->ab($request, $season);

        try {
            $menues = (new MenueServeGerichte)->menuesAb($ab, true);
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
            if ($m['snack']) {
                // Snacks laufen alle über ein festes Gericht.
                $m['hauptspeise_id'] = $z ? $z->hauptspeise_id : ($gerichte->firstWhere('id', MenueServeGerichte::snackGericht())?->id);
                $m['nachspeise_id'] = null;
            } else {
                $m['hauptspeise_id'] = $z ? $z->hauptspeise_id : MenueServeGerichte::vorschlag($m['hauptspeise'], $gerichte);
                $m['nachspeise_id'] = $z ? $z->nachspeise_id : MenueServeGerichte::vorschlag($m['nachspeise'], $gerichte);
            }
        }
        unset($m);

        return view('schulkantine::dishes.menueserve', [
            'menues' => $menues,
            'fehler' => $fehler,
            'ab' => $ab,
            'gruppen' => $gerichte->groupBy(fn ($d) => $d->category?->name ?? 'ohne Kategorie')->sortKeys(),
            'arten' => MenueServeGerichte::ARTEN,
            'verschiedenes' => MenueServeGerichte::verschiedenes(),
        ]);
    }

    public function save(Request $request, Season $season)
    {
        $this->authorize($request, $season);
        $request->validate([
            'menue' => ['array'],
            'menue.*.hauptspeise_id' => ['nullable', 'integer', 'exists:kantine_dishes,id'],
            'menue.*.nachspeise_id' => ['nullable', 'integer', 'exists:kantine_dishes,id'],
        ]);
        $ab = $this->ab($request, $season);

        // Datum, Titel und Texte frisch aus Menü&Serve – das Formular liefert nur die Auswahl.
        $menues = collect((new MenueServeGerichte)->menuesAb($ab, true))->keyBy('ms_id');
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
                'nachspeise_id' => $m['snack'] ? null : (($wahl['nachspeise_id'] ?? null) ?: null),
                'updated_by' => $request->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            // Fleischart des Menüs an die Hauptspeise, wenn dort noch keine eigene steht.
            if ($m['art'] && ! $m['snack'] && ! empty($wahl['hauptspeise_id'])) {
                $haupt = Dish::with('unsuitableDiets')->find($wahl['hauptspeise_id']);
                if ($haupt && in_array($haupt->fleischart, [null, 'fleisch'], true)) {
                    $this->fleischartSetzen($haupt, MenueServeGerichte::ART_ZU_FLEISCHART[$m['art']]);
                }
            }
            $gespeichert++;
        }

        $zurueck = redirect()->route('module.schulkantine.seasons.menueserve', ['ab' => $ab->format('Y-m-d')]);
        if ($request->input('aktion') !== 'speiseplan') {
            return $zurueck->with('status', "Zuordnung für {$gespeichert} Menüs gespeichert.");
        }

        // Zusätzlich in den Speiseplan dieser Saison eintragen.
        $zuordnungen = DB::table('kantine_menueserve_zuordnungen')
            ->whereIn('ms_id', $menues->keys())->orderBy('datum')->get()
            ->each(function ($z) use ($menues) {
                $z->linie = $menues->get($z->ms_id)['linie'] ?? null;
                $z->snack = (bool) ($menues->get($z->ms_id)['snack'] ?? false);
            });
        $ergebnis = (new MenueServeSpeiseplan)->uebernehmen($season, $zuordnungen, $request->boolean('andere_entfernen'),
            $request->boolean('verschiedenes') ? MenueServeGerichte::verschiedenes() : null);
        $ok = count(array_filter($ergebnis, fn ($e) => $e['ok']));

        return $zurueck
            ->with('status', "Zuordnung gespeichert, {$ok} von ".count($ergebnis).' Menüs in den Speiseplan übernommen.')
            ->with('speiseplan_ergebnis', $ergebnis);
    }

    /**
     * Fleischarten (Symbole) aus der Menü&Serve-Historie nachtragen – nur bei Gerichten
     * ohne Angabe oder mit dem allgemeinen „Fleisch" (vom Nachtrag aus „nicht geeignet für").
     */
    public function symbole(Request $request, Season $season)
    {
        $this->authorize($request, $season);

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

    /** Vorschau: welche Menü&Serve-Buchungen würden als Bestellung übernommen? */
    public function buchungen(Request $request, Season $season)
    {
        $this->authorize($request, $season);
        $ab = $this->ab($request, $season);

        try {
            $buchungen = new MenueServeBuchungen;
            $plan = $buchungen->plan($season, $ab);
            $unbekannt = $buchungen->unbekannte($plan);
            $vorhanden = $buchungen->vorhandeneJeTag($season, $plan);
            $fehler = null;
        } catch (\Throwable $e) {
            report($e);
            $plan = [];
            $unbekannt = [];
            $vorhanden = [];
            $fehler = $e->getMessage();
        }

        return view('schulkantine::dishes.menueserve-buchungen', [
            'plan' => $plan, 'unbekannt' => $unbekannt, 'fehler' => $fehler, 'ab' => $ab,
            'vorhanden' => $vorhanden,
            'statusText' => MenueServeBuchungen::STATUS,
        ]);
    }

    public function buchungenUebernehmen(Request $request, Season $season)
    {
        $this->authorize($request, $season);
        $ab = $this->ab($request, $season);

        // Nur ein Tag (Knopf in der Tageszeile) oder alle ab $ab.
        $tag = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->input('tag')) ? (string) $request->input('tag') : null;
        $e = (new MenueServeBuchungen)->uebernehmen($season, $ab, $request->user()->id, $tag);

        return redirect()->route('module.schulkantine.seasons.menueserve.buchungen', ['ab' => $ab->format('Y-m-d')])
            ->with('status', "{$e['angelegt']} Buchungen aus Menü&Serve als Bestellung übernommen, {$e['geloescht']} bisherige Bestellzeilen ersetzt"
                .($tag ? ' ('.Carbon::parse($tag)->format('d.m.Y').').' : '.'));
    }

    /**
     * Tab „OGS übernehmen": Abos aus der Menü&Serve-Historie (Standard: Saisonbeginn bis
     * gestern) und der tageweise Abgleich der Buchungen ab $ab mit den Abos.
     */
    public function ogs(Request $request, Season $season)
    {
        $this->authorize($request, $season);
        $von = $this->datum($request->input('von')) ?? $season->start_date->copy();
        $bis = $this->datum($request->input('bis')) ?? Carbon::yesterday();
        $ab = $this->ab($request, $season);

        try {
            $buchungen = new MenueServeBuchungen;
            $muster = $buchungen->ogsMuster($season, $von, $bis);
            $ogs = $buchungen->ogsAbgleich($season, $buchungen->plan($season, $ab));
            $fehler = null;
        } catch (\Throwable $e) {
            report($e);
            $muster = null;
            $ogs = null;
            $fehler = $e->getMessage();
        }

        return view('schulkantine::dishes.menueserve-ogs', [
            'muster' => $muster, 'ogs' => $ogs, 'fehler' => $fehler, 'von' => $von, 'bis' => $bis, 'ab' => $ab,
            'schwelle' => MenueServeBuchungen::MUSTER_SCHWELLE,
        ]);
    }

    public function ogsMusterSetzen(Request $request, Season $season)
    {
        $this->authorize($request, $season);
        $data = $request->validate([
            'auswahl' => ['required', 'array', 'min:1'],
            'auswahl.*' => ['integer', 'exists:users,id'],
            'tage' => ['array'],
            'tage.*' => ['array'],
            'tage.*.*' => ['integer', 'between:1,7'],
            'alle' => ['required', 'array'],
            'alle.*' => ['integer', 'between:1,7'],
        ], ['auswahl.required' => 'Bitte mindestens ein Kind ankreuzen.']);

        $muster = [];
        foreach ($data['auswahl'] as $userId) {
            $muster[(int) $userId] = $data['tage'][$userId] ?? [];
        }
        $n = (new MenueServeBuchungen)->abosSetzen($season, $muster, array_map('intval', $data['alle']));

        return redirect()->route('module.schulkantine.seasons.menueserve.ogs', $request->only('von', 'bis', 'ab'))
            ->with('status', "Abo für {$n} OGS-Kinder gesetzt.");
    }

    private function datum(mixed $wert): ?Carbon
    {
        return is_string($wert) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $wert) ? Carbon::parse($wert)->startOfDay() : null;
    }

    /** OGS-Kinder an Menü&Serve angleichen (An-/Abmeldung je abweichendem Tag) – alle oder ein Tag. */
    public function ogsAngleichen(Request $request, Season $season)
    {
        $this->authorize($request, $season);
        $ab = $this->ab($request, $season);
        $tag = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->input('tag')) ? (string) $request->input('tag') : null;

        $buchungen = new MenueServeBuchungen;
        $n = $buchungen->ogsAngleichen($season, $buchungen->plan($season, $ab), $tag);

        return redirect()->route('module.schulkantine.seasons.menueserve.ogs', ['ab' => $ab->format('Y-m-d')])
            ->with('status', "OGS: {$n} Tage an Menü&Serve angeglichen".($tag ? ' ('.Carbon::parse($tag)->format('d.m.Y').').' : '.'));
    }

    private function ab(Request $request, Season $season): Carbon
    {
        $wert = (string) $request->input('ab', '');

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $wert)) {
            return Carbon::parse($wert)->startOfDay();
        }

        // Standard: das ganze Schuljahr (ab Saisonbeginn).
        return $season->start_date->copy()->startOfDay();
    }

    /**
     * Nur die Verwaltung der Saisons, und nur für die Saison des Umstiegs
     * (config schulkantine.menueserve_saison). Setzt die Saison als Standard für
     * route() und gibt sie allen Ansichten mit.
     */
    private function authorize(Request $request, Season $season): void
    {
        abort_unless(Access::darfMenuepunkt($request->user(), 'seasons'), 403, 'Kein Zugriff auf diese Mensa-Seite.');
        abort_unless(self::fuerSaison($season), 404);
        \Illuminate\Support\Facades\URL::defaults(['season' => $season->id]);
        view()->share('season', $season);
    }

    /** Gibt es den Menü&Serve-Import für diese Saison? */
    public static function fuerSaison(Season $season): bool
    {
        return (int) $season->id === (int) config('schulkantine.menueserve_saison', 1);
    }
}
