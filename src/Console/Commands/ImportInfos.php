<?php

namespace Intranet\Modules\Schulkantine\Console\Commands;

use Illuminate\Console\Command;
use Intranet\Modules\Schulkantine\Support\InfoImporter;

/**
 * Liest wartende Teilnehmer-Info-CSVs aus storage/app/kantinen-import ein.
 *
 * Läuft stündlich per Scheduler (siehe SchulkantineServiceProvider) und ist
 * zusätzlich in der Teilnehmerliste als „Jetzt importieren" verdrahtet.
 */
class ImportInfos extends Command
{
    protected $signature = 'kantine:import-infos';

    protected $description = 'Liest Teilnehmer-Infos (z. B. Klasse) aus den CSV-Dateien in storage/app/kantinen-import ein.';

    public function handle(InfoImporter $importer): int
    {
        $ergebnis = $importer->run();

        if ($ergebnis['dateien'] === 0 && $ergebnis['fehler'] === []) {
            $this->line('Keine CSV-Datei in '.$importer->verzeichnis().' – nichts zu tun.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            '%d Datei(en) verarbeitet: %d Info(s) gesetzt, %d geleert, %d unbekannte user_id.',
            $ergebnis['dateien'],
            $ergebnis['gesetzt'],
            $ergebnis['geleert'],
            $ergebnis['unbekannt'],
        ));

        foreach ($ergebnis['fehler'] as $fehler) {
            $this->warn($fehler);
        }

        return self::SUCCESS;
    }
}
