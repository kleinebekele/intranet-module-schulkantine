<?php

namespace Intranet\Modules\Schulkantine\Tasks\Linear;

use App\Ekkon\Tasks\EkkonTask;
use Illuminate\Support\Carbon;
use Intranet\Modules\Schulkantine\Models\Season;
use Intranet\Modules\Schulkantine\Support\LinearExport;

/**
 * Sendet die Kantinenabrechnung eines Monats gesammelt an Linear (`MgEsGeld`) und
 * prüft danach laufend gegen, ob Linear die Zeilen abgerechnet hat.
 *
 * Senden: am letzten Kantinentag des Monats, sobald Abbestellschluss und Sendezeit
 * vorbei sind ({@see LinearExport::sendezeitpunkt()}). Betrachtet werden der laufende
 * und der Vormonat – fällt der Server am Monatsende aus, holt der nächste Lauf nach.
 * Je Esser und Monat höchstens eine Zeile; Nachzügler (Zeilen, die nach dem Versand
 * erst übertragbar wurden) gehen beim nächsten Lauf mit.
 *
 * Gegenprüfen: für die letzten Monate je gesendeter Zeile Forderung in `MgSolln`
 * (abgerechnet, offen/bezahlt – setzt das Bezahlt-Häkchen) oder wenigstens die Zeile
 * in `MgEsGeld`. Fehlt beides oder hat sich die Monatssumme nach dem Versand geändert,
 * gibt es eine Meldung.
 */
class KantineAbrechnung extends EkkonTask
{
    public string $category = 'Linear';

    /** Schreibt in die Linear-Datenbank: bei Ausfall nicht starten, sondern nachholen. */
    public bool $brauchtWawi = true;

    public string $description = 'Kantinenabrechnung am letzten Kantinentag des Monats gesammelt an Linear senden '
        .'und prüfen, ob Linear sie abgerechnet hat.';

    public array $meldungsarten = [
        'kantine-linear-gesendet' => 'Kantine: Abrechnung an Linear gesendet',
        'kantine-linear-abweichung' => 'Kantine: Abweichung zu Linear',
    ];

    public array $einstellungen = [
        'probelauf' => [
            'typ' => 'ja_nein',
            'label' => 'Probelauf (nichts an Linear senden)',
            'standard' => true,
            'hilfe' => 'Berichtet, was gesendet würde, schreibt aber nichts nach Linear. Die Gegenprüfung läuft trotzdem.',
        ],
        'sendezeit' => [
            'typ' => 'text',
            'label' => 'Frühestens um (HH:MM) am letzten Kantinentag',
            'standard' => LinearExport::SENDEZEIT,
            'hilfe' => 'Gesendet wird nach dem Abbestellschluss, aber nicht vor dieser Uhrzeit – damit spontane '
                .'Abholungen und Chip-Pfand des letzten Tages noch in der Abrechnung sind.',
        ],
        'ab_monat' => [
            'typ' => 'text',
            'label' => 'Erst ab Monat (JJJJ-MM)',
            'standard' => '',
            'hilfe' => 'Frühere Monate sendet der Task nie (z. B. solange noch Menü&Serve abrechnet). Leer = keine Grenze.',
        ],
        'pruefen_monate' => [
            'typ' => 'zahl',
            'label' => 'Gegenprüfung: wie viele Monate zurück',
            'standard' => 3,
        ],
    ];

    public function schedule(): string
    {
        return '*/15 * * * *';
    }

