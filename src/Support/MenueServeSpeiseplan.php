<?php

namespace Intranet\Modules\Schulkantine\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Intranet\Modules\Schulkantine\Models\Dish;
use Intranet\Modules\Schulkantine\Models\Menu;
use Intranet\Modules\Schulkantine\Models\MenuDay;
use Intranet\Modules\Schulkantine\Models\Order;
use Intranet\Modules\Schulkantine\Models\Season;

/**
 * Trägt die zugeordneten Menü&Serve-Menüs (kantine_menueserve_zuordnungen) in den
 * Speiseplan ein: Am Tag wird das Menü gesucht, das einen Platz für die Kategorie
 * der Hauptspeise und einen für die der Nachspeise hat, und diese Plätze werden
 * belegt. Gibt es den Tag im Speiseplan noch nicht, wird seine Woche ausgerollt
 * (wie der Knopf im Speiseplan). Überschreibt das ganze Schuljahr – auch Vergangenes
 * und freigegebene Wochen; passen mehrere Menüs, entscheidet der Name der
 * Menü&Serve-Menülinie, sonst wird übersprungen.
 * Snacks kommen nicht in ein Menü, sondern als Einzelgericht auf den Tagesplan.
 */
class MenueServeSpeiseplan
{
    /**
     * @param  Collection<int, object>  $zuordnungen  Zeilen aus kantine_menueserve_zuordnungen (+ linie)
     * @param  bool  $andereEntfernen  an jedem vollständig übernommenen Tag alle übrigen Speisen
     *                                 (andere Menüs, Einzelgerichte) samt ihren Bestellungen entfernen
     * @param  Dish|null  $taeglich  dieses Gericht an jedem übernommenen Tag als Snack auf den Tagesplan
     *                               (z. B. „Verschiedenes")
     * @return list<array{datum: string, titel: string, ok: bool, text: string}>
     */
    public function uebernehmen(Season $season, Collection $zuordnungen, bool $andereEntfernen = false, ?Dish $taeglich = null): array
    {
        // Je Tag: was aus Menü&Serve kam (bleibt) und ob etwas schiefging (dann nichts entfernen).
        $behalten = [];
        $release = new ReleaseService;
        $rollout = new MenuRolloutService($release);
        $gerichte = Dish::whereIn('id', $zuordnungen->pluck('hauptspeise_id')->merge($zuordnungen->pluck('nachspeise_id'))->filter())
            ->get()->keyBy('id');
        $ausgerollt = [];
        $ergebnis = [];

        foreach ($zuordnungen as $z) {
            $tag = Carbon::parse($z->datum)->startOfDay();
            $ds = $tag->toDateString();
            $behalten[$ds] ??= ['menuDays' => [], 'menus' => [], 'fehler' => false];
            $zeile = function (bool $ok, string $text) use ($tag, $z, $ds, &$behalten) {
                if (! $ok) {
                    $behalten[$ds]['fehler'] = true;
                }

                return ['datum' => $tag->format('d.m.Y'), 'titel' => $z->titel, 'ok' => $ok, 'text' => $text];
            };
            $haupt = $gerichte->get($z->hauptspeise_id);
            $nach = $gerichte->get($z->nachspeise_id);

            if (! $haupt && ! $nach) {
                $ergebnis[] = $zeile(false, 'keine Gerichte zugeordnet');

                continue;
            }
            // Plätze im Menü gibt es nur je Kategorie – ein Gericht ohne Kategorie passt nirgends hin.
            $ohneKategorie = collect([$haupt, $nach])->filter(fn ($d) => $d && ! $d->category_id);
            if ($ohneKategorie->isNotEmpty()) {
                $ergebnis[] = $zeile(false, $ohneKategorie->map(fn ($d) => '„'.$d->name.'"')->implode(' und ')
                    .($ohneKategorie->count() > 1 ? ' haben' : ' hat').' keine Kategorie – unter Gerichte eintragen, dann erneut übernehmen');

                continue;
            }
            // Der Import darf das ganze Schuljahr überschreiben – auch vergangene Tage
            // und freigegebene Wochen (Menü&Serve ist bis zum Umstieg die Quelle).
            if (! $season->isOpenOn($tag)) {
                $ergebnis[] = $zeile(false, 'kein Mensatag der aktiven Saison');

                continue;
            }

            if (! empty($z->snack)) {
                $neu = $this->aufTagesplan($season, $tag, $haupt);
                $behalten[$ds]['menus'][] = Menu::where('season_id', $season->id)->whereDate('date', $ds)->where('dish_id', $haupt->id)->value('id');
                $ergebnis[] = $zeile(true, 'Snack „'.$haupt->name.'"'.($neu ? ' auf den Tagesplan gesetzt' : ' stand schon auf dem Tagesplan'));

                continue;
            }

            $menues = $this->menuesAm($season, $tag);
            $woche = $release->weekStart($tag)->toDateString();
            if ($menues->isEmpty() && ! isset($ausgerollt[$woche])) {
                // „Heute" = Saisonbeginn, damit auch vergangene Tage ausgerollt werden.
                $rollout->pushWeek($season, $tag, $season->start_date->copy());
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
                    ? ($menues->isEmpty() ? 'kein Menü an diesem Tag (Menü-Vorlagen prüfen)' : 'kein Menü an diesem Tag hat Plätze für '.$this->kategorien($haupt, $nach).' (Menü-Vorlagen prüfen)')
                    : 'mehrere Menüs passen ('.$passend->pluck('name')->implode(', ').') – bitte im Speiseplan von Hand');

                continue;
            }

            $menuDay = $passend->first();
            $behalten[$ds]['menuDays'][] = $menuDay->id;
            foreach ($this->plaetze($menuDay, $haupt, $nach) as $slotId => $dishId) {
                $menuDay->slots->firstWhere('id', $slotId)->update(['dish_id' => $dishId]);
            }
            $ergebnis[] = $zeile(true, '„'.$menuDay->name.'": '.collect([$haupt?->name, $nach?->name])->filter()->implode(' + '));
        }

