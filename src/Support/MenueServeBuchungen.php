<?php

namespace Intranet\Modules\Schulkantine\Support;

use App\Ekkon\Ekkon;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Intranet\Modules\Schulkantine\Models\CustomerGroup;
use Intranet\Modules\Schulkantine\Models\Dish;
use Intranet\Modules\Schulkantine\Models\Menu;
use Intranet\Modules\Schulkantine\Models\MenuDay;
use Intranet\Modules\Schulkantine\Models\Order;
use Intranet\Modules\Schulkantine\Models\Season;

/**
 * Übernimmt die in Menü&Serve schon gebuchten Essen als Bestellungen.
 *
 * Buchung = `BOOBAS` (Konto `BOOBAS_FK_ACCBAS` × Menü `BOOBAS_FK_MNUBAS`); das Konto
 * trägt in `ACCBAS_DT_CODE` die Linear-Nummer = `users.externe_id`. Über die
 * Menü-Zuordnung (kantine_menueserve_zuordnungen) wird das Speiseplan-Menü des Tages
 * gesucht, das genau diese Gerichte enthält (Snacks: das Einzelgericht auf dem
 * Tagesplan), und dafür bestellt – wie „Menü bestellen", nur ohne Fristen.
 *
 * Nicht übernommen: stornierte/gesperrte Buchungen (Menge 0, Status L), Personen
 * ohne Konto bei uns, OGS-Kinder (laufen über das Abo), Tage ohne passendes Menü und
 * alles, was schon bestellt oder schon einmal übernommen ist.
 */
class MenueServeBuchungen
{
    public const STATUS = [
        'bereit' => 'wird übernommen',
        'uebernommen' => 'schon übernommen',
        'vorhanden' => 'schon bei uns bestellt',
        'storniert' => 'in Menü&Serve storniert/gesperrt',
        'person' => 'Person bei uns unbekannt',
        'ogs' => 'OGS – läuft über das Abo',
        'zuordnung' => 'Menü nicht zugeordnet',
        'speiseplan' => 'kein passendes Menü im Speiseplan',
    ];

    /**
     * Alle Buchungen ab $ab mit ihrem Status.
     *
     * @return list<array>
     */
    public function plan(Season $season, Carbon $ab): array
    {
        $buchungen = $this->lesen($ab);
        $zuordnungen = DB::table('kantine_menueserve_zuordnungen')
            ->whereIn('ms_id', array_unique(array_column($buchungen, 'ms_id')))->get()->keyBy('ms_id');
        $schon = DB::table('kantine_menueserve_buchungen')
            ->whereIn('boobas_id', array_column($buchungen, 'id'))->pluck('boobas_id')->flip();
        $personen = User::whereIn('externe_id', array_unique(array_column($buchungen, 'adrnr')))->get()->keyBy('externe_id');
        $gruppen = CustomerGroup::all()->keyBy('role_id');
        $ziele = [];

        foreach ($buchungen as &$b) {
            $user = $personen->get($b['adrnr']);
            $b['user'] = $user;
            $b['ziel'] = null;
            $z = $zuordnungen->get($b['ms_id']);
            $b['titel'] = $z->titel ?? null;

            if ($b['menge'] <= 0 || $b['recsts'] === 'L') {
                $b['status'] = 'storniert';
            } elseif (isset($schon[$b['id']])) {
                $b['status'] = 'uebernommen';
            } elseif (! $user) {
                $b['status'] = 'person';
            } elseif (CustomerGroup::forUser($user, $gruppen)?->ordering_mode === CustomerGroup::MODE_JA_NEIN) {
                $b['status'] = 'ogs';
            } elseif (! $z || (! $z->hauptspeise_id && ! $z->nachspeise_id)) {
                $b['status'] = 'zuordnung';
            } else {
                $b['ziel'] = $ziele[$b['ms_id']] ??= $this->ziel($season, $b['tag'], $z, $b['snack']);
                $b['status'] = ! $b['ziel'] ? 'speiseplan' : ($this->schonBestellt($season, $user, $b['tag'], $b['ziel']) ? 'vorhanden' : 'bereit');
            }
        }
        unset($b);

        return $buchungen;
    }

