<?php

namespace Intranet\Modules\Schulkantine\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Intranet\Modules\Schulkantine\Models\Allergen;
use Intranet\Modules\Schulkantine\Models\CustomerGroup;
use Intranet\Modules\Schulkantine\Models\Diet;

/**
 * Teilnehmer-Verwaltung. Jeder Teilnehmer IST ein Benutzer (angelegt über den
 * Benutzer-Import). Die Kundengruppe ergibt sich AUS DEN ROLLEN des Benutzers
 * (siehe CustomerGroup::forUser) – hier gepflegt wird nur die Sonderkost.
 */
class EaterController
{
    public function index(Request $request)
    {
        $this->authorizeAdmin($request);

        $search = trim((string) $request->query('search', ''));

        $users = User::with(['roles', 'kantineAllergens', 'kantineDiets'])
            ->when($search !== '', fn ($q) => $q->where(function ($sub) use ($search) {
                $sub->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->orderBy('name')
            ->get();

        return view('schulkantine::eaters.index', [
            'users' => $users,
            'groups' => CustomerGroup::all()->keyBy('role_id'), // einmal laden → kein N+1
            'search' => $search,
        ]);
    }

    public function edit(Request $request, User $user)
    {
        $this->authorizeAdmin($request);

        $user->load(['roles', 'kantineAllergens', 'kantineDiets']);

        return view('schulkantine::eaters.form', [
            'user' => $user,
            'group' => CustomerGroup::forUser($user),
            'allergens' => Allergen::orderBy('code')->get(),
            'diets' => Diet::orderBy('name')->get(),
            'selAllergens' => $user->kantineAllergens->pluck('id')->all(),
            'selDiets' => $user->kantineDiets->pluck('id')->all(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $this->authorizeAdmin($request);

        $request->validate([
            'allergens' => ['array'],
            'allergens.*' => ['integer', 'exists:kantine_allergens,id'],
            'diets' => ['array'],
            'diets.*' => ['integer', 'exists:kantine_diets,id'],
        ]);

        $user->kantineAllergens()->sync($request->input('allergens', []));
        $user->kantineDiets()->sync($request->input('diets', []));

        return redirect()
            ->route('module.schulkantine.eaters.index')
            ->with('status', 'Sonderkost von „'.$user->name.'" wurde gespeichert.');
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Nur Administratoren dürfen die Kantine verwalten.');
    }
}
