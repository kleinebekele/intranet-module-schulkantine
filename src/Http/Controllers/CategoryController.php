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

        // Neue Kategorien hinten anhängen; sortiert wird danach per Drag & Drop.
        $category = Category::create($this->validated($request) + [
            'sort_order' => (int) Category::max('sort_order') + 1,
        ]);

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

    /**
     * Neue Reihenfolge aus dem Drag & Drop der Liste (Array von ids in der
     * gewünschten Reihenfolge). Der Core liefert das Ziehen selbst mit: Ein
     * `data-sortable="<url>"` genügt, SortableJS POSTet dann `{ ids: [...] }`.
     *
     * Die Position ist der Listenindex – die alten, frei vergebenen Zahlen
     * (0, 10, 20 …) werden dabei zu einer lückenlosen Folge normalisiert.
     */
    public function reorder(Request $request)
    {
        $this->authorizeAdmin($request);

        $ids = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:kantine_categories,id'],
        ])['ids'];

        foreach ($ids as $position => $id) {
            Category::where('id', $id)->update(['sort_order' => $position]);
        }

        return response()->json(['ok' => true]);
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
            'color' => $request->input('color') ?: null,
            'is_active' => $request->boolean('is_active'),
        ];
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Nur Administratoren dürfen die Kantine verwalten.');
    }
}
