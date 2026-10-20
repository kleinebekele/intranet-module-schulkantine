<?php

namespace Intranet\Modules\Schulkantine\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Intranet\Modules\Schulkantine\Models\Allergen;
use Intranet\Modules\Schulkantine\Models\Budget;
use Intranet\Modules\Schulkantine\Models\Category;
use Intranet\Modules\Schulkantine\Models\ChildCategoryPermission;
use Intranet\Modules\Schulkantine\Models\CustomerGroup;
use Intranet\Modules\Schulkantine\Models\Diet;
use Intranet\Modules\Schulkantine\Models\NfcChip;

/**
 * „Meine Daten": JEDER eingeloggte Nutzer pflegt für seinen Haushalt (sich selbst
 * + seine Kinder) Sonderkost und NFC-Chips. Für die KINDER legen die Eltern
 * zusätzlich das Wochenbudget für Spontankäufe und die Kategorie-Freigaben
 * (Vorbestellung / Spontankauf) fest.
 */
class SonderkostController
{
    public function index(Request $request)
    {
        abort_unless(\Intranet\Modules\Schulkantine\Models\Season::datenSelbstForActive(), 403,
            'Die Daten pflegt in dieser Saison die Verwaltung.');
        $user = $request->user();

        // Haushalt: KINDER ZUERST, der Nutzer selbst zuletzt.
        $user->loadMissing(['kantineAllergens', 'kantineDiets', 'roles']);
        $children = $user->children()
            ->with(['kantineAllergens', 'kantineDiets', 'roles'])
            ->orderBy('name')->get();
        $members = $children->concat([$user])->unique('id')->values();

        $groups = CustomerGroup::all()->keyBy('role_id');
        $chips = NfcChip::active()->whereIn('user_id', $members->pluck('id'))->get()->groupBy('user_id');

        $household = $members->map(function (User $m) use ($groups, $chips, $user) {
            $group = CustomerGroup::forUser($m, $groups);
            $isOgs = $group?->ordering_mode === CustomerGroup::MODE_JA_NEIN;
            $mine = $chips->get($m->id, collect());

            // Budget & Freigaben legen Eltern für ihre (Nicht-OGS-)Kinder fest.
            $canLimits = ! $isOgs && $m->id !== $user->id;

            return [
                'user' => $m,
                'group' => $group,
                'isOgs' => $isOgs,
                'canLimits' => $canLimits,
                'weeklyBudget' => $canLimits ? Budget::weeklyAmount($m->id) : null,
                'budgetAmTerminal' => $canLimits && Budget::amAusgabeterminal($m->id),
                'perms' => $canLimits ? ChildCategoryPermission::mapFor($m->id) : collect(),
                'selAllergens' => $m->kantineAllergens->pluck('id')->all(),
                'selDiets' => $m->kantineDiets->pluck('id')->all(),
                'ownChips' => $mine->where('source', NfcChip::SOURCE_ELTERN)->values(),
                'schoolChips' => $mine->where('source', NfcChip::SOURCE_SCHULE)->values(),
                // Kinder mit eigenem Konto sehen ihre Chips nur – verwalten tun sie die Eltern.
                'canChips' => ChipController::mayManage($user, $m),
            ];
        });

        $categories = Category::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();

        return view('schulkantine::sonderkost.index', [
            'household' => $household,
            'allergens' => Allergen::orderBy('code')->get(),
            'diets' => Diet::orderBy('name')->get(),
            'categories' => $categories,
        ]);
    }

    public function update(Request $request, User $user)
    {
        abort_unless(\Intranet\Modules\Schulkantine\Models\Season::datenSelbstForActive(), 403,
            'Die Daten pflegt in dieser Saison die Verwaltung.');
        abort_unless($this->mayEditFor($request->user(), $user), 403,
            'Du darfst die Sonderkost dieser Person nicht bearbeiten.');

        $request->validate([
            'allergens' => ['array'],
            'allergens.*' => ['integer', 'exists:kantine_allergens,id'],
            'diets' => ['array'],
            'diets.*' => ['integer', 'exists:kantine_diets,id'],
        ]);

        $user->kantineAllergens()->sync($request->input('allergens', []));
        $user->kantineDiets()->sync($request->input('diets', []));

        return redirect()
            ->route('module.schulkantine.sonderkost.index')
            ->with('status', 'Verträglichkeiten von „'.$user->name.'" wurden gespeichert.');
    }

