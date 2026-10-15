<?php

namespace Intranet\Modules\Schulkantine\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Intranet\Modules\Schulkantine\Models\CustomerGroup;
use Intranet\Modules\Schulkantine\Models\NfcChip;
use Intranet\Modules\Schulkantine\Support\Access;

/**
 * Self-Service für NFC-Chips: JEDER eingeloggte Nutzer verwaltet die Chips seines
 * Haushalts (sich selbst + seine Kinder). Eltern können BELIEBIG VIELE eigene
 * Chips registrieren (ohne Pfand) und wieder entfernen.
 *
 * Ein von der Schule ausgegebener Chip (mit Pfand) erscheint hier nur LESBAR –
 * zurücknehmen kann ihn ausschließlich die Schule (EaterController).
 *
 * Wer Erziehungsberechtigte hat (Kind mit eigenem Konto), sieht seine Chips nur –
 * anlegen und entfernen dürfen dann nur die Eltern (bzw. die Mensa-Verwaltung),
 * damit kein Kind einen fremden Chip auf die Elternrechnung registriert.
 *
 * OGS-Kinder bekommen bewusst KEINEN Chip.
 */
class ChipController
{
    /** Einen weiteren EIGENEN Chip registrieren (kein Pfand). */
    public function register(Request $request, User $user)
    {
        abort_unless(self::eigeneChips(), 403, 'Eigene Chips sind abgeschaltet – Chips gibt die Schule aus.');
        $actor = $request->user();
        abort_unless(self::mayManage($actor, $user), 403, 'Du darfst für diese Person keinen Chip registrieren. Chips von Kindern verwalten die Eltern.');
        $this->abortIfOgs($user);

        $data = $request->validate([
            'nfc_uid' => ['required', 'string', 'max:255'],
        ]);

        $uid = NfcChip::normalize($data['nfc_uid']);
        if ($uid === '') {
            return back()->withErrors(['nfc_uid' => 'Die Chip-Kennung ist leer oder ungültig.']);
        }

        if ($conflict = NfcChip::activeForUid($uid)) {
            $who = $conflict->user_id === $user->id ? 'dieser Person' : 'jemand anderem';

            return back()->withErrors(['nfc_uid' => 'Dieser Chip ist bereits '.$who.' zugeordnet.']);
        }

        NfcChip::create([
            'user_id' => $user->id,
            'uid' => $uid,
            'source' => NfcChip::SOURCE_ELTERN,
            'deposit' => 0.0,
        ]);

        return back()->with('status', 'Eigener Chip für „'.$user->name.'" registriert.');
    }

    /** Einen EIGENEN Chip entfernen. Schul-Chips kann nur die Schule zurücknehmen. */
    public function remove(Request $request, NfcChip $chip)
    {
        abort_unless(self::eigeneChips(), 403, 'Eigene Chips sind abgeschaltet – Chips gibt die Schule aus.');
        $actor = $request->user();
        abort_unless($chip->user && self::mayManage($actor, $chip->user), 403, 'Du darfst diesen Chip nicht entfernen.');
        abort_if($chip->isSchool(), 403, 'Ein Schul-Chip kann nur von der Schule zurückgenommen werden.');

        $name = $chip->user?->name;
        $chip->delete();

        return back()->with('status', 'Chip von „'.$name.'" entfernt.');
    }

    /**
     * Dürfen Esser/Eltern eigene Chips anlegen? Aus, solange die Schule nur
     * 125-kHz-Chips ausgibt, die privat niemand lesen kann. Für eine Schule mit
     * NFC-Chips (13,56 MHz) per config('schulkantine.eigene_chips') = true wieder einschalten.
     */
    public static function eigeneChips(): bool
    {
        return (bool) config('schulkantine.eigene_chips', false);
    }

    /**
     * Für ein eigenes Kind; für sich selbst nur ohne Erziehungsberechtigte. Die
     * Mensa-Verwaltung (bei „Als Person handeln": das echte Konto) immer.
     */
    public static function mayManage(User $actor, User $target): bool
    {
        if (Access::darfMenuepunkt($actor, 'eaters')) {
            return true;
        }
        if ($actor->id === $target->id) {
            return ! $target->parents()->exists();
        }

        return $actor->children()->whereKey($target->id)->exists();
    }

    private function abortIfOgs(User $user): void
    {
        abort_if(
            CustomerGroup::forUser($user)?->ordering_mode === CustomerGroup::MODE_JA_NEIN,
            422,
            'OGS-Kinder brauchen keinen Chip – die Ausgabe läuft dort über die Liste.'
        );
    }
}