    /**
     * Wer steckt hinter den Konten, die wir keiner Person zuordnen können? Je Konto
     * die Namensfelder aus Menü&Serve (ACCBAS) und – bei Linear-Nummer – Name und
     * Kennzeichen aus Linear (`Adresse`), samt Vermutung, warum es bei uns fehlt.
     *
     * @param  list<array>  $plan
     * @return list<array{konto: string, adrnr: string, name: string, kartenart: ?string, linear: ?string, hinweis: string, buchungen: int, tage: list<string>}>
     */
    public function unbekannte(array $plan): array
    {
        $liste = collect($plan)->where('status', 'person')->groupBy('konto');
        if ($liste->isEmpty()) {
            return [];
        }
        $db = DB::connection(Ekkon::mssqlConnection());
        $mus = (string) config('schulkantine.menueserve_db', 'MenuAndServe');

        // Namensspalten von ACCBAS suchen statt raten (Text-Spalten mit NAME/TITLE/TEXT im Namen).
        $spalten = collect($db->select(
            "SELECT COLUMN_NAME c FROM {$mus}.INFORMATION_SCHEMA.COLUMNS
              WHERE TABLE_NAME = 'ACCBAS' AND DATA_TYPE IN ('nvarchar', 'varchar', 'nchar', 'char')
                AND (COLUMN_NAME LIKE '%NAME%' OR COLUMN_NAME LIKE '%TITLE%' OR COLUMN_NAME LIKE '%TEXT%')
              ORDER BY ORDINAL_POSITION"
        ))->pluck('c')->all();
        // Kartenart (Klartexte aus TABPOS, Tabelle ACCBAS_DT_TYPE) – Gruppenkarten sind z. B. Sammelbuchungen.
        $kartenarten = ['A' => 'Administratorkarte', 'G' => 'Gruppenkarte', 'S' => 'Standardkarte', 'V' => 'Stellvertreterkarte', 'Y' => 'Systemkarte'];
        $namen = [];
        $arten = [];
        try {
            foreach ($liste->keys()->chunk(500) as $teil) {
                $platz = implode(',', array_fill(0, count($teil), '?'));
                foreach ($db->select("SELECT CONVERT(varchar(36), ACCBAS_ID) id, ACCBAS_DT_TYPE t FROM {$mus}.dbo.ACCBAS WHERE ACCBAS_ID IN ({$platz})", $teil->values()->all()) as $r) {
                    $t = strtoupper(trim((string) $r->t));
                    $arten[strtolower($r->id)] = $kartenarten[$t] ?? ($t !== '' ? "Kartenart {$t}" : null);
                }
            }
        } catch (\Throwable $e) {
            report($e); // nur Zusatzinfo – die Liste geht auch ohne
        }
        if ($spalten) {
            $auswahl = implode(', ', array_map(fn ($s) => "[{$s}]", $spalten));
            foreach ($liste->keys()->chunk(500) as $teil) {
                $platz = implode(',', array_fill(0, count($teil), '?'));
                foreach ($db->select("SELECT CONVERT(varchar(36), ACCBAS_ID) id, {$auswahl} FROM {$mus}.dbo.ACCBAS WHERE ACCBAS_ID IN ({$platz})", $teil->values()->all()) as $r) {
                    $r = (array) $r;
                    $namen[strtolower($r['id'])] = collect($spalten)->map(fn ($s) => trim((string) ($r[$s] ?? '')))->filter()->unique()->implode(' · ');
                }
            }
        }

        // Linear-Name für Konten mit Nummer.
        $nummern = $liste->map(fn ($b) => $b->first()['adrnr'])->filter(fn ($n) => ctype_digit($n))->unique()->values();
        $linear = $nummern->isEmpty() ? collect() : collect($db->select(
            'SELECT AdrNr, Vorname, Nachname, Schuler, Lehrer, Eltern, Mitarbeiter FROM Adresse WHERE AdrNr IN ('
                .implode(',', array_fill(0, $nummern->count(), '?')).')',
            $nummern->map(fn ($n) => (int) $n)->all(),
        ))->keyBy(fn ($a) => (string) (int) $a->AdrNr);

        return $liste->map(function ($buchungen, $konto) use ($namen, $arten, $linear) {
            $adrnr = (string) $buchungen->first()['adrnr'];
            $a = $adrnr !== '' ? $linear->get((string) (int) $adrnr) : null;
            $kennzeichen = $a ? collect(['Schuler' => 'Schüler', 'Lehrer' => 'Lehrer', 'Eltern' => 'Eltern', 'Mitarbeiter' => 'Mitarbeiter'])
                ->filter(fn ($_, $f) => trim((string) $a->$f) === 'J')->values() : collect();

            return [
                'konto' => $konto,
                'adrnr' => $adrnr,
                'name' => $namen[$konto] ?? '',
                'kartenart' => $arten[$konto] ?? null,
                'linear' => $a ? trim($a->Vorname.' '.$a->Nachname) : null,
                'hinweis' => match (true) {
                    $adrnr === '' => 'Konto in Menü&Serve ohne Linear-Nummer',
                    ! $a => 'Linear-Nummer gibt es in Linear nicht',
                    $kennzeichen->isEmpty() => 'in Linear weder Schüler, Lehrer, Eltern noch Mitarbeiter – wird nicht importiert',
                    default => 'in Linear '.$kennzeichen->implode('/').' – beim nächsten Linear-Import erwartet',
                },
                'buchungen' => $buchungen->count(),
                'tage' => $buchungen->pluck('tag')->unique()->sort()->values()->all(),
            ];
        })->sortBy('name')->values()->all();
    }