    /**
     * Wochenbudget (Spontankäufe) + Kategorie-Freigaben eines Kindes speichern.
     * Nur Eltern für ihre (Nicht-OGS-)Kinder.
     */
    public function saveLimits(Request $request, User $user)
    {
        abort_unless(\Intranet\Modules\Schulkantine\Models\Season::datenSelbstForActive(), 403,
            'Die Daten pflegt in dieser Saison die Verwaltung.');
        $actor = $request->user();
        abort_if($actor->id === $user->id, 403, 'Budget & Freigaben legen die Eltern fest, nicht man selbst.');
        abort_unless($this->mayEditFor($actor, $user), 403, 'Du darfst das für diese Person nicht festlegen.');
        abort_if(
            CustomerGroup::forUser($user)?->ordering_mode === CustomerGroup::MODE_JA_NEIN,
            422,
            'Für OGS-Kinder gibt es kein Budget / keine Freigaben.'
        );

        $data = $request->validate([
            'weekly_budget' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
            'preorder' => ['array'],
            'preorder.*' => ['integer'],
            'walkin' => ['array'],
            'walkin.*' => ['integer'],
        ]);

        // Wochenbudget (allgemein) – nur, wenn es in dieser Saison Budgets gibt (sonst fehlt das
        // Feld im Formular und ein leerer Wert würde ein gespeichertes Budget löschen).
        if (Budget::aktiv()) {
            $general = Budget::where('user_id', $user->id)->whereNull('week_start')->first();
            if (! $request->filled('weekly_budget')) {
                $general?->delete();
            } else {
                $amount = (float) $data['weekly_budget'];
                $general
                    ? $general->update(['amount' => $amount])
                    : Budget::create(['user_id' => $user->id, 'week_start' => null, 'amount' => $amount]);
            }

            Budget::amAusgabeterminalSetzen($user->id, $request->boolean('am_ausgabeterminal'));
        }

        // Kategorie-Freigaben (Zeile nur speichern, wenn eingeschränkt).
        $preorder = collect($request->input('preorder', []))->map(fn ($v) => (int) $v);
        $walkin = collect($request->input('walkin', []))->map(fn ($v) => (int) $v);

        foreach (Category::where('is_active', true)->get() as $cat) {
            $mayPre = $preorder->contains($cat->id);
            $mayWalk = $cat->allows_walkin ? $walkin->contains($cat->id) : true;

            if ($mayPre && $mayWalk) {
                ChildCategoryPermission::where('user_id', $user->id)->where('category_id', $cat->id)->delete();
            } else {
                ChildCategoryPermission::updateOrCreate(
                    ['user_id' => $user->id, 'category_id' => $cat->id],
                    ['may_preorder' => $mayPre, 'may_walkin' => $mayWalk],
                );
            }
        }

        return redirect()
            ->route('module.schulkantine.sonderkost.index')
            ->with('status', 'Budget &amp; Freigaben von „'.$user->name.'" gespeichert.');
    }

