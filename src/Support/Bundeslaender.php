<?php

namespace Intranet\Modules\Schulkantine\Support;

/**
 * Die 16 Bundesländer mit ihren OpenHolidays-Subdivision-Codes
 * (Format "DE-XX") – genau die Codes, die die API für den Ferien-/
 * Feiertags-Import erwartet.
 */
class Bundeslaender
{
    /** @return array<string, string>  Code => Anzeigename */
    public static function all(): array
    {
        return [
            'DE-BW' => 'Baden-Württemberg',
            'DE-BY' => 'Bayern',
            'DE-BE' => 'Berlin',
            'DE-BB' => 'Brandenburg',
            'DE-HB' => 'Bremen',
            'DE-HH' => 'Hamburg',
            'DE-HE' => 'Hessen',
            'DE-MV' => 'Mecklenburg-Vorpommern',
            'DE-NI' => 'Niedersachsen',
            'DE-NW' => 'Nordrhein-Westfalen',
            'DE-RP' => 'Rheinland-Pfalz',
            'DE-SL' => 'Saarland',
            'DE-SN' => 'Sachsen',
            'DE-ST' => 'Sachsen-Anhalt',
            'DE-SH' => 'Schleswig-Holstein',
            'DE-TH' => 'Thüringen',
        ];
    }
}
