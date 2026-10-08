<?php

namespace Intranet\Modules\Schulkantine\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Intranet\Modules\Schulkantine\Models\Dish;
use Intranet\Modules\Schulkantine\Models\MenuDay;
use Intranet\Modules\Schulkantine\Models\Season;

/**
 * Trägt die zugeordneten Menü&Serve-Menüs (kantine_menueserve_zuordnungen) in den
 * Speiseplan ein: Am Tag wird das Menü gesucht, das einen Platz für die Kategorie
 * der Hauptspeise und einen für die der Nachspeise hat, und diese Plätze werden
 * belegt. Gibt es den Tag im Speiseplan noch nicht, wird seine Woche ausgerollt
 * (wie der Knopf im Speiseplan). Freigegebene Wochen bleiben unberührt; passen
 * mehrere Menüs, entscheidet der Name der Menü&Serve-Menülinie, sonst wird übersprungen.
 */
class MenueServeSpeiseplan
{
    /**
     * @param  Collection<int, object>  $zuordnungen  Zeilen aus kantine_menueserve_zuordnungen (+ linie)
     * @return list<array{datum: string, titel: string, ok: bool, text: string}>
     */
    public function uebernehmen(Season $season, Collection $zuordnungen): array
    {
        $release = new ReleaseService;
        $rollout = new MenuRolloutService($release);
        $gerichte = Dish::whereIn('id', $zuordnungen->pluck('hauptspeise_id')->merge($zuordnungen->pluck('nachspeise_id'))->filter())
            ->get()->keyBy('id');
        $ausgerollt = [];
        $ergebnis = [];

        foreach ($zuordnungen as $z) {
            $tag = Carbon::parse($z->datum)->startOfDay();
            $zeile = fn (bool $ok, string $text) => ['datum' => $tag->format('d.m.Y'), 'titel' => $z->titel, 'ok' => $ok, 'text' => $text];
            $haupt = $gerichte->get($z->hauptspeise_id);
            $nach = $gerichte->get($z->nachspeise_id);

            if (! $haupt && ! $nach) {
                $ergebnis[] = $zeile(false, 'keine Gerichte zugeordnet');

                continue;
            }
            if ($tag->lt(Carbon::today()) || ! $season->isOpenOn($tag)) {
                $ergebnis[] = $zeile(false, 'kein Kantinentag der aktiven Saison (oder vorbei)');

                continue;
            }
            if ($release->isWeekReleased($season, $tag)) {
                $ergebnis[] = $zeile(false, 'Woche ist freigegeben – im Speiseplan erst „Zur Bearbeitung freigeben"');

                continue;
            }

            $menues = $this->menuesAm($season, $tag);
            $woche = $release->weekStart($tag)->toDateString();
            if ($menues->isEmpty() && ! isset($ausgerollt[$woche])) {
                $rollout->pushWeek($season, $tag);
                $ausgerollt[$woche] = true;
                $menues = $this->menuesAm($season, $tag);
            }

            // Menüs mit einem Platz je benötigter Kategorie.
            $passend = $menues->filter(fn (MenuDay $md) => $this->plaetze($md, $haupt, $nach) !== null)->values();
            if ($passend->count() > 1) {
                $gleich = $passend->filter(fn (MenuDay $md) => mb_strtolower(trim($md->name)) === mb_strtolower(trim((string) ($z->linie ?? ''))));
                $passend = $gleich->count() === 1 ? $gleich->values() : $passend;
            }
            if ($passend->count() !== 1) {
                $ergebnis[] = $zeile(false, $passend->isEmpty()
                    ? ($menues->isEmpty() ? 'kein Menü an diesem Tag (Menü-Vorlagen prüfen)' : 'kein Menü mit Plätzen für '.$this->kategorien($haupt, $nach))
                    : 'mehrere Menüs passen ('.$passend->pluck('name')->implode(', ').') – bitte im Speiseplan von Hand');

                continue;
            }

            $menuDay = $passend->first();
            foreach ($this->plaetze($menuDay, $haupt, $nach) as $slotId => $dishId) {
                $menuDay->slots->firstWhere('id', $slotId)->update(['dish_id' => $dishId]);
            }
            $ergebnis[] = $zeile(true, '„'.$menuDay->name.'": '.collect([$haupt?->name, $nach?->name])->filter()->implode(' + '));
        }

        return $ergebnis;
    }

    private function menuesAm(Season $season, Carbon $tag): Collection
    {
        return MenuDay::with('slots')->where('season_id', $season->id)->whereDate('date', $tag->toDateString())->get();
    }

    /**
     * Slot-ID → Gericht-ID für Haupt- und Nachspeise, je der erste freie Platz ihrer
     * Kategorie – oder null, wenn das Menü einen benötigten Platz nicht hat.
     *
     * @return array<int, int>|null
     */
    private function plaetze(MenuDay $md, ?Dish $haupt, ?Dish $nach): ?array
    {
        $belegung = [];
        foreach (array_filter([$haupt, $nach]) as $dish) {
            $slot = $md->slots->first(fn ($s) => (int) $s->category_id === (int) $dish->category_id && ! isset($belegung[$s->id]));
            if (! $slot) {
                return null;
            }
            $belegung[$slot->id] = $dish->id;
        }

        return $belegung;
    }

    private function kategorien(?Dish $haupt, ?Dish $nach): string
    {
        return collect([$haupt, $nach])->filter()->map(fn ($d) => $d->category?->name ?? 'ohne Kategorie')->implode(' und ');
    }
}
