<?php

namespace Intranet\Modules\Schulkantine\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Intranet\Modules\Schulkantine\Models\Allergen;
use Intranet\Modules\Schulkantine\Models\CustomerGroup;
use Intranet\Modules\Schulkantine\Models\Diet;
use Intranet\Modules\Schulkantine\Models\NfcChip;
use Intranet\Modules\Schulkantine\Support\InfoImporter;

/**
 * Teilnehmer-Verwaltung. Jeder Teilnehmer IST ein Benutzer (angelegt über den
 * Benutzer-Import). Die Kundengruppe ergibt sich AUS DEN ROLLEN des Benutzers.
 * Hier gepflegt werden Sonderkost und – nur durch die Schule – die Schul-Chips
 * (Ausgabe mit Pfand, Rückgabe).
 */
class EaterController
{
    public function index(Request $request)
    {
        $this->authorizeAdmin($request);

        $search = trim((string) $request->query('search', ''));

        $users = User::with(['roles', 'kantineAllergens', 'kantineDiets', 'kantineInfo'])
            ->when($search !== '', fn ($q) => $q->where(function ($sub) use ($search) {
                $sub->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('kantineInfo', fn ($i) => $i->where('info', 'like', "%{$search}%"));
            }))
            ->orderBy('name')
            ->get();

        return view('schulkantine::eaters.index', [
            'users' => $users,
            'groups' => CustomerGroup::all()->keyBy('role_id'), // einmal laden → kein N+1
            'chips' => NfcChip::active()->whereIn('user_id', $users->pluck('id'))->get()->groupBy('user_id'),
            'search' => $search,
            // Liegt gerade eine CSV bereit? Dann den Import-Button hervorheben.
            'wartendeImporte' => count(app(InfoImporter::class)->wartendeDateien()),
        ]);
    }

    /**
     * Teilnehmer-Infos aus den CSV-Dateien in storage/app/kantinen-import einlesen.
     * Derselbe Weg wie der stündliche Scheduler – nur eben auf Knopfdruck.
     */
    public function importInfos(Request $request, InfoImporter $importer)
    {
        $this->authorizeAdmin($request);

        $ergebnis = $importer->run();

        if ($ergebnis['dateien'] === 0 && $ergebnis['fehler'] === []) {
            return back()->with('status', 'Keine CSV-Datei im Ordner kantinen-import gefunden – nichts zu importieren.');
        }

        $meldung = sprintf(
            '%d Datei(en) eingelesen: %d Info(s) gesetzt, %d geleert.',
            $ergebnis['dateien'],
            $ergebnis['gesetzt'],
            $ergebnis['geleert'],
        );

        if ($ergebnis['fehler'] !== []) {
            return back()
                ->with('status', $meldung)
                ->with('error', implode(' · ', $ergebnis['fehler']));
        }

        return back()->with('status', $meldung);
    }

    public function edit(Request $request, User $user)
    {
        $this->authorizeAdmin($request);

        $user->load(['roles', 'kantineAllergens', 'kantineDiets', 'parents']);

        return view('schulkantine::eaters.form', [
            'user' => $user,
            'group' => CustomerGroup::forUser($user),
            'allergens' => Allergen::orderBy('code')->get(),
            'diets' => Diet::orderBy('name')->get(),
            'selAllergens' => $user->kantineAllergens->pluck('id')->all(),
            'selDiets' => $user->kantineDiets->pluck('id')->all(),
            'chips' => NfcChip::active()->where('user_id', $user->id)->orderBy('source')->get(),
            'isOgs' => CustomerGroup::forUser($user)?->ordering_mode === CustomerGroup::MODE_JA_NEIN,
        ]);
    }

    public function update(Request $request, User $user)
    {
        $this->authorizeAdmin($request);

        $request->validate([
            'allergens' => ['array'],
            'allergens.*' => ['integer', 'exists:kantine_allergens,id'],
            'diets' => ['array'],
            'diets.*' => ['integer', 'exists:kantine_diets,id'],
        ]);

        $user->kantineAllergens()->sync($request->input('allergens', []));
        $user->kantineDiets()->sync($request->input('diets', []));

        return redirect()
            ->route('module.schulkantine.eaters.index')
            ->with('status', 'Verträglichkeiten von „'.$user->name.'" wurden gespeichert.');
    }

    // ------------------------------------------------------- Schul-Chips

    /** Schul-Chip an einen Esser ausgeben (mit optionalem Pfand). */
    public function issueChip(Request $request, User $user)
    {
        $this->authorizeAdmin($request);
        $this->abortIfOgs($user);

        $data = $request->validate([
            'nfc_uid' => ['required', 'string', 'max:255'],
            'nfc_deposit' => ['nullable', 'in:0,1'],
        ]);

        $uid = NfcChip::normalize($data['nfc_uid']);
        if ($uid === '') {
            return back()->withErrors(['nfc_uid' => 'Die Chip-Kennung ist leer oder ungültig.']);
        }

        $conflict = NfcChip::activeForUid($uid);
        if ($conflict) {
            $who = $conflict->user_id === $user->id ? 'diesem Esser' : ($conflict->user?->name ?? 'einem anderen Esser');

            return back()->withErrors(['nfc_uid' => 'Dieser Chip ist bereits '.$who.' zugeordnet.']);
        }

        NfcChip::create([
            'user_id' => $user->id,
            'uid' => $uid,
            'source' => NfcChip::SOURCE_SCHULE,
            'deposit' => $request->boolean('nfc_deposit') ? NfcChip::SCHULE_DEPOSIT : 0.0,
            'lent_at' => Carbon::today()->toDateString(),
        ]);

        return back()->with('status', 'Schul-Chip an „'.$user->name.'" ausgegeben.');
    }

    /** Schul-Chip zurücknehmen – nur die Schule. Erscheint als Pfand-Rückgabe. */
    public function returnChip(Request $request, NfcChip $chip)
    {
        $this->authorizeAdmin($request);

        abort_unless($chip->isSchool(), 422, 'Nur Schul-Chips können zurückgenommen werden.');
        if ($chip->isReturned()) {
            return back();
        }

        $chip->update(['returned_at' => Carbon::today()->toDateString()]);

        return back()->with('status', 'Schul-Chip von „'.$chip->user?->name.'" zurückgenommen (Pfand-Rückgabe in der Abrechnung dieses Monats).');
    }

    /** Chip endgültig entfernen (Korrektur). */
    public function removeChip(Request $request, NfcChip $chip)
    {
        $this->authorizeAdmin($request);

        $name = $chip->user?->name;
        $chip->delete();

        return back()->with('status', 'Chip von „'.$name.'" entfernt.');
    }

    // ----------------------------------------------------------------- Helfer

    private function abortIfOgs(User $user): void
    {
        abort_if(
            CustomerGroup::forUser($user)?->ordering_mode === CustomerGroup::MODE_JA_NEIN,
            422,
            'OGS-Kinder bekommen keinen Chip.'
        );
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Nur Administratoren dürfen die Kantine verwalten.');
    }
}
