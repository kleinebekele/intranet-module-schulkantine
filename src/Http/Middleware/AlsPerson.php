<?php

namespace Intranet\Modules\Schulkantine\Http\Middleware;

use App\Models\Module;
use App\Models\User;
use App\Modules\Support\ModuleRegistry;
use App\Modules\Support\Modulzugriff;
use App\Support\Audit;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Intranet\Modules\Schulkantine\Models\CustomerGroup;
use Symfony\Component\HttpFoundation\Response;

/**
 * „Als Person handeln" (Mensa-Administration): Ist in der Sitzung eine Person
 * gewählt, laufen die Mensa-Seiten so, als wäre sie angemeldet – Bestellen,
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

    /** Request-Attribut mit dem echten Konto (für Verwaltungsrechte, siehe Access::verwalter()). */
    public const ECHTER_USER = 'kantine_echter_user';

    /**
     * Menüpunkte zur Selbstbedienung. Rechte auf jedem ANDEREN Menüpunkt gelten
     * als Mensa-Verwaltung – auch auf künftigen, solange sie hier fehlen.
     */
    private const SELBSTBEDIENUNG = ['orders', 'abrechnung', 'sonderkost', 'ratings', 'reports'];

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
        // Bei jeder Anfrage neu prüfen: Rechte bzw. Rollen können sich seit dem Start geändert haben.
        if (! $ziel || ! self::darf($echt) || ! self::zielErlaubt($ziel)) {
            $request->session()->forget(self::SITZUNG);

            return $next($request);
        }

        Auth::setUser($ziel);
        $request->setUserResolver(fn () => $ziel);
        $request->attributes->set(self::ECHTER_USER, $echt);

        try {
            $antwort = $next($request);
        } finally {
            Auth::setUser($echt);
            $request->setUserResolver(fn () => $echt);
            $request->attributes->remove(self::ECHTER_USER);
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

    /**
     * Darf man als diese Person handeln? Nur als „normale" Teilnehmer: nicht als
     * Admin und nicht als jemand mit Mensa-Verwaltungsrechten – sonst ließe
     * sich über die Person mehr tun als mit dem eigenen Konto. Verwaltung heißt:
     * eine Rolle aus dem Modul-Manifest, die keine Kundengruppe ist (Koch,
     * Kellner, OGS-Betreuer …), oder irgendeine Stufe auf einem Menüpunkt
     * außerhalb der Selbstbedienung (Rollen am Menüpunkt, Verwaltung → Module).
     */
    public static function zielErlaubt(User $ziel): bool
    {
        if ($ziel->isAdmin()) {
            return false;
        }

        // Über die (in Listen vorab geladene) roles-Beziehung statt je Ziel eine
        // Abfrage – die Teilnehmerliste prüft das für jede Zeile.
        [$rollen, $menuepunkte] = self::verwaltung();
        if ($ziel->roles->pluck('role_id')->intersect($rollen)->isNotEmpty()) {
            return false;
        }

        return ! $menuepunkte->contains(fn ($item) => $item->stufeFuer($ziel) !== null);
    }

    /**
     * Verwaltungsrollen (Manifest ohne Kundengruppen) und Verwaltungs-Menüpunkte
     * samt Rollen – je Anfrage einmal geladen (im Request abgelegt).
     *
     * @return array{0: \Illuminate\Support\Collection<int, string>, 1: \Illuminate\Support\Collection<int, \App\Models\ModuleMenuItem>}
     */
    private static function verwaltung(): array
    {
        $request = request();
        if ($geladen = $request->attributes->get('kantine.als_verwaltung')) {
            return $geladen;
        }

        $rollen = collect(app(ModuleRegistry::class)->manifest('schulkantine')?->rollen ?? [])
            ->map(fn ($r) => $r->roleId)
            ->diff(CustomerGroup::ROLE_PRIORITY)
            ->values();
        $menuepunkte = (Module::query()->with('menuItems.roles')->where('key', 'schulkantine')->first()?->menuItems ?? collect())
            ->reject(fn ($item) => in_array($item->key, self::SELBSTBEDIENUNG, true))
            ->values();

        $request->attributes->set('kantine.als_verwaltung', $geladen = [$rollen, $menuepunkte]);

        return $geladen;
    }

    /** Die gerade gewählte Person (für Banner), sonst null. */
    public static function person(): ?User
    {
        $id = session(self::SITZUNG);

        return $id ? User::find($id) : null;
    }
}
