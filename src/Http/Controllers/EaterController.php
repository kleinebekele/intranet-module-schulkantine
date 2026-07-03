<?php

namespace Intranet\Modules\Schulkantine\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Intranet\Modules\Schulkantine\Models\Allergen;
use Intranet\Modules\Schulkantine\Models\CustomerGroup;
use Intranet\Modules\Schulkantine\Models\Diet;
use Intranet\Modules\Schulkantine\Models\Season;

/**
 * Teilnehmer-Verwaltung. Jeder Teilnehmer IST ein Benutzer – angelegt werden
 * Benutzer über den Benutzer-Import (Core), nicht hier. Hier pflegt man nur die
 * kantinen-spezifischen Zusatzdaten: Gruppe je Saison und Sonderkost.
 */
class EaterController
{
    public function index(Request $request)
    {
        $this->authorizeAdmin($request);

        $search = trim((string) $request->query('search', ''));

        $users = User::with(['kantineGroups', 'kantineAllergens', 'kantineDiets'])
            ->when($search !== '', fn ($q) => $q->where(function ($sub) use ($search) {
                $sub->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->orderBy('name')
            ->get();

        return view('schulkantine::eaters.index', [
            'users' => $users,
            'activeSeason' => Season::where('is_active', true)->first(),
            'search' => $search,
        ]);
    }

    public function edit(Request $request, User $user)
    {
        $this->authorizeAdmin($request);

        $user->load(['kantineGroups', 'kantineAllergens', 'kantineDiets']);

        return view('schulkantine::eaters.form', $this->formData($user));
    }

    public function update(Request $request, User $user)
    {
        $this->authorizeAdmin($request);

        $request->validate([
            'group_id' => ['nullable', 'exists:kantine_customer_groups,id'],
            'allergens' => ['array'],
            'allergens.*' => ['integer', 'exists:kantine_allergens,id'],
            'diets' => ['array'],
            'diets.*' => ['integer', 'exists:kantine_diets,id'],
        ]);

        $user->kantineAllergens()->sync($request->input('allergens', []));
        $user->kantineDiets()->sync($request->input('diets', []));
        $this->setGroupForActiveSeason($user, $request);

        return redirect()
            ->route('module.schulkantine.eaters.index')
            ->with('status', 'Teilnehmer „'.$user->name.'" wurde gespeichert.');
    }

    // ---------------------------------------------------------------- Helfer

    /** @return array<string, mixed> */
    private function formData(User $user): array
    {
        $activeSeason = Season::where('is_active', true)->first();

        $currentGroupId = $activeSeason
            ? optional($user->kantineGroups->firstWhere('pivot.season_id', $activeSeason->id))->id
            : null;

        return [
            'user' => $user,
            'groups' => CustomerGroup::where('is_active', true)->orderBy('name')->get(),
            'allergens' => Allergen::orderBy('code')->get(),
            'diets' => Diet::orderBy('name')->get(),
            'activeSeason' => $activeSeason,
            'currentGroupId' => $currentGroupId,
            'selAllergens' => $user->kantineAllergens->pluck('id')->all(),
            'selDiets' => $user->kantineDiets->pluck('id')->all(),
        ];
    }

    /** Setzt die Kundengruppe des Benutzers für die aktuell aktive Saison. */
    private function setGroupForActiveSeason(User $user, Request $request): void
    {
        $activeSeason = Season::where('is_active', true)->first();

        if (! $activeSeason) {
            return;
        }

        $groupId = $request->input('group_id') ?: null;

        if ($groupId) {
            DB::table('kantine_user_season_group')->updateOrInsert(
                ['user_id' => $user->id, 'season_id' => $activeSeason->id],
                ['customer_group_id' => $groupId],
            );
        } else {
            DB::table('kantine_user_season_group')
                ->where('user_id', $user->id)
                ->where('season_id', $activeSeason->id)
                ->delete();
        }
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Nur Administratoren dürfen die Kantine verwalten.');
    }
}
