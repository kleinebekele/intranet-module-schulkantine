<?php

namespace Intranet\Modules\Schulkantine\Support;

use Illuminate\Support\HtmlString;

/**
 * Fleischart-Symbole als Bild statt Emoji: Nicht jedes System kann Emoji darstellen
 * (Windows XP zeigt Kästchen). Die Grafiken stammen aus Twemoji (CC-BY 4.0, siehe
 * resources/twemoji/LIZENZ.txt) und werden als data-URI in die Seite gelegt – so kommen
 * sie auch auf Geräten ohne Internet an.
 */
class Symbolbild
{
    /** Emoji → Datei in resources/twemoji */
    private const DATEIEN = [
        '🌱' => '1f331', '🥦' => '1f966', '🐟' => '1f41f', '🐔' => '1f414',
        '🐄' => '1f404', '🐖' => '1f416', '🐑' => '1f411', '🍖' => '1f356',
    ];

    /** @var array<string, string> Datei → data-URI */
    private static array $cache = [];

    /**
     * Ein oder mehrere Symbole (z. B. "🐄🐖" oder "🥦 🐟") als Bilder; anderes bleibt Text.
     * Größe folgt der Schrift (1em), passt sich also der umgebenden Textgröße an.
     */
    public static function html(?string $symbole, ?string $titel = null): HtmlString
    {
        if ($symbole === null || $symbole === '') {
            return new HtmlString('');
        }
        $titelAttr = $titel ? ' title="'.e($titel).'"' : '';
        $html = '';
        foreach (mb_str_split($symbole) as $zeichen) {
            $datei = self::DATEIEN[$zeichen] ?? null;
            if ($datei === null) {
                // Variation-Selector u. ä. still weglassen, Leerzeichen und Unbekanntes als Text.
                $html .= $zeichen === "\u{FE0F}" ? '' : e($zeichen);

                continue;
            }
            $html .= '<img src="'.self::uri($datei).'" alt="'.e($titel ?? $zeichen).'"'.$titelAttr
                .' style="display:inline-block;height:1em;width:1em;vertical-align:-0.15em">';
        }

        return new HtmlString($html);
    }

    private static function uri(string $datei): string
    {
        return self::$cache[$datei] ??= 'data:image/svg+xml;base64,'
            .base64_encode((string) @file_get_contents(dirname(__DIR__, 2).'/resources/twemoji/'.$datei.'.svg'));
    }
}