    /**
     * Bereite Buchungen ab $ab als Bestellung anlegen – alle oder nur die eines Tages.
     *
     * @return array{angelegt: int, plan: list<array>}
     */
    public function uebernehmen(Season $season, Carbon $ab, ?int $durch, ?string $nurTag = null): array
    {
        $plan = $this->plan($season, $ab);
        $angelegt = 0;

        DB::transaction(function () use ($season, $plan, $durch, $nurTag, &$angelegt) {
            foreach ($plan as $b) {
                if ($b['status'] !== 'bereit' || ($nurTag !== null && $b['tag'] !== $nurTag)) {
                    continue;
                }
                // Zwei Buchungen derselben Person am selben Tag (z. B. doppelt gebucht) nur einmal.
                if ($this->schonBestellt($season, $b['user'], $b['tag'], $b['ziel'])) {
                    continue;
                }
                $this->bestellen($season, $b['user'], $b['tag'], $b['ziel']);
                DB::table('kantine_menueserve_buchungen')->insert([
                    'boobas_id' => $b['id'], 'user_id' => $b['user']->id, 'datum' => $b['tag'],
                    'uebernommen_von' => $durch, 'created_at' => now(), 'updated_at' => now(),
                ]);
                $angelegt++;
            }
        });

        return ['angelegt' => $angelegt, 'plan' => $plan];
    }

    /** @return list<array{id: string, ms_id: string, konto: string, tag: string, adrnr: string, menge: int, recsts: string, snack: bool}> */
    private function lesen(Carbon $ab): array
    {
        if (! Ekkon::mssqlKonfiguriert()) {
            throw new \RuntimeException('Keine Verbindung zum SQL-Server von Linear/Menü&Serve konfiguriert.');
        }
        $mus = (string) config('schulkantine.menueserve_db', 'MenuAndServe');

        return array_map(fn ($r) => [
            'id' => strtolower((string) $r->id),
            'ms_id' => strtolower((string) $r->ms_id),
            'konto' => strtolower((string) $r->konto),
            'tag' => (string) $r->tag,
            'adrnr' => trim((string) $r->adrnr),
            'menge' => (int) $r->menge,
            'recsts' => trim((string) $r->recsts),
            'snack' => (int) $r->typ === MenueServeGerichte::TYP_SNACK,
        ], DB::connection(Ekkon::mssqlConnection())->select(
            "SELECT CONVERT(varchar(36), o.BOOBAS_ID) id, CONVERT(varchar(36), o.BOOBAS_FK_MNUBAS) ms_id,
                    CONVERT(varchar(36), o.BOOBAS_FK_ACCBAS) konto,
                    CONVERT(varchar(10), k.CALBAS_DT_DATE, 23) tag, a.ACCBAS_DT_CODE adrnr,
                    o.BOOBAS_DT_QTY menge, o.BOOBAS_DT_RECSTS recsts, p.MNUPRP_DT_TYPE typ
               FROM {$mus}.dbo.BOOBAS o
               JOIN {$mus}.dbo.ACCBAS a ON a.ACCBAS_ID = o.BOOBAS_FK_ACCBAS
               JOIN {$mus}.dbo.MNUBAS b ON b.MNUBAS_ID = o.BOOBAS_FK_MNUBAS
               JOIN {$mus}.dbo.CALBAS k ON k.CALBAS_ID = b.MNUBAS_FK_CALBAS
               JOIN {$mus}.dbo.MNUPRP p ON p.MNUPRP_ID = b.MNUBAS_FK_MNUPRP
              WHERE k.CALBAS_DT_DATE >= ?
              ORDER BY k.CALBAS_DT_DATE",
            [$ab->format('Y-m-d')],
        ));
    }