    public function run(): array
    {
        $season = Season::where('is_active', true)->first();
        if (! $season) {
            $this->msg('Keine aktive Saison – nichts zu tun.');

            return ['gesendet' => 0];
        }

        $export = new LinearExport;
        $probelauf = (bool) $this->einstellung('probelauf');
        $sendezeit = preg_match('/^\d{1,2}:\d{2}$/', (string) $this->einstellung('sendezeit'))
            ? (string) $this->einstellung('sendezeit') : LinearExport::SENDEZEIT;
        $abMonat = trim((string) $this->einstellung('ab_monat'));

        $ergebnis = ['probelauf' => $probelauf, 'gesendet' => 0, 'summe' => 0.0, 'fehler' => 0, 'abweichungen' => 0];

        // ── Senden: Vormonat und laufender Monat ─────────────────────────────
        foreach ([now()->subMonthNoOverflow(), now()] as $m) {
            [$year, $month] = [(int) $m->year, (int) $m->month];
            $label = sprintf('%02d/%d', $month, $year);
            if ($abMonat !== '' && sprintf('%04d-%02d', $year, $month) < $abMonat) {
                continue;
            }
            $zeitpunkt = $export->sendezeitpunkt($season, $year, $month, $sendezeit);
            if (! $zeitpunkt) {
                continue;
            }
            if (now()->lt($zeitpunkt)) {
                $this->debug['geplant'][$label] = $zeitpunkt->format('d.m.Y H:i');

                continue;
            }

            $offen = array_values(array_filter($export->vorschau($season, $year, $month)['zeilen'], fn ($z) => $z['export'] === null));
            if ($offen === []) {
                continue;
            }
            $summe = round(array_sum(array_column($offen, 'Betrag')), 2);

            if ($probelauf) {
                $this->msg(sprintf('Probelauf %s: würde %d Zeilen mit %s € senden.', $label, count($offen), $this->euro($summe)));
                $this->debug['wuerde_senden'][$label] = array_map(fn ($z) => $z['user']->name.' '.$z['Betrag'], $offen);

                continue;
            }

            $e = $export->sendenAlle($season, $year, $month);
            $ergebnis['gesendet'] += $e['gesendet'];
            $ergebnis['summe'] = round($ergebnis['summe'] + $e['summe'], 2);
            $ergebnis['fehler'] += count($e['fehler']);

            $text = sprintf('%s: %d Zeilen mit %s € an Linear gesendet.', $label, $e['gesendet'], $this->euro($e['summe']))
                .($e['vermerkt'] > 0 ? " {$e['vermerkt']} standen schon in Linear." : '')
                .($e['fehler'] !== [] ? ' Fehler: '.implode(' · ', $e['fehler']) : '');
            $this->msg($text);
            if ($e['gesendet'] > 0 || $e['fehler'] !== []) {
                $this->benachrichtige('kantine-linear-gesendet', "Kantine {$label} an Linear gesendet", $text, $e);
            }
        }

        // ── Gegenprüfen: ist das Gesendete in Linear angekommen/abgerechnet? ──
        $monate = max(1, (int) $this->einstellung('pruefen_monate'));
        for ($i = 0; $i < $monate; $i++) {
            $m = now()->startOfMonth()->subMonthsNoOverflow($i);
            [$year, $month] = [(int) $m->year, (int) $m->month];
            $label = sprintf('%02d/%d', $month, $year);

            $stand = $export->mitZahlungsstand($export->vorschau($season, $year, $month), $season, $year, $month);
            if (! ($stand['linearLesbar'] ?? true)) {
                $this->msg("Gegenprüfung {$label}: Linear nicht lesbar.");

                continue;
            }

            $zaehler = [];
            foreach ($stand['zeilen'] as $z) {
                if (! $z['export']) {
                    continue;
                }
                $zustand = $z['linear']['zustand'] ?? 'unbekannt';
                $zaehler[$zustand] = ($zaehler[$zustand] ?? 0) + 1;

                $abweichung = null;
                if ($zustand === 'fehlt') {
                    $abweichung = 'ist in Linear nicht (mehr) zu finden';
                } elseif (round((float) $z['export']->betrag, 2) !== round((float) $z['Betrag'], 2)) {
                    $abweichung = sprintf('gesendet %s €, Monatssumme jetzt %s €', $this->euro((float) $z['export']->betrag), $this->euro($z['Betrag']));
                }
                if ($abweichung) {
                    $ergebnis['abweichungen']++;
                    $this->benachrichtige(
                        'kantine-linear-abweichung',
                        "Kantine {$label}: Abweichung zu Linear",
                        "{$z['user']->name} (AdrNr {$z['AbwAdrNr']}): {$abweichung}.",
                        ['user_id' => $z['user']->id, 'monat' => $label],
                        "kantine-linear-abweichung-{$z['user']->id}-{$year}-{$month}-".md5($abweichung),
                    );
                }
            }
            if ($zaehler !== []) {
                $this->debug['gegenpruefung'][$label] = $zaehler;
            }
        }

        if ($ergebnis['abweichungen'] > 0) {
            $this->msg("{$ergebnis['abweichungen']} Abweichungen zu Linear gemeldet.");
        }

        return $ergebnis;
    }

    private function euro(float $v): string
    {
        return number_format($v, 2, ',', '.');
    }
}
