<?php

namespace Intranet\Modules\Schulkantine\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Intranet\Modules\Schulkantine\Models\CustomerGroup;

/**
 * Verwaltung der drei FESTEN Kundengruppen (OGS, Schüler, Sonstige). Angelegt
 * werden sie per Migration; hier lassen sich nur die Betriebsparameter
 * (Bestellmodus, Ausgabe-Zeitfenster) bearbeiten – Name und Rolle sind fest,
 * Anlegen/Löschen ist nicht vorgesehen.
 */
class CustomerGroupController
{
    public function index(Request $request)
    {
        $this->authorizeAdmin($request);

        $groups = CustomerGroup::orderBy('id')->get();

        return view('schulkantine::customer_groups.index', compact('groups'));
    }

    public function edit(Request $request, CustomerGroup $customerGroup)
    {
        $this->authorizeAdmin($request);

        return view('schulkantine::customer_groups.form', [
            'group' => $customerGroup,
            'modes' => CustomerGroup::orderingModes(),
        ]);
    }

    public function update(Request $request, CustomerGroup $customerGroup)
    {
        $this->authorizeAdmin($request);

        $request->validate([
            'ordering_mode' => ['required', Rule::in(array_keys(CustomerGroup::orderingModes()))],
            'pickup_from' => ['nullable', 'date_format:H:i'],
            'pickup_to' => ['nullable', 'date_format:H:i'],
        ]);

        // Nur Betriebsparameter – Name und Rolle bleiben fest.
        $customerGroup->update([
            'ordering_mode' => $request->input('ordering_mode'),
            'pickup_from' => $request->input('pickup_from') ?: null,
            'pickup_to' => $request->input('pickup_to') ?: null,
        ]);

        return redirect()
            ->route('module.schulkantine.customer-groups.index')
            ->with('status', 'Kundengruppe „'.$customerGroup->name.'" wurde gespeichert.');
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Nur Administratoren dürfen die Kantine verwalten.');
    }
}
