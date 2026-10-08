<?php

namespace Intranet\Modules\Schulkantine\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Intranet\Modules\Schulkantine\Models\Dish;
use Intranet\Modules\Schulkantine\Support\Access;
use Intranet\Modules\Schulkantine\Support\MenueServeGerichte;

/**
 * Menü&Serve-Menüs unseren Gerichten zuordnen: je Menü (Tag + Menülinie) eine
 * Hauptspeise und eine Nachspeise. Die Zuordnung wird nur gemerkt
 * (kantine_menueserve_zuordnungen) – der Speiseplan bleibt unberührt.
 */
class MenueServeImportController
{
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
            $gespeichert++;
        }

        return redirect()->route('module.schulkantine.dishes.menueserve', ['ab' => $ab->format('Y-m-d')])
            ->with('status', "Zuordnung für {$gespeichert} Menüs gespeichert.");
    }

    private function ab(Request $request): Carbon
    {
        $wert = (string) $request->input('ab', '');

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $wert) ? Carbon::parse($wert)->startOfDay() : Carbon::today();
    }

    private function authorize(Request $request): void
    {
        abort_unless(Access::darfMenuepunkt($request->user(), 'dishes'), 403, 'Kein Zugriff auf diese Kantinen-Seite.');
    }
}
