<?php

namespace Intranet\Modules\Schulkantine\Support;

use App\Ekkon\Ekkon;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Intranet\Modules\Schulkantine\Models\MenuDay;

/**
 * Essenspreise je Vertragsgruppe aus Linear (`MgArtDat`: Art, Jahr, Monat, Betrag –
 * ein Preis gilt ab Jahr/Monat, es zählt die jüngste Zeile bis heute).
 *
 * Menüs mit „Preis aus Linear" kosten je Esser den Preis seines Vertrags
 * (Rolle `kantine_vertrag_<art>`, s. Essensvertrag). Wer keinem Vertrag mit Preis
 * zuzuordnen ist, zahlt den teuersten Preis. Ist Linear nicht erreichbar, gilt
 * der im Menü eingetragene Preis.
 */
class LinearPreise
{
    /** Vertragsarten mit Anzeigenamen. */
    public const ARTEN = [
        27 => 'Klasse 1–4',
        28 => 'Schüler 5–13',
        29 => 'Lehrer/Mitarbeiter',
        50 => 'Eltern',
    ];

    private const CACHE_KEY = 'kantine.linear_preise';

    /**
     * Aktueller Preis je Vertragsart, null = Linear nicht lesbar.
     *
     * @return array<int,float>|null
     */
    public static function aktuell(): ?array
    {
        $preise = Cache::get(self::CACHE_KEY);
        if (is_array($preise)) {
            return $preise;
        }
        if (! Ekkon::mssqlKonfiguriert()) {
            return null;
        }

        $tabelle = (string) config('schulkantine.linear_preise_tabelle', 'Linear2.dbo.MgArtDat');
        $arten = implode(', ', array_keys(self::ARTEN));
        try {
            $zeilen = DB::connection(Ekkon::mssqlConnection())->select(
                "SELECT d.Art, d.Betrag FROM {$tabelle} d
                  WHERE d.Art IN ({$arten})
                    AND d.Jahr * 100 + d.Monat = (
                        SELECT MAX(x.Jahr * 100 + x.Monat) FROM {$tabelle} x
                         WHERE x.Art = d.Art
                           AND x.Jahr * 100 + x.Monat <= YEAR(GETDATE()) * 100 + MONTH(GETDATE()))"
            );
        } catch (\Throwable $e) {
            report($e);

            return null;
        }

        $preise = [];
        foreach ($zeilen as $z) {
            $preise[(int) $z->Art] = round((float) $z->Betrag, 2);
        }
        if ($preise === []) {
            return null;
        }
        ksort($preise);
        Cache::put(self::CACHE_KEY, $preise, now()->addMinutes(10));

        return $preise;
    }

    /** Der teuerste Preis – gilt für alle, die keinem Vertrag zuzuordnen sind. */
    public static function fallback(): ?float
    {
        $preise = self::aktuell();

        return $preise ? max($preise) : null;
    }

    /** Preis für diesen Esser (günstigster seiner Verträge), sonst Fallback. */
    public static function fuer(User $esser): ?float
    {
        $preise = self::aktuell();
        if (! $preise) {
            return null;
        }
        $eigene = [];
        foreach ($esser->roles as $rolle) {
            if (str_starts_with($rolle->role_id, Essensvertrag::PRAEFIX)) {
                $art = (int) substr($rolle->role_id, strlen(Essensvertrag::PRAEFIX));
                if (isset($preise[$art])) {
                    $eigene[] = $preise[$art];
                }
            }
        }

        return $eigene ? min($eigene) : max($preise);
    }

    /** Was kostet dieser Menü-Tag für diesen Esser? */
    public static function menuPreis(MenuDay $menuDay, User $esser): float
    {
        if ($menuDay->template?->linear_price) {
            $preis = self::fuer($esser);
            if ($preis !== null) {
                return $preis;
            }
        }

        return (float) $menuDay->price;
    }
}
