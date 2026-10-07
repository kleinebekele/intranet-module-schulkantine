<?php

namespace Intranet\Modules\Schulkantine\Http\Middleware;

use App\Models\Module;
use Closure;
use Illuminate\Http\Request;
use Intranet\Modules\Schulkantine\Support\Bestellterminal;
use Symfony\Component\HttpFoundation\Response;

/**
 * Das Bestell-Terminal läuft ohne Intranet-Anmeldung – darum nur aus den
 * freigegebenen Netzen der Schule (Einstellung unter „Bestell-Terminal").
 * Angemeldete Admins dürfen immer (Einrichten/Testen von außen).
 */
class NurSchulnetz
{
    public function handle(Request $request, Closure $next): Response
    {
        $modulAktiv = Module::where('key', 'schulkantine')->where('is_enabled', true)->exists();
        abort_unless($modulAktiv, 404);

        // Von außen nur ein schlichtes 403 – keine Erklärung, kein Hinweis auf das Terminal.
        abort_unless($request->user()?->isAdmin() || Bestellterminal::erlaubt($request->ip()), 403);

        return $next($request);
    }
}
