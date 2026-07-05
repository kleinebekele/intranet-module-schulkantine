<?php

namespace Intranet\Modules\Schulkantine\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Intranet\Modules\Schulkantine\Models\Allergen;
use Intranet\Modules\Schulkantine\Models\Budget;
use Intranet\Modules\Schulkantine\Models\Category;
use Intranet\Modules\Schulkantine\Models\ChildCategoryPermission;
use Intranet\Modules\Schulkantine\Models\CustomerGroup;
use Intranet\Modules\Schulkantine\Models\Diet;
use Intranet\Modules\Schulkantine\Models\NfcChip;

/**
 * „Meine Daten": JEDER eingeloggte Nutzer pflegt für seinen Haushalt (sich selbst
 * + seine Kinder) Sonderkost und NFC-Chips. Für die KINDER legen die Eltern
 * zusätzlich das Wochenbudget für Spontankäufe und die Kategorie-Freigaben
 * (Vorbestellung / Spontankauf) fest.
 */
class SonderkostController
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Haushalt: KINDER ZUERST, der Nutzer selbst zuletzt.
        $user->loadMissing(['kantineAllergens', 'kantineDiets', 'roles']);
        $children = $user->children()
            ->with(['kantineAllergens', 'kantineDiets', 'roles'])
            ->orderBy('name')->get();
        $members = $children->concat([$user])->unique('id')->values();

        $groups = CustomerGroup::all()->keyBy('role_id');
        $chips = NfcChip::active()->whereIn('user_id', $members->pluck('id'))->get()->groupBy('user_id');

        $household = $members->map(function (User $m) use ($groups, $chips, $user) {
            $group = CustomerGroup::forUser($m, $groups);
            $isOgs = $group?->ordering_mode === CustomerGroup::MODE_JA_NEIN;
            $mine = $chips->get($m->id, collect());

            // Budget & Freigaben legen Eltern für ihre (Nicht-OGS-)Kinder fest.
            $canLimits = ! $isOgs && $m->id !== $user->id;

            return [
                'user' => $m,
                'group' => $group,
                'isOgs' => $isOgs,
                'canLimits' => $canLimits,
                'weeklyBudget' => $canLimits ? Budget::weeklyAmount($m->id) : null,
                'perms' => $canLimits ? ChildCategoryPermission::mapFor($m->id) : collect(),
                'selAllergens' => $m->kantineAllergens->pluck('id')->all(),
                'selDiets' => $m->kantineDiets->pluck('id')->all(),
                'ownChips' => $mine->where('source', NfcChip::SOURCE_ELTERN)->values(),
                'schoolChips' => $mine->where('source', NfcChip::SOURCE_SCHULE)->values(),
            ];
        });

        $categories = Category::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();

        return view('schulkantine::sonderkost.index', [
            'household' => $household,
            'allergens' => Allergen::orderBy('code')->get(),
            'diets' => Diet::orderBy('name')->get(),
            'categories' => $categories,
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
            ->with('status', 'Verträglichkeiten von „'.$user->name.'" wurden gespeichert.');
    }

    /**
     * Wochenbudget (Spontankäufe) + Kategorie-Freigaben eines Kindes speichern.
     * Nur Eltern für ihre (Nicht-OGS-)Kinder.
     */
    public function saveLimits(Request $request, User $user)
    {
        $actor = $request->user();
        abort_if($actor->id === $user->id, 403, 'Budget & Freigaben legen die Eltern fest, nicht man selbst.');
        abort_unless($this->mayEditFor($actor, $user), 403, 'Du darfst das für diese Person nicht festlegen.');
        abort_if(
            CustomerGroup::forUser($user)?->ordering_mode === CustomerGroup::MODE_JA_NEIN,
            422,
            'Für OGS-Kinder gibt es kein Budget / keine Freigaben.'
        );

        $data = $request->validate([
            'weekly_budget' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
            'preorder' => ['array'],
            'preorder.*' => ['integer'],
            'walkin' => ['array'],
            'walkin.*' => ['integer'],
        ]);

        // Wochenbudget (allgemein).
        $general = Budget::where('user_id', $user->id)->whereNull('week_start')->first();
        if (! $request->filled('weekly_budget')) {
            $general?->delete();
        } else {
            $amount = (float) $data['weekly_budget'];
            $general
                ? $general->update(['amount' => $amount])
                : Budget::create(['user_id' => $user->id, 'week_start' => null, 'amount' => $amount]);
        }

        // Kategorie-Freigaben (Zeile nur speichern, wenn eingeschränkt).
        $preorder = collect($request->input('preorder', []))->map(fn ($v) => (int) $v);
        $walkin = collect($request->input('walkin', []))->map(fn ($v) => (int) $v);

        foreach (Category::where('is_active', true)->get() as $cat) {
            $mayPre = $preorder->contains($cat->id);
            $mayWalk = $cat->allows_walkin ? $walkin->contains($cat->id) : true;

            if ($mayPre && $mayWalk) {
                ChildCategoryPermission::where('user_id', $user->id)->where('category_id', $cat->id)->delete();
            } else {
                ChildCategoryPermission::updateOrCreate(
                    ['user_id' => $user->id, 'category_id' => $cat->id],
                    ['may_preorder' => $mayPre, 'may_walkin' => $mayWalk],
                );
            }
        }

        return redirect()
            ->route('module.schulkantine.sonderkost.index')
            ->with('status', 'Budget &amp; Freigaben von „'.$user->name.'" gespeichert.');
    }

    /** Nur für sich selbst oder ein eigenes Kind. */
    private function mayEditFor(User $actor, User $target): bool
    {
        return $actor->id === $target->id || $actor->children()->whereKey($target->id)->exists();
    }
}
