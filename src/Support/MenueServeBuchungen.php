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
use Intranet\Modules\Schulkantine\Models\Subscription;

/**
 * Übernimmt die in Menü&Serve schon gebuchten Essen als Bestellungen.
 *
 * Buchung = `BOOBAS` (Konto `BOOBAS_FK_ACCBAS` × Menü `BOOBAS_FK_MNUBAS`); das Konto
 * trägt in `ACCBAS_DT_CODE` die Linear-Nummer = `users.externe_id`. Über die
 * Menü-Zuordnung (kantine_menueserve_zuordnungen) wird das Speiseplan-Menü des Tages
 * gesucht, das genau diese Gerichte enthält (Snacks: das Einzelgericht auf dem
 * Tagesplan), und dafür bestellt – wie „Menü bestellen", nur ohne Fristen.
 *
 * Gezählt wird in Bestellungen: ein Menü (mehrere Bestellzeilen, je Gericht eine) oder
 * ein Einzelgericht. Je Tag:
 *  - neu: in Menü&Serve gebucht, bei uns noch nicht bestellt → wird angelegt
 *  - vorhanden: steht schon genauso bei uns → bleibt
 *  - löschbar: steht bei uns, aber nicht (so) in Menü&Serve → wird nur gelöscht, wenn
 *    „übernehmen und löschen" gewählt UND die Person angehakt ist. Personen, die an dem
 *    Tag gar keine Menü&Serve-Buchung haben (nur im Intranet bestellt), sind standardmäßig
 *    nicht angehakt. OGS-Zeilen bleiben immer – die gleicht der OGS-Abgleich an.
 *
 * Nicht übernommen: stornierte/gesperrte Buchungen (Menge 0, Status L), Personen
 * ohne Konto bei uns, OGS-Kinder (laufen über das Abo) und Tage ohne passendes Menü.
 */
class MenueServeBuchungen
{
    /** Ab diesem Anteil gebuchter Tage zählt ein Wochentag zum OGS-Abo-Muster. */
    public const MUSTER_SCHWELLE = 0.6;

