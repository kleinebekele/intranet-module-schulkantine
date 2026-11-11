<?php

namespace Intranet\Modules\Schulkantine\Support;

use App\Models\User;
use App\Support\Audit;

/**
 * Audit-Einträge der Mensa (Bestellungen, Abo, Budget, Verwaltungsaktionen). Ergänzt die
 * Quelle (Intranet oder Bestell-Terminal) und fängt Fehler ab – ein Protokoll-Problem darf
 * nie eine Bestellung verhindern.
 *
 * Am Bestell-Terminal ist niemand im Intranet angemeldet: Handelnder ist dort der Besteller
 * per Chip, deshalb $akteur mitgeben.
 */
class MensaAudit
{
    public const BEZEICHNUNGEN = [
        'kantine.bestellt' => 'Mensa: bestellt',
        'kantine.abbestellt' => 'Mensa: abbestellt',
        'kantine.abo_geaendert' => 'Mensa: OGS-Abo geändert',
        'kantine.bestellung_geloescht' => 'Mensa: Bestellung gelöscht (Verwaltung)',
        'kantine.vertraeglichkeiten' => 'Mensa: Allergien/Diäten geändert',
        'kantine.freigaben' => 'Mensa: Freigaben/Budget geändert',
        'kantine.budget' => 'Mensa: Wochenbudget geändert',
        'kantine.saison_geaendert' => 'Mensa: Saison-Einstellungen geändert',
        'kantine.linear_gesendet' => 'Mensa: Monat an Linear übertragen',
        'kantine.menueserve_buchungen' => 'Mensa: Menü&Serve-Buchungen übernommen',
        'kantine.chip_unbekannt' => 'Mensa: unbekannter Chip am Terminal',
        'kantine.ausgegeben' => 'Mensa: Ausgabe gebucht',
        'kantine.ausgabe_zurueck' => 'Mensa: Ausgabe zurückgenommen',
    ];

    /** @param  array<string, mixed>  $daten */
    public static function schreiben(string $aktion, string $text, ?User $betroffener = null, array $daten = [], ?User $akteur = null): void
    {
        try {
            Audit::schreiben($aktion, $text, $betroffener, $daten + ['Quelle' => self::quelle()], akteur: $akteur ?? auth()->user());
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private static function quelle(): string
    {
        return match (true) {
            request()->routeIs('kantine.bestellterminal.*') => 'Bestell-Terminal',
            request()->routeIs('module.schulkantine.servings.terminal*') => 'Ausgabe-Terminal',
            request()->routeIs('module.schulkantine.servings.*') => 'Ausgabe-Übersicht',
            request()->routeIs('module.schulkantine.ogs-ausgabe.*') => 'Ausgabe OGS',
            default => 'Intranet',
        };
    }
}
