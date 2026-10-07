<?php

namespace Intranet\Modules\Schulkantine\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Intranet\Modules\Schulkantine\Models\NfcChip;

/**
 * Bestell-Terminal: Vollbild-Kiosk auf den Schul-Terminals. Das Gerät ist mit
 * einem eigenen Intranet-Konto angemeldet (Menüpunkt „Bestell-Terminal"); wer
 * bestellt, meldet sich NUR per Chip an. Danach gilt dieselbe Wochenansicht wie
 * unter „Essen bestellen" – für den Chip-Inhaber und seine Kinder.
 *
 * Die Chip-Anmeldung steht in der Session und verfällt nach ABLAUF Sekunden ohne
 * Aktion (zusätzlich meldet die Seite selbst nach kurzer Untätigkeit ab).
 */
class BestellTerminalController
{
    private const SESSION = 'kantine_bestellterminal';

    /** Sekunden ohne Aktion, nach denen die Chip-Anmeldung serverseitig verfällt. */
    public const ABLAUF = 180;

    public function index(Request $request, OrderController $orders)
    {
        $besteller = $this->besteller($request);

        if (! $besteller) {
            return view('schulkantine::bestellterminal.index', [
                'besteller' => null,
                'simChips' => $this->simChips(),
            ]);
        }

        return view('schulkantine::bestellterminal.index', $orders->wochenDaten($request, $besteller) + [
            'besteller' => $besteller,
            'simChips' => collect(),
        ]);
    }

    /** Chip aufgelegt → Inhaber für diese Session anmelden. Antwort als JSON. */
    public function anmelden(Request $request)
    {
        $data = $request->validate(['uid' => ['required', 'string', 'max:255']]);

        $user = NfcChip::userForLeser($data['uid']);
        if (! $user) {
            $request->session()->forget(self::SESSION);

            return response()->json(['found' => false]);
        }

        $request->session()->put(self::SESSION, ['user' => $user->id, 'zuletzt' => time()]);

        return response()->json(['found' => true, 'name' => $user->name]);
    }

    public function abmelden(Request $request)
    {
        $request->session()->forget(self::SESSION);

        return redirect()->route('module.schulkantine.bestellterminal.index');
    }

    public function bestellen(Request $request, OrderController $orders)
    {
        return $orders->speichern($request, $this->bestellerOderAbbruch($request));
    }

    public function abo(Request $request, OrderController $orders)
    {
        return $orders->aboSpeichern($request, $this->bestellerOderAbbruch($request));
    }

    /** Der per Chip angemeldete Besteller – oder null (nie angemeldet/abgelaufen). */
    private function besteller(Request $request): ?User
    {
        $sitzung = $request->session()->get(self::SESSION);
        if (! $sitzung || time() - ($sitzung['zuletzt'] ?? 0) > self::ABLAUF) {
            $request->session()->forget(self::SESSION);

            return null;
        }

        $user = User::find($sitzung['user']);
        if ($user) {
            $request->session()->put(self::SESSION.'.zuletzt', time());
        }

        return $user;
    }

    private function bestellerOderAbbruch(Request $request): User
    {
        $user = $this->besteller($request);
        if (! $user) {
            // Abgelaufen: zurück zur Chip-Anmeldung (die Seite lädt dann neu).
            abort(redirect()->route('module.schulkantine.bestellterminal.index'));
        }

        return $user;
    }

    /** Nur lokal: Chip-Auswahl zum Testen ohne Leser. Live meldet man sich ausschließlich per Chip an. */
    private function simChips()
    {
        if (! app()->environment('local')) {
            return collect();
        }

        return NfcChip::active()->with('user')->get()
            ->filter(fn ($c) => $c->user !== null)
            ->map(fn ($c) => ['uid' => $c->uid, 'name' => $c->user->name])
            ->sortBy('name')->values();
    }
}
