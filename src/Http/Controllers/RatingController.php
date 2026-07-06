<?php

namespace Intranet\Modules\Schulkantine\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Intranet\Modules\Schulkantine\Models\MealRating;
use Intranet\Modules\Schulkantine\Models\Serving;
use Intranet\Modules\Schulkantine\Support\Access;

/**
 * Feedback / Bewertung (Phase 6).
 *
 * Zwei Sichten:
 *  - Kinder & Eltern bewerten im eigenen Login-Bereich die Essen, die sie
 *    tatsächlich bekommen haben (Daumen hoch/runter, jederzeit änderbar).
 *  - Das Küchen-/Ausgabepersonal sieht einen NUR AGGREGIERTEN Report je
 *    Gericht (Anzahl 👍/👎) – nie, wer bewertet hat.
 */
class RatingController
{
    /**
     * Bewertbar ist eine Ausgabe nur, wenn das Kind das Essen wirklich
     * bekommen hat: ein konkretes Gericht (dish_id), nicht abgelehnt
     * (declined) und keine Alternative (dann ist das protokollierte Gericht
     * nicht das gegessene).
     */
    private function ratableServings()
    {
        return Serving::query()
            ->whereNotNull('dish_id')
            ->where('declined', false)
            ->where('alternative', false);
    }

    /** Bewertungsseite für den eigenen Haushalt (ich + meine Kinder). */
    public function index(Request $request)
    {
        $viewer = $request->user();

        // Kinder zuerst, der Nutzer selbst zuletzt (wie „Meine Daten").
        $children = $viewer->children()->orderBy('name')->get();
        $members = $children->concat([$viewer])->unique('id')->values();

        // Eine Ausgabe-Zeile existiert erst, wenn das Essen tatsächlich
        // ausgegeben wurde – das ist bereits das richtige Signal (kein
        // zusätzlicher Datumsfilter nötig).
        $servings = $this->ratableServings()
            ->whereIn('user_id', $members->pluck('id'))
            ->with(['dish:id,name', 'rating:id,serving_id,rating'])
            ->orderByDesc('date')
            ->get()
            ->groupBy('user_id');

        $households = $members->map(fn (User $m) => [
            'user' => $m,
            'servings' => $servings->get($m->id, collect()),
        ])->filter(fn ($row) => $row['servings']->isNotEmpty())->values();

        return view('schulkantine::ratings.index', [
            'households' => $households,
        ]);
    }

    /** Bewertung setzen/ändern (jederzeit änderbar). */
    public function rate(Request $request, Serving $serving)
    {
        $this->authorizeRating($request->user(), $serving);

        $validated = $request->validate([
            'rating' => 'required|integer|in:'.MealRating::DOWN.','.MealRating::UP,
        ]);

        MealRating::updateOrCreate(
            ['serving_id' => $serving->id],
            [
                'user_id' => $serving->user_id,
                'dish_id' => $serving->dish_id,
                'date' => $serving->date,
                'rating' => (int) $validated['rating'],
            ],
        );

        return back()->with('status', 'Danke für deine Bewertung!');
    }

    /** Bewertung zurücknehmen. */
    public function destroy(Request $request, Serving $serving)
    {
        $this->authorizeRating($request->user(), $serving);

        MealRating::where('serving_id', $serving->id)->delete();

        return back()->with('status', 'Bewertung entfernt.');
    }

    /**
     * Personal-Report: aggregiert je Gericht, streng anonym.
     * Zugriff wie die Ausgabelisten: Admin, Koch oder Kellner.
     */
    public function report(Request $request)
    {
        abort_unless(Access::canViewServings($request->user()), 403, 'Kein Zugriff auf die Bewertungen.');

        // Monatsfilter (optional). Default: gesamter Zeitraum.
        $months = MealRating::query()
            ->selectRaw("strftime('%Y-%m', date) as ym")
            ->distinct()
            ->orderByDesc('ym')
            ->pluck('ym')
            ->filter()
            ->values();

        $monthValue = $request->query('monat');
        if (! in_array($monthValue, $months->all(), true)) {
            $monthValue = null; // „gesamter Zeitraum"
        }

        $ratings = MealRating::query()
            ->whereNotNull('dish_id')
            ->when($monthValue, function ($q) use ($monthValue) {
                [$y, $m] = explode('-', $monthValue);
                $q->whereYear('date', (int) $y)->whereMonth('date', (int) $m);
            })
            ->with('dish:id,name')
            ->get();

        $dishes = $ratings
            ->groupBy('dish_id')
            ->map(function ($group) {
                $up = $group->where('rating', MealRating::UP)->count();
                $down = $group->where('rating', MealRating::DOWN)->count();
                $total = $up + $down;

                return [
                    'dish' => optional($group->first())->dish,
                    'up' => $up,
                    'down' => $down,
                    'total' => $total,
                    'quote' => $total > 0 ? (int) round($up / $total * 100) : 0,
                ];
            })
            ->filter(fn ($row) => $row['dish'] !== null)
            ->sortByDesc(fn ($row) => [$row['up'], $row['quote']])
            ->values();

        return view('schulkantine::ratings.report', [
            'dishes' => $dishes,
            'months' => $months,
            'monthValue' => $monthValue,
            'totalVotes' => $ratings->count(),
        ]);
    }

    /**
     * Darf der Betrachter diese Ausgabe bewerten? Admin, oder es ist seine
     * eigene / die seines Kindes – UND die Ausgabe ist überhaupt bewertbar.
     */
    private function authorizeRating(?User $viewer, Serving $serving): void
    {
        abort_unless(
            $serving->dish_id !== null && ! $serving->declined && ! $serving->alternative,
            422,
            'Dieses Essen kann nicht bewertet werden.',
        );

        $eater = $serving->user;
        $allowed = $viewer && (
            $viewer->isAdmin()
            || $viewer->id === $eater->id
            || $viewer->children()->whereKey($eater->id)->exists()
        );

        abort_unless($allowed, 403, 'Keine Berechtigung für diese Bewertung.');
    }
}
