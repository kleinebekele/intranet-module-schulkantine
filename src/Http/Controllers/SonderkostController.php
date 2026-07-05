<?php

namespace Intranet\Modules\Schulkantine\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Intranet\Modules\Schulkantine\Models\Allergen;
use Intranet\Modules\Schulkantine\Models\CustomerGroup;
use Intranet\Modules\Schulkantine\Models\Diet;

/**
 * Selbstbedienung für die Sonderkost: JEDER eingeloggte Nutzer pflegt seine
 * eigenen Allergien/Diäten – und als Elternteil auch die seiner Kinder.
 *
 * (Admins pflegen zusätzlich alle Teilnehmer über den EaterController; hier
 * geht es ausschließlich um den eigenen Haushalt = ich + meine Kinder.)
 */
class SonderkostController
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Haushalt: der Nutzer selbst + seine Kinder.
        $user->loadMissing(['kantineAllergens', 'kantineDiets', 'roles']);
        $children = $user->children()
            ->with(['kantineAllergens', 'kantineDiets', 'roles'])
            ->orderBy('name')->get();
        $members = collect([$user])->concat($children)->unique('id')->values();

        $groups = CustomerGroup::all()->keyBy('role_id');

        $household = $members->map(fn (User $m) => [
            'user' => $m,
            'group' => CustomerGroup::forUser($m, $groups),
            'selAllergens' => $m->kantineAllergens->pluck('id')->all(),
            'selDiets' => $m->kantineDiets->pluck('id')->all(),
        ]);

        return view('schulkantine::sonderkost.index', [
            'household' => $household,
            'allergens' => Allergen::orderBy('code')->get(),
            'diets' => Diet::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        abort_unless($this->mayEditFor($request->user(), $user), 403,
            'Du darfst die Sonderkost dieser Person nicht bearbeiten.');

        $request->validate([
            'allergens' => ['array'],
            'allergens.*' => ['integer', 'exists:kantine_allergens,id'],
            'diets' => ['array'],
            'diets.*' => ['integer', 'exists:kantine_diets,id'],
        ]);

        $user->kantineAllergens()->sync($request->input('allergens', []));
        $user->kantineDiets()->sync($request->input('diets', []));

        return redirect()
            ->route('module.schulkantine.sonderkost.index')
            ->with('status', 'Sonderkost von „'.$user->name.'" wurde gespeichert.');
    }

    /** Nur für sich selbst oder ein eigenes Kind. */
    private function mayEditFor(User $actor, User $target): bool
    {
        return $actor->id === $target->id || $actor->children()->whereKey($target->id)->exists();
    }
}
