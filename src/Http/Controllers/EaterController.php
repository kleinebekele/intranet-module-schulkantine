<?php

namespace Intranet\Modules\Schulkantine\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Intranet\Modules\Schulkantine\Models\Allergen;
use Intranet\Modules\Schulkantine\Models\CustomerGroup;
use Intranet\Modules\Schulkantine\Models\Diet;
use Intranet\Modules\Schulkantine\Models\Eater;
use Intranet\Modules\Schulkantine\Models\Season;
use Intranet\Modules\Schulkantine\Support\EaterImporter;

/**
 * Verwaltung der Teilnehmer (Esser). Vorerst nur für Administratoren.
 */
class EaterController
{
    public function index(Request $request)
    {
        $this->authorizeAdmin($request);

        $search = trim((string) $request->query('search', ''));
        $statusFilter = (string) $request->query('status', '');

        $eaters = Eater::with(['user', 'guardians', 'allergens', 'diets', 'customerGroups'])
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->when($statusFilter === 'active', fn ($q) => $q->where('is_active', true))
            ->when($statusFilter === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('name')
            ->get();

        return view('schulkantine::eaters.index', [
            'eaters' => $eaters,
            'activeSeason' => Season::where('is_active', true)->first(),
            'search' => $search,
            'statusFilter' => $statusFilter,
        ]);
    }

    public function create(Request $request)
    {
        $this->authorizeAdmin($request);

        return view('schulkantine::eaters.form', $this->formData(new Eater(['is_active' => true])));
    }

    public function importForm(Request $request)
    {
        $this->authorizeAdmin($request);

        return view('schulkantine::eaters.import', [
            'activeSeason' => Season::where('is_active', true)->first(),
            'result' => session('importResult'),
        ]);
    }

    public function import(Request $request, EaterImporter $importer)
    {
        $this->authorizeAdmin($request);

        $request->validate([
            'file' => ['required', 'file', 'max:4096'],
        ]);

        $content = file_get_contents($request->file('file')->getRealPath());
        $result = $importer->import((string) $content);

        return redirect()
            ->route('module.schulkantine.eaters.import.form')
            ->with('importResult', $result)
            ->with('status', "Import abgeschlossen: {$result['created']} neu, {$result['updated']} aktualisiert, {$result['skipped']} übersprungen.");
    }

    public function store(Request $request)
    {
        $this->authorizeAdmin($request);

        $data = $this->validated($request);

        // Falls ein Benutzer gewählt wurde: den bestehenden (auto-angelegten)
        // Teilnehmer dieses Users wiederverwenden statt eine Dublette zu erzeugen.
        $eater = ! empty($data['user_id'])
            ? Eater::firstOrNew(['user_id' => $data['user_id']])
            : new Eater();
        $eater->fill($data)->save();

        $this->syncRelations($eater, $request);
        $this->setGroupForActiveSeason($eater, $request);

        return redirect()
            ->route('module.schulkantine.eaters.index')
            ->with('status', 'Teilnehmer „'.$eater->name.'" wurde angelegt.');
    }

    public function edit(Request $request, Eater $eater)
    {
        $this->authorizeAdmin($request);

        $eater->load('guardians', 'allergens', 'diets', 'customerGroups');

        return view('schulkantine::eaters.form', $this->formData($eater));
    }

    public function update(Request $request, Eater $eater)
    {
        $this->authorizeAdmin($request);

        $eater->update($this->validated($request));
        $this->syncRelations($eater, $request);
        $this->setGroupForActiveSeason($eater, $request);

        return redirect()
            ->route('module.schulkantine.eaters.index')
            ->with('status', 'Teilnehmer wurde gespeichert.');
    }

    public function destroy(Request $request, Eater $eater)
    {
        $this->authorizeAdmin($request);

        $eater->delete();

        return redirect()
            ->route('module.schulkantine.eaters.index')
            ->with('status', 'Teilnehmer wurde gelöscht.');
    }

    // ---------------------------------------------------------------- Helfer

    /** @return array<string, mixed> */
    private function formData(Eater $eater): array
    {
        $activeSeason = Season::where('is_active', true)->first();

        return [
            'eater' => $eater,
            'users' => User::orderBy('name')->get(),
            'groups' => CustomerGroup::where('is_active', true)->orderBy('name')->get(),
            'allergens' => Allergen::orderBy('code')->get(),
            'diets' => Diet::orderBy('name')->get(),
            'activeSeason' => $activeSeason,
            'currentGroupId' => $activeSeason ? optional($eater->groupForSeason($activeSeason->id))->id : null,
        ];
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'user_id' => ['nullable', 'exists:users,id'],
            'group_id' => ['nullable', 'exists:kantine_customer_groups,id'],
            'guardians' => ['array'],
            'guardians.*' => ['integer', 'exists:users,id'],
            'allergens' => ['array'],
            'allergens.*' => ['integer', 'exists:kantine_allergens,id'],
            'diets' => ['array'],
            'diets.*' => ['integer', 'exists:kantine_diets,id'],
        ]);

        return [
            'name' => $request->string('name')->toString(),
            'user_id' => $request->input('user_id') ?: null,
            'is_active' => $request->boolean('is_active'),
        ];
    }

    private function syncRelations(Eater $eater, Request $request): void
    {
        $eater->guardians()->sync($request->input('guardians', []));
        $eater->allergens()->sync($request->input('allergens', []));
        $eater->diets()->sync($request->input('diets', []));
    }

    /** Setzt die Kundengruppe des Essers für die aktuell aktive Saison. */
    private function setGroupForActiveSeason(Eater $eater, Request $request): void
    {
        $activeSeason = Season::where('is_active', true)->first();

        if (! $activeSeason) {
            return;
        }

        $groupId = $request->input('group_id') ?: null;

        if ($groupId) {
            DB::table('kantine_eater_season_group')->updateOrInsert(
                ['eater_id' => $eater->id, 'season_id' => $activeSeason->id],
                ['customer_group_id' => $groupId],
            );
        } else {
            DB::table('kantine_eater_season_group')
                ->where('eater_id', $eater->id)
                ->where('season_id', $activeSeason->id)
                ->delete();
        }
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Nur Administratoren dürfen die Kantine verwalten.');
    }
}
