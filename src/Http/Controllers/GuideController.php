<?php

namespace Intranet\Modules\Schulkantine\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Response;

/**
 * Admin-Anleitung zum Schulkantine-Modul: Bedienung, Testszenarien, Seeder,
 * Konsolen-Befehle und Test-Benutzer. Online lesbar und als PDF ladbar.
 * Inhalt liegt in resources/views/guide/_content.blade.php (für beide Ausgaben).
 */
class GuideController
{
    /** Online-Ansicht der Anleitung (nur Admin). */
    public function index(): Renderable
    {
        abort_unless((bool) auth()->user()?->isAdmin(), 403);

        return view('schulkantine::guide.index');
    }

    /** Dieselbe Anleitung als PDF zum Herunterladen (nur Admin). */
    public function pdf(): Response
    {
        abort_unless((bool) auth()->user()?->isAdmin(), 403);

        $pdf = Pdf::loadView('schulkantine::guide.pdf')->setPaper('a4');

        return $pdf->download('kantine-anleitung.pdf');
    }
}
