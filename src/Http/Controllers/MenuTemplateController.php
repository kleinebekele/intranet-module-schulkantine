<?php

namespace Intranet\Modules\Schulkantine\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Intranet\Modules\Schulkantine\Models\MenuTemplate;
use Intranet\Modules\Schulkantine\Models\Season;

/**
 * Verwaltung der Menü-Vorlagen einer Saison (Ersatz für Sparmenüs). Gepflegt
 * wird das im Tab „Menüs" der Saison-Ansicht. Eine Vorlage legt Name, Preis,
 * Wochentage und die Kategorie-Slots fest – die konkreten Gerichte kommen erst
 * im Speiseplan dazu. Nur Administratoren.
 */
class MenuTemplateController
{
    public function store(Request $request, Season $season)
    {
        $this->authorizeAdmin($request);

        $data = $this->validated($request);

        $template = $season->menuTemplates()->create([
            'name' => $data['name'],
            'price' => $data['price'],
            'weekdays' => $data['weekdays'],
            'is_active' => $data['is_active'],
            'sort_order' => (int) $season->menuTemplates()->max('sort_order') + 1,
        ]);
        $this->syncSlots($template, $data['slots']);

        return redirect()
            ->route('module.schulkantine.seasons.show', ['season' => $season, 'tab' => 'menues'])
            ->with('status', 'Menü „'.$template->name.'" wurde angelegt.');
    }

    public function update(Request $request, MenuTemplate $menuTemplate)
    {
        $this->authorizeAdmin($request);

        $data = $this->validated($request);

        $menuTemplate->update([
            'name' => $data['name'],
            'price' => $data['price'],
            'weekdays' => $data['weekdays'],
            'is_active' => $data['is_active'],
        ]);
        $menuTemplate->slots()->delete();
        $this->syncSlots($menuTemplate, $data['slots']);

        return redirect()
            ->route('module.schulkantine.seasons.show', ['season' => $menuTemplate->season_id, 'tab' => 'menues'])
            ->with('status', 'Menü wurde gespeichert.');
    }

    public function destroy(Request $request, MenuTemplate $menuTemplate)
    {
        $this->authorizeAdmin($request);

        $seasonId = $menuTemplate->season_id;
        $menuTemplate->delete();

        return redirect()
            ->route('module.schulkantine.seasons.show', ['season' => $seasonId, 'tab' => 'menues'])
            ->with('status', 'Menü wurde gelöscht.');
    }

    // ---------------------------------------------------------------- Helfer

    /** @return array{name:string, price:float, weekdays:array<int>, is_active:bool, slots:array<int,array{category_id:int,quantity:int}>} */
    private function validated(Request $request): array
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0', 'max:999.99'],
            'weekdays' => ['array'],
            'weekdays.*' => ['integer', 'between:1,7'],
            'slots' => ['required', 'array', 'min:1'],
            'slots.*.category_id' => ['nullable', 'integer', 'exists:kantine_categories,id'],
            'slots.*.quantity' => ['nullable', 'integer', 'min:1', 'max:20'],
        ], [
            'slots.required' => 'Ein Menü braucht mindestens einen Kategorie-Slot.',
        ]);

        // Nur ausgefüllte Slot-Zeilen (Kategorie gewählt) übernehmen.
        $slots = collect($request->input('slots', []))
            ->filter(fn ($s) => ! empty($s['category_id']))
            ->map(fn ($s) => [
                'category_id' => (int) $s['category_id'],
                'quantity' => max(1, (int) ($s['quantity'] ?? 1)),
            ])
            ->values()
            ->all();

        if ($slots === []) {
            throw ValidationException::withMessages([
                'slots' => 'Bitte mindestens eine Kategorie mit Anzahl wählen.',
            ]);
        }

        return [
            'name' => $request->string('name')->toString(),
            'price' => (float) $request->input('price'),
            'weekdays' => collect($request->input('weekdays', []))->map(fn ($d) => (int) $d)->unique()->sort()->values()->all(),
            'is_active' => $request->boolean('is_active', true),
            'slots' => $slots,
        ];
    }

    /** @param  array<int,array{category_id:int,quantity:int}>  $slots */
    private function syncSlots(MenuTemplate $template, array $slots): void
    {
        foreach ($slots as $i => $slot) {
            $template->slots()->create([
                'category_id' => $slot['category_id'],
                'quantity' => $slot['quantity'],
                'sort_order' => $i,
            ]);
        }
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Nur Administratoren dürfen die Kantine verwalten.');
    }
}
