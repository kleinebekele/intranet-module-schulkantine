<?php

namespace Intranet\Modules\Schulkantine\Support;

use App\Models\User;
use Illuminate\Support\Carbon;
use Intranet\Modules\Schulkantine\Models\MenuDay;
use Intranet\Modules\Schulkantine\Models\Setting;

/**
 * Essenspreise je Vertragsgruppe aus Linear. Der nächtliche Linear-Import
 * (Modul Verwaltung, `MgArtDat`) legt sie in `kantine_settings.linear_prices` ab;
 * die Kantine liest Linear nicht selbst.
 *
 * Menüs mit „Preis aus Linear" kosten je Esser den Preis seines Vertrags
 * (Rolle `kantine_vertrag_<art>`, s. Essensvertrag), bei mehreren den günstigsten.
 * Wer keinem Vertrag mit Preis zuzuordnen ist, zahlt den teuersten Preis. Sind
 * (noch) keine Preise importiert, gilt der im Menü eingetragene Preis.
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

    /** Vertragsart der OGS (Klasse 1–4). */
    public const ART_OGS = 27;

    /** @var array<int,float>|null|false  false = noch nicht geladen */
    private static array|null|false $preise = false;

    /**
     * Zuletzt importierter Preis je Vertragsart, null = noch nichts importiert.
     *
     * @return array<int,float>|null
     */
    public static function aktuell(): ?array
    {
        if (self::$preise === false) {
            $roh = Setting::current()->linear_prices;
            $preise = [];
            foreach (is_array($roh) ? $roh : [] as $art => $betrag) {
                $preise[(int) $art] = round((float) $betrag, 2);
            }
            ksort($preise);
            self::$preise = $preise ?: null;
        }

        return self::$preise;
    }

    /** Wann zuletzt importiert. */
    public static function stand(): ?Carbon
    {
        return Setting::current()->linear_prices_at;
    }

    /** Der teuerste Preis – gilt für alle, die keinem Vertrag zuzuordnen sind. */
    public static function fallback(): ?float
    {
        $preise = self::aktuell();

        return $preise ? max($preise) : null;
    }

    /** Preis einer Vertragsart, null wenn nicht importiert. */
    public static function art(int $art): ?float
    {
        return self::aktuell()[$art] ?? null;
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
