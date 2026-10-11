<?php

namespace Intranet\Modules\Schulkantine\Http\Middleware;

use App\Models\User;
use App\Modules\Support\Modulzugriff;
use App\Support\Audit;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * „Als Person handeln" (Kantinen-Administration): Ist in der Sitzung eine Person
 * gewählt, laufen die Kantinenseiten so, als wäre sie angemeldet – Bestellen,
 * Abbestellen, Meine Daten … sehen und tun genau das, was sie sähe und täte.
 *
 * - Nur auf den Routen dieses Moduls (die Modul-Zugriffsprüfung des Cores lief
 *   davor noch mit dem echten Konto). Außerhalb bleibt man man selbst.
 * - Wer es nicht (mehr) darf, verliert die Auswahl stillschweigend.
 * - Jede Änderung (alles außer GET) landet im Audit-Log: Akteur = echtes Konto,
 *   Betroffener = die Person. Die Erfolgsmeldung nennt die Person.
 */
class AlsPerson
{
    public const SITZUNG = 'kantine.als_person';

    /** Darf hier nie umgeschaltet werden: der Ausstieg selbst. */
    private const OHNE = ['module.schulkantine.als.ende', 'module.schulkantine.eaters.als'];

    public function __construct(private Modulzugriff $zugriff) {}

    public function handle(Request $request, Closure $next): Response
    {
        $echt = $request->user();
        $id = $request->session()->get(self::SITZUNG);
        if (! $echt || ! $id || in_array($request->route()?->getName(), self::OHNE, true)) {
            return $next($request);
        }

        $ziel = User::find($id);
        if (! $ziel || ! self::darf($echt)) {
            $request->session()->forget(self::SITZUNG);

            return $next($request);
        }

        Auth::setUser($ziel);
        $request->setUserResolver(fn () => $ziel);
        $request->attributes->set('kantine_echter_user', $echt);

        try {
            $antwort = $next($request);
        } finally {
            Auth::setUser($echt);
            $request->setUserResolver(fn () => $echt);
        }

        if (! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true) && $antwort->getStatusCode() < 400) {
            $route = (string) $request->route()?->getName();
            Audit::schreiben(
                'kantine.als_person_aenderung',
                "Als {$ziel->name}: ".str_replace('module.schulkantine.', '', $route),
                betroffener: $ziel,
                daten: [
                    'route' => $route,
                    'parameter' => collect($request->route()?->parameters() ?? [])->map(fn ($p) => is_object($p) ? ($p->getKey() ?? (string) $p) : $p)->all(),
                    'eingaben' => $request->except(['_token', '_method', 'password', 'password_confirmation']),
                ],
                akteur: $echt,
            );
            if ($request->session()->has('status')) {
                $request->session()->flash('status', "Für {$ziel->name}: ".$request->session()->get('status'));
            }
        }

        return $antwort;
    }

    /** Darf $user als andere Person handeln? Wer Teilnehmer verwalten darf (Admins immer). */
    public static function darf(User $user): bool
    {
        return app(Modulzugriff::class)->darfRoute('module.schulkantine.eaters.als', $user);
    }

    /** Die gerade gewählte Person (für Banner), sonst null. */
    public static function person(): ?User
    {
        $id = session(self::SITZUNG);

        return $id ? User::find($id) : null;
    }
}
