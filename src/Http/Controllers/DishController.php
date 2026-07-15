<?php

namespace Intranet\Modules\Schulkantine\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Intranet\Modules\Schulkantine\Models\Additive;
use Intranet\Modules\Schulkantine\Models\Allergen;
use Intranet\Modules\Schulkantine\Models\Category;
use Intranet\Modules\Schulkantine\Models\Diet;
use Intranet\Modules\Schulkantine\Models\Dish;
use Intranet\Modules\Schulkantine\Models\MealRating;

/**
 * Verwaltung des Gerichte-Katalogs inkl. Allergene/Zusatzstoffe/Diäten.
 * Vorerst nur für Administratoren.
 */
class DishController
{
    public function index(Request $request)
    {
        $this->authorizeAdmin($request);

        $search = trim((string) $request->query('search', ''));
        $categoryFilter = (string) $request->query('category', '');
        $statusFilter = (string) $request->query('status', '');

        // components.* wird für isBundle() und die effective*-Sets gebraucht (sonst N+1).
        $dishes = Dish::with([
            'category', 'allergens', 'additives', 'unsuitableDiets',
            'components.allergens', 'components.additives', 'components.unsuitableDiets',
        ])
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->when($categoryFilter !== '', fn ($q) => $categoryFilter === 'none'
                ? $q->whereNull('category_id')
                : $q->where('category_id', $categoryFilter))
            ->when($statusFilter === 'active', fn ($q) => $q->where('is_active', true))
            ->when($statusFilter === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('name')
            ->get();

        $categories = Category::orderBy('sort_order')->orderBy('name')->get();

        // Bewertungen (Daumen) je Gericht – aggregiert, anonym.
        $ratings = MealRating::query()
            ->whereIn('dish_id', $dishes->pluck('id'))
            ->get(['dish_id', 'rating'])
            ->groupBy('dish_id')
            ->map(fn ($group) => [
                'up' => $group->where('rating', MealRating::UP)->count(),
                'down' => $group->where('rating', MealRating::DOWN)->count(),
            ]);

        return view('schulkantine::dishes.index', compact('dishes', 'categories', 'search', 'categoryFilter', 'statusFilter', 'ratings'));
    }

    public function create(Request $request)
    {
        $this->authorizeAdmin($request);

        return view('schulkantine::dishes.form', $this->formData(new Dish(['is_active' => true, 'price' => 0])));
    }

    public function store(Request $request)
    {
        $this->authorizeAdmin($request);

        $data = $this->applyPhoto($request, $this->validated($request), null);
        $this->validateComponents($request, null);
        $dish = Dish::create($data);
        $this->syncRelations($dish, $request);

        return redirect()
            ->route('module.schulkantine.dishes.index')
            ->with('status', 'Gericht „'.$dish->name.'" wurde angelegt.');
    }

    public function edit(Request $request, Dish $dish)
    {
        $this->authorizeAdmin($request);

        $dish->load('allergens', 'additives', 'unsuitableDiets', 'components');

        return view('schulkantine::dishes.form', $this->formData($dish));
    }

    public function update(Request $request, Dish $dish)
    {
        $this->authorizeAdmin($request);

        $data = $this->applyPhoto($request, $this->validated($request), $dish);
        $this->validateComponents($request, $dish);
        $dish->update($data);
        $this->syncRelations($dish, $request);

        return redirect()
            ->route('module.schulkantine.dishes.index')
            ->with('status', 'Gericht wurde gespeichert.');
    }

    public function destroy(Request $request, Dish $dish)
    {
        $this->authorizeAdmin($request);

        // Löschschutz: Steckt das Gericht in einem Sparmenü, würde der
        // Fremdschlüssel die Bestandteil-Zeile still mit abräumen – das Sparmenü
        // verlöre einen Bestandteil, behielte aber seinen Preis. Erst das
        // Sparmenü auflösen.
        $bundles = $dish->partOfBundles()->pluck('name');
        if ($bundles->isNotEmpty()) {
            return back()->withErrors([
                'dish' => 'Dieses Gericht kann nicht gelöscht werden – es ist Bestandteil von: '.$bundles->join(', ').'.',
            ]);
        }

        if ($dish->photo_path) {
            Storage::disk('public')->delete($dish->photo_path);
        }

        $dish->delete();

        return redirect()
            ->route('module.schulkantine.dishes.index')
            ->with('status', 'Gericht wurde gelöscht.');
    }

    // ---------------------------------------------------------------- Helfer

    /** @return array<string, mixed> */
    private function formData(Dish $dish): array
    {
        // Als Bestandteil taugt jedes Gericht, das selbst kein Sparmenü ist
        // (keine Verschachtelung) und nicht das Gericht selbst.
        $candidates = Dish::with('category')
            ->whereDoesntHave('components')
            ->when($dish->exists, fn ($q) => $q->where('id', '!=', $dish->id))
            ->orderBy('name')
            ->get()
            // Nach Kategorie-Reihenfolge gruppieren (Hauptmenü vor Nachspeise),
            // nicht nach Zufall der Namen.
            ->sortBy(fn (Dish $d) => $d->category?->sort_order ?? PHP_INT_MAX)
            ->values();

        return [
            'dish' => $dish,
            'categories' => Category::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'allergens' => Allergen::orderBy('code')->get(),
            'additives' => Additive::orderBy('id')->get(),
            'diets' => Diet::orderBy('name')->get(),
            'componentCandidates' => $candidates->groupBy(fn (Dish $d) => $d->category?->name ?? 'Ohne Kategorie'),
            // Ein Gericht, das selbst schon Bestandteil ist, darf kein Sparmenü
            // werden – das ergäbe eine Verschachtelung.
            'canBeBundle' => ! $dish->exists || ! $dish->partOfBundles()->exists(),
        ];
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'exists:kantine_categories,id'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'photo' => ['nullable', 'image', 'max:4096'],
            'remove_photo' => ['nullable', 'boolean'],
            'allergens' => ['array'],
            'allergens.*' => ['integer', 'exists:kantine_allergens,id'],
            'additives' => ['array'],
            'additives.*' => ['integer', 'exists:kantine_additives,id'],
            'diets' => ['array'],
            'diets.*' => ['integer', 'exists:kantine_diets,id'],
            'components' => ['array'],
            'components.*' => ['integer', 'exists:kantine_dishes,id'],
        ]);

