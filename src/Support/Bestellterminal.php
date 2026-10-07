<?php

namespace Intranet\Modules\Schulkantine\Support;

use App\Models\Setting;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Freigegebene Netze für das Bestell-Terminal (ohne Intranet-Anmeldung).
 * Gespeichert als Core-Einstellung, eine Adresse oder ein Netz (CIDR) je Zeile.
 * Leer = das Terminal ist nirgends erreichbar (außer für angemeldete Admins).
 */
class Bestellterminal
{
    public const EINSTELLUNG = 'kantine.bestellterminal.netze';

    /** @return list<string> */
    public static function netze(): array
    {
        return self::zerlegen((string) Setting::get(self::EINSTELLUNG, ''));
    }

    public static function erlaubt(?string $ip): bool
    {
        $netze = self::netze();

        return $ip !== null && $netze !== [] && IpUtils::checkIp($ip, $netze);
    }

    /**
     * Freitext → Liste gültiger Adressen/Netze. Trenner: Zeilen, Kommas, Leerzeichen;
     * alles ab „#" ist Kommentar.
     *
     * @return list<string>
     */
    public static function zerlegen(string $text): array
    {
        $text = preg_replace('/#[^\n]*/', '', $text);
        $teile = preg_split('/[\s,;]+/', (string) $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique($teile));
    }

    /** Ist der Eintrag eine gültige IP-Adresse oder ein gültiges Netz (CIDR)? */
    public static function gueltig(string $eintrag): bool
    {
        [$adresse, $maske] = array_pad(explode('/', $eintrag, 2), 2, null);
        if (! filter_var($adresse, FILTER_VALIDATE_IP)) {
            return false;
        }
        if ($maske === null) {
            return true;
        }
        $max = str_contains($adresse, ':') ? 128 : 32;

        return ctype_digit($maske) && (int) $maske <= $max;
    }
}
