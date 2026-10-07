<?php

namespace Intranet\Modules\Schulkantine\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Intranet\Modules\Schulkantine\Models\Category;
use Intranet\Modules\Schulkantine\Models\Diet;
use Intranet\Modules\Schulkantine\Models\Dish;
use Intranet\Modules\Schulkantine\Support\Access;
use Intranet\Modules\Schulkantine\Support\MenueServeGerichte;

/**
 * Gerichte aus Menü&Serve übernehmen: Vorschau (liest nur) und Anlegen der
 * ausgewählten Titel. Vorhandene Gerichte (gleicher Name) bleiben unberührt.
 */
class MenueServeImportController
{
    public function index(Request $request)
    {
        $this->authorize($request);

        try {
            $daten = (new MenueServeGerichte)->lesen();
            $fehler = null;
        } catch (\Throwable $e) {
            report($e);
            $daten = ['linien' => [], 'gerichte' => []];
            $fehler = $e->getMessage();
        }

        return view('schulkantine::dishes.menueserve', $daten + [
            'fehler' => $fehler,
            'categories' => Category::orderBy('sort_order')->orderBy('name')->get(),
            'arten' => MenueServeGerichte::PREIS_ARTEN,
        ]);
    }

    public function import(Request $request)
    {
        $this->authorize($request);
        $request->validate([
            'auswahl' => ['required', 'array', 'min:1'],
            'kategorie' => ['array'],
            'kategorie.*' => ['nullable', 'integer', 'exists:kantine_categories,id'],
            'preis' => ['array'],
            'preis.*.*' => ['nullable', 'numeric', 'min:0'],
        ], ['auswahl.required' => 'Bitte mindestens ein Gericht auswählen.']);

        // Frisch lesen statt Formulardaten zu vertrauen – Titel und Notiz kommen aus Menü&Serve.
        $gerichte = collect((new MenueServeGerichte)->lesen()['gerichte'])->keyBy('key');
        $vorhanden = Dish::pluck('name')->mapWithKeys(fn ($n) => [MenueServeGerichte::schluessel($n) => true]);

        $diaeten = Diet::pluck('id', 'name');
        $angelegt = 0;
        $ohneKategorie = 0;
        DB::transaction(function () use ($request, $gerichte, $vorhanden, $diaeten, &$angelegt, &$ohneKategorie) {
            foreach ((array) $request->input('auswahl') as $key) {
                $g = $gerichte->get($key);
                if (! $g || isset($vorhanden[$key])) {
                    continue;
                }
                $kategorie = $request->input('kategorie.'.$g['linie']);
                if (! $kategorie) {
                    $ohneKategorie++;

                    continue;
                }
                $preise = collect((array) $request->input('preis.'.$g['linie'], []))
                    ->only(MenueServeGerichte::PREIS_ARTEN)
                    ->filter(fn ($p) => $p !== null && $p !== '')
                    ->map(fn ($p) => round((float) str_replace(',', '.', (string) $p), 2))
                    ->all();

                $dish = Dish::create([
                    'category_id' => (int) $kategorie,
                    'name' => mb_substr($g['titel'], 0, 255),
                    'description' => $g['notiz'] !== '' ? $g['notiz'] : null,
                    // Hauptpreis = teuerster Vertragspreis (gilt für alle ohne passenden Vertrag).
                    'price' => $preise ? max($preise) : 0,
                    'contract_prices' => $preise ?: null,
                    'is_active' => true,
                ]);
                // Fleischart → „nicht geeignet für" (vegetarisch, halal …).
                $nichtFuer = MenueServeGerichte::NICHT_FUER[$g['art']] ?? [];
                $dish->unsuitableDiets()->sync($diaeten->only($nichtFuer)->values()->all());
                $vorhanden[$key] = true;
                $angelegt++;
            }
        });

        $meldung = "{$angelegt} Gerichte aus Menü&Serve angelegt.";
        if ($ohneKategorie > 0) {
            $meldung .= " {$ohneKategorie} übersprungen, weil ihrer Menülinie keine Kategorie zugeordnet war.";
        }

        return redirect()->route('module.schulkantine.dishes.index')->with('status', $meldung);
    }

    private function authorize(Request $request): void
    {
        abort_unless(Access::darfMenuepunkt($request->user(), 'dishes'), 403, 'Kein Zugriff auf diese Kantinen-Seite.');
    }
}
