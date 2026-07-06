<?php

namespace Intranet\Modules\Schulkantine\Http\Controllers;

/**
 * Landing-Route des Moduls. Die frühere Roadmap-Übersicht ist mit dem
 * Abschluss aller Phasen entfallen – wir leiten direkt auf „Essen bestellen",
 * die zentrale Aktion für Nutzer.
 */
class DashboardController
{
    public function index()
    {
        return redirect()->route('module.schulkantine.orders.index');
    }
}
