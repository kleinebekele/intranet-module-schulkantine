<?php

namespace Intranet\Modules\Schulkantine\Http\Controllers;

/**
 * Start-/Übersichtsseite des Moduls. Aktuell nur ein Willkommens-Dashboard,
 * das den Bau-Fahrplan zeigt – die einzelnen Bereiche kommen Phase für Phase.
 */
class DashboardController
{
    public function index()
    {
        return view('schulkantine::index');
    }
}
