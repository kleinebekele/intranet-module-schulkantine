<?php

namespace Intranet\Modules\Schulkantine\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Intranet\Modules\Schulkantine\Models\CustomerGroup;
use App\Models\Setting;
use Intranet\Modules\Schulkantine\Models\NfcChip;
use Intranet\Modules\Schulkantine\Support\Access;
use Intranet\Modules\Schulkantine\Support\Bestellterminal;

/**
 * Bestell-Terminal: Vollbild-Kiosk auf den Schul-Terminals, OHNE Intranet-
 * Anmeldung – erreichbar nur aus den freigegebenen Netzen (NurSchulnetz). Wer
 * bestellt, meldet sich per Chip an. Danach gilt dieselbe Wochenansicht wie unter
 * „Essen bestellen" – für den Chip-Inhaber und seine Kinder. Ohne Chip zeigt das
 * Terminal den Speiseplan der Woche nur zum Ansehen.
 *
 * Die Chip-Anmeldung steht in der Session und verfällt nach ABLAUF Sekunden ohne
 * Aktion (zusätzlich meldet die Seite selbst nach kurzer Untätigkeit ab).
 *
 * Der Menüpunkt „Bestell-Terminal" im Intranet ist die Einrichtungsseite
 * (freigegebene Netze, Link zum Terminal).
 */
class BestellTerminalController
{
    private const SESSION = 'kantine_bestellterminal';

    /** Sekunden ohne Aktion, nach denen die Chip-Anmeldung serverseitig verfällt. */
    public const ABLAUF = 180;

    public function einstellungen(Request $request)
    {
        abort_unless(Access::darfMenuepunkt($request->user(), 'bestellterminal'), 403);

        $netze = Bestellterminal::netze();

        return view('schulkantine::bestellterminal.einstellungen', [
            'netzeText' => (string) Setting::get(Bestellterminal::EINSTELLUNG, ''),
            'netze' => $netze,
            'ip' => $request->ip(),
            'ipErlaubt' => Bestellterminal::erlaubt($request->ip()),
            'terminalUrl' => route('kantine.bestellterminal.index'),
        ]);
    }

    public function einstellungenSpeichern(Request $request)
    {
        abort_unless(Access::darfMenuepunkt($request->user(), 'bestellterminal'), 403);

        $text = (string) $request->input('netze', '');
        $ungueltig = array_filter(Bestellterminal::zerlegen($text), fn ($e) => ! Bestellterminal::gueltig($e));
        if ($ungueltig) {
            return back()->withInput()->withErrors(['netze' => 'Keine gültige Adresse bzw. kein gültiges Netz: '.implode(', ', $ungueltig)]);
        }

        Setting::set(Bestellterminal::EINSTELLUNG, trim($text));

        return back()->with('status', 'Freigegebene Netze gespeichert.');
    }

    public function index(Request $request, OrderController $orders)
    {
        $besteller = $this->besteller($request);

        if (! $besteller) {
            // Ohne Chip: der Speiseplan der Woche zum Ansehen, ohne Bestellungen.
            $daten = $orders->wochenDaten($request, $this->gast());
            if ($daten['season']) {
                $daten['eaters'] = collect([[
                    'user' => $this->gast(),
                    'group' => new CustomerGroup(['ordering_mode' => CustomerGroup::MODE_MENUE]),
                    'mode' => CustomerGroup::MODE_MENUE,
                    'hasContract' => true,
                    'allergenIds' => [],
                    'dietIds' => [],
                    'blockedCats' => [],
                    'aboActive' => false,
                    'aboWeekdays' => [],
                ]]);
            }

            return view('schulkantine::bestellterminal.index', $daten + [
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

        return redirect()->route('kantine.bestellterminal.index');
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
            abort(redirect()->route('kantine.bestellterminal.index'));
        }

        return $user;
    }

    /**
     * Platzhalter-Esser für die Ansicht ohne Chip: kein Konto, keine Kinder, kein
     * Vertrag (Preise = Hauptpreis), keine Sonderkost.
     */
    private function gast(): User
    {
        $gast = new User(['name' => 'Speiseplan']);
        $gast->externe_id = 'gast'; // kein Testvertrag
        $gast->setRelation('roles', collect());
        $gast->setRelation('kantineAllergens', collect());
        $gast->setRelation('kantineDiets', collect());

        return $gast;
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
