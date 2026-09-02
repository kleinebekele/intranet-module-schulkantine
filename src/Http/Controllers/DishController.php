<?php

namespace Intranet\Modules\Schulkantine\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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

        // Sortierung (Dropdown über der Tabelle). Whitelist → Spalte + Richtung.
        $sort = (string) $request->query('sort', 'name');
        $sortMap = [
            'name' => ['name', 'asc'],
            'created_desc' => ['created_at', 'desc'],
            'created_asc' => ['created_at', 'asc'],
            'price_asc' => ['price', 'asc'],
            'price_desc' => ['price', 'desc'],
        ];
        if (! isset($sortMap[$sort])) {
            $sort = 'name';
        }
        [$sortColumn, $sortDirection] = $sortMap[$sort];

        $dishes = Dish::with(['category', 'allergens', 'additives', 'unsuitableDiets'])
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->when($categoryFilter !== '', fn ($q) => $categoryFilter === 'none'
                ? $q->whereNull('category_id')
                : $q->where('category_id', $categoryFilter))
            ->when($statusFilter === 'active', fn ($q) => $q->where('is_active', true))
            ->when($statusFilter === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy($sortColumn, $sortDirection)
            // Zweitschlüssel Name, damit gleiche Preise/Daten stabil sortiert bleiben.
            ->when($sortColumn !== 'name', fn ($q) => $q->orderBy('name'))
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

        return view('schulkantine::dishes.index', compact('dishes', 'categories', 'search', 'categoryFilter', 'statusFilter', 'ratings', 'sort'));
    }

    public function create(Request $request)
    {
        $this->authorizeAdmin($request);

        return view('schulkantine::dishes.form',
            $this->formData(new Dish(['is_active' => true, 'price' => 0])) + ['filters' => $this->filterParams($request)]);
    }

    public function store(Request $request)
    {
        $this->authorizeAdmin($request);

        $data = $this->applyPhoto($request, $this->validated($request), null);
        $dish = Dish::create($data);
        $this->syncRelations($dish, $request);

        return redirect()
            ->route('module.schulkantine.dishes.index', $this->filterParams($request))
            ->with('status', 'Gericht „'.$dish->name.'" wurde angelegt.');
    }

    public function edit(Request $request, Dish $dish)
    {
        $this->authorizeAdmin($request);

        $dish->load('allergens', 'additives', 'unsuitableDiets');

        return view('schulkantine::dishes.form', $this->formData($dish) + ['filters' => $this->filterParams($request)]);
    }

    public function update(Request $request, Dish $dish)
    {
        $this->authorizeAdmin($request);

        $data = $this->applyPhoto($request, $this->validated($request), $dish);
        $dish->update($data);
        $this->syncRelations($dish, $request);

        return redirect()
            ->route('module.schulkantine.dishes.index', $this->filterParams($request))
            ->with('status', 'Gericht wurde gespeichert.');
    }

    public function destroy(Request $request, Dish $dish)
    {
        $this->authorizeAdmin($request);

        if ($dish->photo_path) {
            Storage::disk('public')->delete($dish->photo_path);
        }

        $dish->delete();

        return redirect()
            ->route('module.schulkantine.dishes.index', $this->filterParams($request))
            ->with('status', 'Gericht wurde gelöscht.');
    }

    // ---------------------------------------------------------------- Helfer

    /** @return array<string, mixed> */
    private function formData(Dish $dish): array
    {
        return [
            'dish' => $dish,
            'categories' => Category::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'allergens' => Allergen::orderBy('code')->get(),
            'additives' => Additive::orderBy('id')->get(),
            'diets' => Diet::orderBy('name')->get(),
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
    }

    /**
     * Die aktiven Listen-Filter (Suche/Kategorie/Status/Sortierung), damit die
     * Gerichte-Liste nach dem Bearbeiten wieder mit demselben Filter erscheint.
     *
     * @return array<string, string>
     */
    private function filterParams(Request $request): array
    {
        return array_filter(
            $request->only(['search', 'category', 'status', 'sort']),
            fn ($v) => $v !== null && $v !== '',
        );
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Nur Administratoren dürfen die Kantine verwalten.');
    }
}