    /**
     * Wohin bestellt wird: das vollständige Speiseplan-Menü des Tages mit genau den
     * zugeordneten Gerichten – bzw. beim Snack das Einzelgericht auf dem Tagesplan.
     */
    private function ziel(Season $season, string $tag, object $z, bool $snack): MenuDay|Menu|null
    {
        if ($snack) {
            return Menu::with('dish')->where('season_id', $season->id)->whereDate('date', $tag)
                ->where('dish_id', $z->hauptspeise_id)->first();
        }
        $gewollt = collect([$z->hauptspeise_id, $z->nachspeise_id])->filter()->map(fn ($id) => (int) $id);

        return MenuDay::with('slots.dish')->where('season_id', $season->id)->whereDate('date', $tag)->get()
            ->first(function (MenuDay $md) use ($gewollt) {
                $drin = $md->slots->pluck('dish_id');

                return $md->slots->isNotEmpty() && ! $drin->contains(null)
                    && $gewollt->every(fn ($id) => $drin->contains($id));
            });
    }

    private function schonBestellt(Season $season, User $user, string $tag, MenuDay|Menu $ziel): bool
    {
        $q = Order::where('season_id', $season->id)->where('user_id', $user->id)
            ->whereDate('date', $tag)->where('status', Order::STATUS_ORDERED);

        return $ziel instanceof MenuDay
            ? $q->where('menu_day_id', $ziel->id)->exists()
            : $q->where('dish_id', $ziel->dish_id)->whereNull('menu_day_id')->exists();
    }

    /** Wie OrderController::handleMenuDay bzw. handleMenu – ohne Fristen und Eltern-Sperren. */
    private function bestellen(Season $season, User $user, string $tag, MenuDay|Menu $ziel): void
    {
        $basis = ['season_id' => $season->id, 'user_id' => $user->id, 'date' => $tag, 'status' => Order::STATUS_ORDERED];

        if ($ziel instanceof Menu) {
            Order::create($basis + [
                'menu_id' => $ziel->id,
                'dish_id' => $ziel->dish_id,
                'category_id' => $ziel->dish->category_id,
                'price_snapshot' => $ziel->dish->preisFuer($user),
            ]);

            return;
        }

        $slots = $ziel->slots->values();
        $preise = $this->preisVerteilen(LinearPreise::menuPreis($ziel, $user), $slots->map(fn ($s) => (float) ($s->dish->price ?? 0))->all());
        foreach ($slots as $i => $slot) {
            Order::create($basis + [
                'menu_day_id' => $ziel->id,
                'dish_id' => $slot->dish_id,
                'category_id' => $slot->category_id,
                'price_snapshot' => $preise[$i] ?? 0,
            ]);
        }
    }

    /** Menü-Festpreis anteilig auf die Gerichte verteilen (wie OrderController::distributeMenuPrice). */
    private function preisVerteilen(float $menuPreis, array $einzelpreise): array
    {
        $n = count($einzelpreise);
        if ($n === 0) {
            return [];
        }
        $summe = array_sum($einzelpreise);
        $teile = $summe > 0
            ? array_map(fn ($p) => round($menuPreis * $p / $summe, 2), $einzelpreise)
            : array_fill(0, $n, round($menuPreis / $n, 2));
        $teile[0] = round($teile[0] + ($menuPreis - array_sum($teile)), 2);

        return $teile;
    }
}
