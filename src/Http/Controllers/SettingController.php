<?php

namespace Intranet\Modules\Schulkantine\Http\Controllers;

use Illuminate\Http\Request;
use Intranet\Modules\Schulkantine\Models\Setting;

/**
 * Globale Kantinen-Einstellungen (Fristen-Uhrzeiten, Freigabe-Vorlauf).
 * Nur für Administratoren.
 */
class SettingController
{
    public function edit(Request $request)
    {
        $this->authorizeAdmin($request);

        return view('schulkantine::settings.edit', [
            'settings' => Setting::current(),
        ]);
    }

    public function update(Request $request)
    {
        $this->authorizeAdmin($request);

        $data = $request->validate([
            'order_deadline_time' => ['required', 'date_format:H:i'],
            'cancel_deadline_time' => ['required', 'date_format:H:i'],
            'release_lead_weeks' => ['required', 'integer', 'min:0', 'max:52'],
        ]);

        Setting::current()->update($data);

        return redirect()
            ->route('module.schulkantine.settings.edit')
            ->with('status', 'Einstellungen gespeichert.');
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Nur Administratoren dürfen die Kantine verwalten.');
    }
}
