<?php

namespace Intranet\Modules\Schulkantine\Http\Controllers;

use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\Request;
use Intranet\Modules\Schulkantine\Http\Middleware\AlsPerson;

/** „Als Person handeln" starten und beenden – siehe {@see AlsPerson}. */
class AlsPersonController
{
    public function start(Request $request, User $user)
    {
        abort_unless(AlsPerson::darf($request->user()), 403, 'Nur die Kantinen-Verwaltung darf als andere Person handeln.');
        abort_if($user->is($request->user()), 422, 'Das bist du selbst.');
        abort_unless(AlsPerson::zielErlaubt($user), 403, 'Als Administratoren oder Kantinen-Personal (Verwaltungsrechte) kann man nicht handeln.');

        $request->session()->put(AlsPerson::SITZUNG, $user->id);
        Audit::schreiben('kantine.als_person_start', "Handelt in der Kantine als {$user->name}", betroffener: $user);

        return redirect()->route('module.schulkantine.orders.index')
            ->with('status', "Du handelst jetzt als {$user->name}. Alles, was du in der Kantine einstellst, gilt für diese Person.");
    }

    public function ende(Request $request)
    {
        $person = AlsPerson::person();
        $request->session()->forget(AlsPerson::SITZUNG);
        if ($person) {
            Audit::schreiben('kantine.als_person_ende', "Handelt nicht mehr als {$person->name}", betroffener: $person);
        }

        return redirect()->route('module.schulkantine.eaters.index')
            ->with('status', $person ? "Du bist wieder du selbst (nicht mehr {$person->name})." : 'Du bist wieder du selbst.');
    }
}
