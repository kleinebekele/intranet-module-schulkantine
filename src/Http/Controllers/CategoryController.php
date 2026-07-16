<?php

namespace Intranet\Modules\Schulkantine\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Intranet\Modules\Schulkantine\Models\Category;

/**
 * Verwaltung der Kategorien. Vorerst nur für Administratoren.
 */
class CategoryController
{
    public function index(Request $request)
    {
        $this->authorizeAdmin($request);

        $categories = Category::orderBy('sort_order')->orderBy('name')->get();

        return view('schulkantine::categories.index', compact('categories'));
    }

    public function create(Request $request)
    {
        $this->authorizeAdmin($request);

        return view('schulkantine::categories.form', [
            // Vorbestellbar ist der Normalfall – sonst legt man aus Versehen eine
            // Kategorie an, die in der Vorbestellung gar nicht auftaucht.
            'category' => new Category(['is_active' => true, 'sort_order' => 0, 'allows_preorder' => true]),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeAdmin($request);

        $category = Category::create($this->validated($request));

        return redirect()
            ->route('module.schulkantine.categories.index')
            ->with('status', 'Kategorie „'.$category->name.'" wurde angelegt.');
    }

    public function edit(Request $request, Category $category)
    {
        $this->authorizeAdmin($request);

        return view('schulkantine::categories.form', compact('category'));
    }

    public function update(Request $request, Category $category)
    {
        $this->authorizeAdmin($request);

        $category->update($this->validated($request));

        return redirect()
            ->route('module.schulkantine.categories.index')
            ->with('status', 'Kategorie wurde gespeichert.');
    }

    public function destroy(Request $request, Category $category)
    {
        $this->authorizeAdmin($request);

        $category->delete();

        return redirect()
            ->route('module.schulkantine.categories.index')
            ->with('status', 'Kategorie wurde gelöscht.');
    }

    // ---------------------------------------------------------------- Helfer

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        $walkin = $request->boolean('allows_walkin');
        $preorder = $request->boolean('allows_preorder');

        // Wäre weder vorbestellbar noch am Tresen zu haben: Die Gerichte dieser
        // Kategorie könnten dann von niemandem mehr bezogen werden – ein Zustand,
        // den man nur aus Versehen erzeugt.
        if (! $walkin && ! $preorder) {
            throw ValidationException::withMessages([
                'allows_preorder' => 'Eine Kategorie muss mindestens auf einem Weg erhältlich sein: '
                    .'vorbestellbar und/oder spontane Abholung.',
            ]);
        }

        return [
            'name' => $request->string('name')->toString(),
            'allows_walkin' => $walkin,
            'allows_preorder' => $preorder,
            'sort_order' => (int) $request->input('sort_order', 0),
            'color' => $request->input('color') ?: null,
            'is_active' => $request->boolean('is_active'),
        ];
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Nur Administratoren dürfen die Kantine verwalten.');
    }
}