    /**
     * Wochenbudget aus „Essen bestellen" (Modal): dauerhaft (allgemein) oder nur für
     * eine Woche. Dauerhaft entfernt auch ein Sonderbudget der gezeigten Woche, damit
     * die neue Zahl dort sofort gilt. Leer = 0 € (dauerhaft – Kinder haben ohne Eintrag
     * kein Budget) bzw. zurück zum dauerhaften Budget (nur diese Woche). Dazu die
     * Einstellung „Budget an der Ausgabe anzeigen".
     */
    public function saveWeekBudget(Request $request, User $user)
    {
        abort_unless(Budget::aktiv(), 403, 'Ein Wochenbudget gibt es in dieser Saison nicht.');
        $actor = $request->user();
        abort_if($actor->id === $user->id, 403, 'Das Budget legen die Eltern fest, nicht man selbst.');
        abort_unless($this->mayEditFor($actor, $user), 403, 'Du darfst das für diese Person nicht festlegen.');
        abort_if(CustomerGroup::forUser($user)?->ordering_mode === CustomerGroup::MODE_JA_NEIN, 422, 'Für OGS-Kinder gibt es kein Budget.');

        // Komma als Dezimaltrenner zulassen („2,50").
        $request->merge(['betrag' => str_replace(',', '.', trim((string) $request->input('betrag', '')))]);
        $data = $request->validate([
            'betrag' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
            'geltung' => ['required', 'in:woche,dauerhaft'],
            'woche' => ['required', 'date'],
        ]);
        $woche = \Illuminate\Support\Carbon::parse($data['woche'])->startOfWeek(\Illuminate\Support\Carbon::MONDAY);
        $betrag = $request->filled('betrag') ? round((float) $data['betrag'], 2) : null;
        $sonder = Budget::where('user_id', $user->id)->whereDate('week_start', $woche->toDateString());
        $kw = 'KW '.$woche->isoWeek();

        if ($data['geltung'] === 'dauerhaft') {
            $allgemein = Budget::where('user_id', $user->id)->whereNull('week_start')->first();
            if ($betrag === null) {
                $allgemein?->delete();
            } else {
                $allgemein
                    ? $allgemein->update(['amount' => $betrag])
                    : Budget::create(['user_id' => $user->id, 'week_start' => null, 'amount' => $betrag]);
            }
            $sonder->delete();
            $text = $betrag === null ? 'Wochenbudget dauerhaft 0 € (keine spontanen Käufe)' : 'Wochenbudget dauerhaft '.number_format($betrag, 2, ',', '.').' €';
        } else {
            if ($betrag === null) {
                $sonder->delete();
                $text = "für {$kw} wieder das dauerhafte Wochenbudget";
            } else {
                (clone $sonder)->exists()
                    ? $sonder->update(['amount' => $betrag])
                    : Budget::create(['user_id' => $user->id, 'week_start' => $woche->toDateString(), 'amount' => $betrag]);
                $text = "Budget nur für {$kw}: ".number_format($betrag, 2, ',', '.').' €';
            }
        }
        if ($request->has('am_ausgabeterminal')) {
            Budget::amAusgabeterminalSetzen($user->id, $request->boolean('am_ausgabeterminal'));
        }

        return back()->with('status', $user->name.': '.$text.'.');
    }

    /**
     * Eine an der Ausgabe erlaubte Budget-Überschreitung bestätigen (Eltern). Erst
     * danach ist an der Ausgabe wieder eine Überschreitung möglich.
     */
    public function ueberschreitungBestaetigen(Request $request, User $user, int $ueberschreitung)
    {
        abort_unless(Budget::aktiv(), 403, 'Ein Wochenbudget gibt es in dieser Saison nicht.');
        $actor = $request->user();
        abort_if($actor->id === $user->id, 403, 'Das bestätigen die Eltern, nicht man selbst.');
        abort_unless($this->mayEditFor($actor, $user), 403, 'Du darfst das für diese Person nicht bestätigen.');

        $zeile = \Illuminate\Support\Facades\DB::table('kantine_budget_ueberschreitungen')
            ->where('id', $ueberschreitung)->where('user_id', $user->id)->first();
        abort_unless($zeile, 404);
        if ($zeile->akzeptiert_at === null) {
            \Illuminate\Support\Facades\DB::table('kantine_budget_ueberschreitungen')->where('id', $zeile->id)
                ->update(['akzeptiert_at' => now(), 'akzeptiert_von' => $actor->id, 'updated_at' => now()]);
            \App\Support\Audit::schreiben('kantine.budget_ueberschreitung_bestaetigt',
                'Budget-Überschreitung '.number_format((float) $zeile->ueber, 2, ',', '.').' € vom '.\Illuminate\Support\Carbon::parse($zeile->tag)->format('d.m.Y').' bestätigt',
                betroffener: $user);
        }

        return back()->with('status', $user->name.': Budget-Überschreitung bestätigt.');
    }

    /** Nur für sich selbst oder ein eigenes Kind. */
    private function mayEditFor(User $actor, User $target): bool
    {
        return $actor->id === $target->id || $actor->children()->whereKey($target->id)->exists();
    }
}