        return [
            'name' => $request->string('name')->toString(),
            'category_id' => $request->input('category_id') ?: null,
            'description' => $request->input('description') ?: null,
            'price' => $request->input('price'),
            'is_active' => $request->boolean('is_active'),
        ];
    }

    /**
     * Übernimmt einen Foto-Upload bzw. entfernt das Foto.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applyPhoto(Request $request, array $data, ?Dish $dish): array
    {
        if ($request->hasFile('photo')) {
            if ($dish?->photo_path) {
                Storage::disk('public')->delete($dish->photo_path);
            }
            $data['photo_path'] = $request->file('photo')->store('kantine/dishes', 'public');
        } elseif ($request->boolean('remove_photo') && $dish?->photo_path) {
            Storage::disk('public')->delete($dish->photo_path);
            $data['photo_path'] = null;
        }

        return $data;
    }

    private function syncRelations(Dish $dish, Request $request): void
    {
        $dish->allergens()->sync($request->input('allergens', []));
        $dish->additives()->sync($request->input('additives', []));
        $dish->unsuitableDiets()->sync($request->input('diets', []));

        // Bestandteile in der Reihenfolge der Speisefolge ablegen (Kategorie-
        // Reihenfolge: Hauptmenü vor Nachspeise), damit ein Sparmenü sich überall
        // gleich und sinnvoll liest – nicht in der zufälligen Klick-Reihenfolge.
        $ids = $this->componentIds($request);
        $ordered = Dish::with('category')->whereIn('id', $ids)->get()
            ->sortBy(fn (Dish $d) => [$d->category?->sort_order ?? PHP_INT_MAX, $d->name])
            ->pluck('id');

        $components = [];
        foreach ($ordered->values() as $i => $id) {
            $components[$id] = ['sort_order' => $i];
        }
        $dish->components()->sync($components);
    }

    /**
     * Prüft die Bestandteile eines Sparmenüs. Bewusst hier und nicht per DB-Regel:
     * SQLite/MySQL können „Bestandteil darf selbst kein Bündel sein" nicht abbilden.
     *
     * @throws ValidationException
     */
    private function validateComponents(Request $request, ?Dish $dish): void
    {
        $ids = $this->componentIds($request);

        if ($ids === []) {
            return; // normales Gericht – nichts zu prüfen
        }

        if (count($ids) < 2) {
            throw ValidationException::withMessages([
                'components' => 'Ein Sparmenü muss aus mindestens zwei Gerichten bestehen.',
            ]);
        }

        if ($dish && in_array($dish->id, $ids, true)) {
            throw ValidationException::withMessages([
                'components' => 'Ein Sparmenü kann sich nicht selbst enthalten.',
            ]);
        }

        // Keine Verschachtelung: weder darf ein Bestandteil ein Sparmenü sein …
        $nested = Dish::whereIn('id', $ids)->whereHas('components')->pluck('name');
        if ($nested->isNotEmpty()) {
            throw ValidationException::withMessages([
                'components' => 'Ein Sparmenü darf kein anderes Sparmenü enthalten: '.$nested->join(', ').'.',
            ]);
        }

        // … noch darf ein Gericht, das selbst Bestandteil ist, zum Sparmenü werden.
        if ($dish && $dish->partOfBundles()->exists()) {
            throw ValidationException::withMessages([
                'components' => 'Dieses Gericht ist bereits Bestandteil eines Sparmenüs und kann deshalb selbst keins werden.',
            ]);
        }
    }

    /** @return array<int> */
    private function componentIds(Request $request): array
    {
        return collect($request->input('components', []))
            ->map(fn ($v) => (int) $v)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Nur Administratoren dürfen die Kantine verwalten.');
    }
}
