<?php

namespace Intranet\Modules\Schulkantine\Http\Controllers;

use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Intranet\Modules\Schulkantine\Models\CustomerGroup;

/**
 * Verwaltung der Kundengruppen. Vorerst nur für Administratoren
 * (die feinere Rollen-Steuerung folgt in der Rechte-Phase).
 */
class CustomerGroupController
{
    public function index(Request $request)
    {
        $this->authorizeAdmin($request);

        $groups = CustomerGroup::orderBy('name')->get();

        return view('schulkantine::customer_groups.index', compact('groups'));
    }

    public function create(Request $request)
    {
        $this->authorizeAdmin($request);

        return view('schulkantine::customer_groups.form', [
            'group' => new CustomerGroup(['ordering_mode' => CustomerGroup::MODE_MENUE, 'is_active' => true]),
            'modes' => CustomerGroup::orderingModes(),
            'roles' => Role::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeAdmin($request);

        $group = CustomerGroup::create($this->validated($request));

        return redirect()
            ->route('module.schulkantine.customer-groups.index')
            ->with('status', 'Kundengruppe „'.$group->name.'" wurde angelegt.');
    }

    public function edit(Request $request, CustomerGroup $customerGroup)
    {
        $this->authorizeAdmin($request);

        return view('schulkantine::customer_groups.form', [
            'group' => $customerGroup,
            'modes' => CustomerGroup::orderingModes(),
            'roles' => Role::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, CustomerGroup $customerGroup)
    {
        $this->authorizeAdmin($request);

        $customerGroup->update($this->validated($request, $customerGroup));

        return redirect()
            ->route('module.schulkantine.customer-groups.index')
            ->with('status', 'Kundengruppe wurde gespeichert.');
    }

    public function destroy(Request $request, CustomerGroup $customerGroup)
    {
        $this->authorizeAdmin($request);

        $customerGroup->delete();

        return redirect()
            ->route('module.schulkantine.customer-groups.index')
            ->with('status', 'Kundengruppe wurde gelöscht.');
    }

    // ---------------------------------------------------------------- Helfer

    /** @return array<string, mixed> */
    private function validated(Request $request, ?CustomerGroup $group = null): array
    {
        // Leere Auswahl aus dem Dropdown ('') als „keine Rolle" (null) behandeln,
        // damit die nullable-Regel greift und mehrere Gruppen ohne Rolle erlaubt sind.
        $request->merge(['role_id' => $request->input('role_id') ?: null]);

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'ordering_mode' => ['required', Rule::in(array_keys(CustomerGroup::orderingModes()))],
            'pickup_from' => ['nullable', 'date_format:H:i'],
            'pickup_to' => ['nullable', 'date_format:H:i'],
            'role_id' => [
                'nullable',
                'exists:roles,role_id',
                Rule::unique('kantine_customer_groups', 'role_id')->ignore($group?->id),
            ],
        ], [
            'role_id.unique' => 'Diese Rolle ist bereits einer anderen Kundengruppe zugeordnet.',
        ]);

        return [
            'name' => $request->string('name')->toString(),
            'ordering_mode' => $request->input('ordering_mode'),
            'pickup_from' => $request->input('pickup_from') ?: null,
            'pickup_to' => $request->input('pickup_to') ?: null,
            'is_active' => $request->boolean('is_active'),
            'role_id' => $request->input('role_id') ?: null,
        ];
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Nur Administratoren dürfen die Kantine verwalten.');
    }
}