        if ($taeglich) {
            $neu = 0;
            foreach ($behalten as $ds => $b) {
                if (! $season->isOpenOn(Carbon::parse($ds))) {
                    continue;
                }
                $neu += $this->aufTagesplan($season, Carbon::parse($ds), $taeglich) ? 1 : 0;
                // Bleibt stehen, auch wenn die übrigen Speisen entfernt werden.
                $behalten[$ds]['menus'][] = Menu::where('season_id', $season->id)->whereDate('date', $ds)->where('dish_id', $taeglich->id)->value('id');
            }
            $ergebnis[] = ['datum' => '', 'titel' => $taeglich->name, 'ok' => true,
                'text' => 'an '.count($behalten).' Tagen als Snack auf dem Tagesplan ('.$neu.' neu)'];
        }

        if ($andereEntfernen) {
            foreach ($behalten as $ds => $b) {
                if ($b['fehler'] || ($b['menuDays'] === [] && $b['menus'] === [])) {
                    continue;
                }
                $ergebnis[] = $this->andereEntfernen($season, $ds, $b['menuDays'], array_filter($b['menus']));
            }
        }

        return $ergebnis;
    }

    /**
     * Alle übrigen Speisen eines Tages entfernen: Menüs und Einzelgerichte, die nicht
     * aus Menü&Serve kamen – und ihre Bestellungen (sonst blieben sie ohne Speise stehen).
     */
    private function andereEntfernen(Season $season, string $ds, array $menuDays, array $menus): array
    {
        $wegMd = MenuDay::where('season_id', $season->id)->whereDate('date', $ds)->whereNotIn('id', $menuDays)->get();
        $wegM = Menu::where('season_id', $season->id)->whereDate('date', $ds)->whereNotIn('id', $menus)->get();
        $bestellungen = Order::where('season_id', $season->id)->whereDate('date', $ds)
            ->where(fn ($q) => $q->whereIn('menu_day_id', $wegMd->pluck('id'))->orWhereIn('menu_id', $wegM->pluck('id')))
            ->delete();
        $wegMd->each->delete();
        $wegM->each->delete();

        return ['datum' => Carbon::parse($ds)->format('d.m.Y'), 'titel' => 'Übrige Speisen', 'ok' => true,
            'text' => $wegMd->isEmpty() && $wegM->isEmpty()
                ? 'nichts weiter zu entfernen'
                : $wegMd->count().' Menüs und '.$wegM->count().' Einzelgerichte entfernt'.($bestellungen ? " ({$bestellungen} Bestellzeilen)" : '')];
    }

    /** Einzelgericht auf den Tagesplan – idempotent (wie MenuController::addDishToPlan). */
    private function aufTagesplan(Season $season, Carbon $tag, Dish $dish): bool
    {
        $vorhanden = Menu::where('season_id', $season->id)->whereDate('date', $tag->toDateString())->where('dish_id', $dish->id)->exists();
        if ($vorhanden) {
            return false;
        }
        Menu::create([
            'season_id' => $season->id,
            'date' => $tag->toDateString(),
            'dish_id' => $dish->id,
            'sort_order' => (int) Menu::where('season_id', $season->id)->whereDate('date', $tag->toDateString())->max('sort_order') + 1,
        ]);

        return true;
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