    public const STATUS = [
        'bereit' => 'wird übernommen',
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
            } elseif (! $user) {
                $b['status'] = 'person';
            } elseif (CustomerGroup::forUser($user, $gruppen)?->ordering_mode === CustomerGroup::MODE_JA_NEIN) {
                $b['status'] = 'ogs';
            } elseif (! $z || (! $z->hauptspeise_id && ! $z->nachspeise_id)) {
                $b['status'] = 'zuordnung';
            } else {
                $b['ziel'] = $ziele[$b['ms_id']] ??= $this->ziel($season, $b['tag'], $z, $b['snack']);
                $b['status'] = $b['ziel'] ? 'bereit' : 'speiseplan';
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
     * OGS-Abgleich: Für jedes OGS-Kind mit Menü&Serve-Buchungen im Zeitraum wird je
     * Tag verglichen, ob es dort gebucht ist und ob es bei uns isst (Abo + einzelne
     * An-/Abmeldungen, {@see OgsAttendance}). Abweichende Tage → Anmeldung bzw.
     * Abmeldung für den Tag. OGS-Kinder mit aktivem Abo, die in Menü&Serve im ganzen
     * Zeitraum nichts gebucht haben, werden nur aufgelistet (vielleicht ohne Konto dort).
     *
     * @param  list<array>  $plan
     * @return array{kinder: list<array>, tage: list<string>, ohneBuchung: list<array>}
     */
    public function ogsAbgleich(Season $season, array $plan): array
    {
        $tage = collect($plan)->pluck('tag')->unique()->sort()
            ->filter(fn ($t) => $season->isOpenOn(Carbon::parse($t)))->values();
        if ($tage->isEmpty()) {
            return ['kinder' => [], 'tage' => [], 'ohneBuchung' => []];
        }
        $ogs = collect($plan)->where('status', 'ogs');
        $kinder = $ogs->groupBy(fn ($b) => $b['user']->id);
        $subs = Subscription::where('season_id', $season->id)->get()->keyBy('user_id');
        $zeilen = Order::where('season_id', $season->id)->whereNull('category_id')
            ->whereIn('user_id', $kinder->keys())
            ->whereBetween('date', [$tage->first(), $tage->last().' 23:59:59'])->get()
            ->groupBy('user_id');

        $liste = [];
        foreach ($kinder as $userId => $buchungen) {
            $gebucht = $buchungen->pluck('tag')->flip();
            $eigene = $zeilen->get($userId, collect());
            $sub = $subs->get($userId);
            $abweichungen = [];
            foreach ($tage as $t) {
                $wir = OgsAttendance::attends($sub, Carbon::parse($t),
                    $eigene->contains(fn ($o) => $o->date->toDateString() === $t && $o->status === Order::STATUS_ORDERED),
                    $eigene->contains(fn ($o) => $o->date->toDateString() === $t && $o->status === Order::STATUS_CANCELLED));
                $ms = $gebucht->has($t);
                if ($ms !== $wir) {
                    $abweichungen[$t] = $ms ? 'anmelden' : 'abmelden';
                }
            }
            $liste[] = ['user' => $buchungen->first()['user'], 'abo' => $this->aboText($sub), 'gebucht' => $gebucht->count(), 'abweichungen' => $abweichungen];
        }

        // Mit Abo bei uns, aber in Menü&Serve nichts gebucht – nur zur Info.
        $ohne = $subs->filter(fn ($s) => $s->active && ! $kinder->has($s->user_id))->keys();
        $ohneBuchung = User::whereIn('id', $ohne)->orderBy('name')->get()
            ->filter(fn ($u) => CustomerGroup::forUser($u)?->ordering_mode === CustomerGroup::MODE_JA_NEIN)
            ->map(fn ($u) => ['user' => $u, 'abo' => $this->aboText($subs->get($u->id))])->values()->all();

        usort($liste, fn ($a, $b) => [count($b['abweichungen']) === 0, $a['user']->name] <=> [count($a['abweichungen']) === 0, $b['user']->name]);

        return ['kinder' => $liste, 'tage' => $tage->all(), 'ohneBuchung' => $ohneBuchung];
    }

    /**
     * OGS-Abweichungen angleichen (wie OrderController::handleOgs, ohne Fristen) –
     * alle oder nur die eines Tages.
     *
     * @return int Zahl der angeglichenen Kind-Tage
     */
    public function ogsAngleichen(Season $season, array $plan, ?string $nurTag = null): int
    {
        $abgleich = $this->ogsAbgleich($season, $plan);
        $subs = Subscription::where('season_id', $season->id)->get()->keyBy('user_id');
        $n = 0;

        DB::transaction(function () use ($season, $abgleich, $subs, $nurTag, &$n) {
            foreach ($abgleich['kinder'] as $k) {
                foreach ($k['abweichungen'] as $tag => $aktion) {
                    if ($nurTag !== null && $tag !== $nurTag) {
                        continue;
                    }
                    $sub = $subs->get($k['user']->id);
                    $standard = $sub && $sub->active && $sub->eatsWeekday(Carbon::parse($tag)->dayOfWeekIso);
                    $basis = Order::where('season_id', $season->id)->where('user_id', $k['user']->id)
                        ->whereDate('date', $tag)->whereNull('category_id');

                    if ($aktion === 'anmelden') {
                        (clone $basis)->where('status', Order::STATUS_CANCELLED)->delete();
                        if (! $standard && ! (clone $basis)->where('status', Order::STATUS_ORDERED)->exists()) {
                            Order::create(['season_id' => $season->id, 'user_id' => $k['user']->id, 'date' => $tag, 'status' => Order::STATUS_ORDERED]);
                        }
                    } else {
                        (clone $basis)->where('status', Order::STATUS_ORDERED)->delete();
                        if ($standard && ! (clone $basis)->where('status', Order::STATUS_CANCELLED)->exists()) {
                            Order::create(['season_id' => $season->id, 'user_id' => $k['user']->id, 'date' => $tag, 'status' => Order::STATUS_CANCELLED]);
                        }
                    }
                    $n++;
                }
            }
        });

        return $n;
    }

    private function aboText(?Subscription $sub): string
    {
        if (! $sub || ! $sub->active) {
            return 'kein Abo';
        }
        $namen = [1 => 'Mo', 2 => 'Di', 3 => 'Mi', 4 => 'Do', 5 => 'Fr', 6 => 'Sa', 7 => 'So'];

        return empty($sub->weekdays) ? 'Abo alle Tage' : 'Abo '.collect($sub->weekdays)->map(fn ($d) => $namen[$d] ?? $d)->implode(', ');
    }

    /**
     * Je Tag, was eine Übernahme bewirkt – in Bestellungen (Menü bzw. Einzelgericht).
     *
     * @return array<string, array{neu: int, vorhanden: int, loeschbar: list<array{user_id: int, name: string, posten: list<string>, inMs: bool}>}>
     */
    public function vorschau(Season $season, array $plan): array
    {
        $ergebnis = [];
        foreach ($this->tagesStand($season, $plan) as $tag => $stand) {
            $neu = 0;
            $vorhanden = 0;
            foreach ($stand['gewollt'] as $userId => $schluessel) {
                foreach (array_keys($schluessel) as $k) {
                    isset($stand['bestehend'][$userId][$k]) ? $vorhanden++ : $neu++;
                }
            }
            $loeschbar = [];
            foreach ($stand['bestehend'] as $userId => $einheiten) {
                $weg = array_diff_key($einheiten, $stand['gewollt'][$userId] ?? []);
                if ($weg) {
                    $loeschbar[] = [
                        'user_id' => $userId,
                        'name' => $stand['namen'][$userId] ?? ('#'.$userId),
                        'posten' => array_values($weg),
                        'inMs' => isset($stand['gewollt'][$userId]),
                    ];
                }
            }
            usort($loeschbar, fn ($x, $y) => strcmp($x['name'], $y['name']));
            $ergebnis[$tag] = ['neu' => $neu, 'vorhanden' => $vorhanden, 'loeschbar' => $loeschbar];
        }

        return $ergebnis;
    }

    /**
     * Je Tag: was Menü&Serve will (Person → Bestellschlüssel) und was bei uns steht
     * (Person → Schlüssel → Bezeichnung). Schlüssel: "md<MenuDay-ID>" für ein Menü,
     * "g<Gericht-ID>" für ein Einzelgericht.
     *
     * @return array<string, array{gewollt: array<int, array<string, true>>, bestehend: array<int, array<string, string>>, namen: array<int, string>}>
     */
    private function tagesStand(Season $season, array $plan): array
    {
        $plan = array_values(array_filter($plan, fn ($b) => $b['status'] !== 'ogs'));
        $tage = array_values(array_unique(array_column($plan, 'tag')));
        sort($tage);
        $stand = [];
        foreach ($tage as $tag) {
            $stand[$tag] = ['gewollt' => [], 'bestehend' => [], 'namen' => []];
        }
        foreach ($plan as $b) {
            if ($b['status'] === 'bereit') {
                $stand[$b['tag']]['gewollt'][$b['user']->id][$this->schluessel($b['ziel'])] = true;
            }
        }
        if ($tage === []) {
            return [];
        }
        $orders = Order::with(['menuDay', 'dish', 'user'])->where('season_id', $season->id)
            ->whereNotNull('category_id')->where('status', Order::STATUS_ORDERED)
            ->whereBetween('date', [$tage[0], end($tage).' 23:59:59'])->get();
        foreach ($orders as $o) {
            $tag = $o->date->toDateString();
            if (! isset($stand[$tag])) {
                continue;
            }
            $k = $o->menu_day_id ? 'md'.$o->menu_day_id : 'g'.$o->dish_id;
            $stand[$tag]['bestehend'][$o->user_id][$k] = $o->menu_day_id
                ? ($o->menuDay?->name ?? 'Menü')
                : ($o->dish?->name ?? 'Gericht');
            $stand[$tag]['namen'][$o->user_id] = $o->user?->name ?? '';
        }

        return $stand;
    }

    private function schluessel(MenuDay|Menu $ziel): string
    {
        return $ziel instanceof MenuDay ? 'md'.$ziel->id : 'g'.$ziel->dish_id;
    }

    /**
     * Buchungen ab $ab übernehmen – alle Tage oder nur einer. Angelegt wird, was in
     * Menü&Serve gebucht und bei uns noch nicht bestellt ist. Gelöscht wird nur, was
     * $loeschen ausdrücklich nennt (Tag → Personen-IDs) und Menü&Serve so nicht vorsieht.
     *
     * @param  array<string, list<int>>  $loeschen
     * @return array{angelegt: int, geloescht: int}  in Bestellungen (Menü bzw. Einzelgericht)
     */
    public function uebernehmen(Season $season, Carbon $ab, ?int $durch, ?string $nurTag = null, array $loeschen = []): array
    {
        $plan = $this->plan($season, $ab);
        $stand = $this->tagesStand($season, $plan);
        $angelegt = 0;
        $geloescht = 0;
        $tage = collect(array_keys($stand))->filter(fn ($t) => $nurTag === null || $t === $nurTag)->values();

        DB::transaction(function () use ($season, $plan, $stand, $durch, $tage, $loeschen, &$angelegt, &$geloescht) {
            foreach ($tage as $tag) {
                foreach (array_map('intval', $loeschen[$tag] ?? []) as $userId) {
                    $weg = array_keys(array_diff_key($stand[$tag]['bestehend'][$userId] ?? [], $stand[$tag]['gewollt'][$userId] ?? []));
                    foreach ($weg as $k) {
                        $q = Order::where('season_id', $season->id)->where('user_id', $userId)
                            ->whereDate('date', $tag)->whereNotNull('category_id');
                        str_starts_with($k, 'md')
                            ? $q->where('menu_day_id', (int) substr($k, 2))
                            : $q->whereNull('menu_day_id')->where('dish_id', (int) substr($k, 1));
                        if ($q->delete() > 0) {
                            $geloescht++;
                        }
                    }
                }
            }
            foreach ($plan as $b) {
                if ($b['status'] !== 'bereit' || ! $tage->contains($b['tag'])) {
                    continue;
                }
                // Schon bestellt (auch doppelt gebucht) – nichts anlegen.
                if ($this->schonBestellt($season, $b['user'], $b['tag'], $b['ziel'])) {
                    continue;
                }
                $this->bestellen($season, $b['user'], $b['tag'], $b['ziel']);
                DB::table('kantine_menueserve_buchungen')->updateOrInsert(['boobas_id' => $b['id']], [
                    'user_id' => $b['user']->id, 'datum' => $b['tag'],
                    'uebernommen_von' => $durch, 'created_at' => now(), 'updated_at' => now(),
                ]);
                $angelegt++;
            }
        });

        return ['angelegt' => $angelegt, 'geloescht' => $geloescht];
    }

    /** @return list<array{id: string, ms_id: string, konto: string, tag: string, adrnr: string, menge: int, recsts: string, snack: bool}> */
    protected function lesen(Carbon $ab, ?Carbon $bis = null): array
    {
        $bis ??= Carbon::create(2999, 12, 31);
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
              WHERE k.CALBAS_DT_DATE >= ? AND k.CALBAS_DT_DATE <= ?
              ORDER BY k.CALBAS_DT_DATE",
            [$ab->format('Y-m-d'), $bis->format('Y-m-d')],
        ));
    }

    /**
     * Abo-Muster der OGS-Kinder aus der Menü&Serve-Historie: je Kind und Wochentag
     * der Anteil der Mensatage (= Tage mit irgendeiner Buchung), an denen es
     * gebucht war. Vorschlag = Wochentage mit mindestens MUSTER_SCHWELLE;
     * „unklar", wenn ein Wochentag zwischen 25 % und 75 % liegt.
     *
     * @return array{von: Carbon, bis: Carbon, wochentage: list<int>, kinder: list<array>}
     */
    public function ogsMuster(Season $season, Carbon $von, Carbon $bis): array
    {
        $buchungen = collect($this->lesen($von, $bis));
        $tage = $buchungen->pluck('tag')->unique()->filter(fn ($t) => $season->isOpenOn(Carbon::parse($t)));
        $jeWochentag = $tage->countBy(fn ($t) => Carbon::parse($t)->dayOfWeekIso)->sortKeys();
        $wochentage = $jeWochentag->keys()->map(fn ($d) => (int) $d)->values()->all();

        $gueltig = $buchungen->filter(fn ($b) => $b['menge'] > 0 && $b['recsts'] !== 'L' && $tage->contains($b['tag']));
        $personen = User::whereIn('externe_id', $gueltig->pluck('adrnr')->unique()->filter())->get()->keyBy('externe_id');
        $gruppen = CustomerGroup::all()->keyBy('role_id');
        $subs = Subscription::where('season_id', $season->id)->get()->keyBy('user_id');

        $kinder = [];
        foreach ($gueltig->groupBy('adrnr') as $adrnr => $liste) {
            $user = $personen->get($adrnr);
            if (! $user || CustomerGroup::forUser($user, $gruppen)?->ordering_mode !== CustomerGroup::MODE_JA_NEIN) {
                continue;
            }
            $gebucht = $liste->pluck('tag')->unique()->countBy(fn ($t) => Carbon::parse($t)->dayOfWeekIso);
            $anteile = [];
            foreach ($jeWochentag as $wd => $n) {
                $anteile[$wd] = $n > 0 ? round(($gebucht[$wd] ?? 0) / $n, 2) : 0.0;
            }
            $vorschlag = array_keys(array_filter($anteile, fn ($a) => $a >= self::MUSTER_SCHWELLE));
            $sub = $subs->get($user->id);
            $aktuell = $sub && $sub->active ? ($sub->weekdays ?: $wochentage) : [];
            sort($aktuell);

            $kinder[] = [
                'user' => $user,
                'tage' => $liste->pluck('tag')->unique()->count(),
                'anteile' => $anteile,
                'vorschlag' => $vorschlag,
                'unklar' => collect($anteile)->contains(fn ($a) => $a > 0.25 && $a < 0.75),
                'aktuell' => array_values(array_map('intval', $aktuell)),
                'abo' => $this->aboText($sub),
            ];
        }
        usort($kinder, fn ($a, $b) => strcmp($a['user']->name, $b['user']->name));

        return ['von' => $von, 'bis' => $bis, 'wochentage' => $wochentage, 'jeWochentag' => $jeWochentag->all(), 'kinder' => $kinder];
    }

    /**
     * Abos setzen: je Kind die gewählten Wochentage. Alle Mensa-Wochentage = Abo
     * ohne Einschränkung, keiner = Abo aus.
     *
     * @param  array<int, list<int>>  $muster  user_id → Wochentage
     * @param  list<int>  $alle  die Mensa-Wochentage
     */
    public function abosSetzen(Season $season, array $muster, array $alle): int
    {
        $n = 0;
        foreach ($muster as $userId => $tage) {
            $tage = array_values(array_unique(array_map('intval', $tage)));
            sort($tage);
            Subscription::updateOrCreate(
                ['season_id' => $season->id, 'user_id' => (int) $userId],
                ['active' => $tage !== [], 'weekdays' => array_diff($alle, $tage) === [] ? [] : $tage],
            );
            $n++;
        }

        return $n;
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
