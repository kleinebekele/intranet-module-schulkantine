<?php

namespace Intranet\Modules\Schulkantine\Support;

/**
 * Schreibt eine einfache Excel-Datei (.xlsx, ein Blatt) ohne Fremdbibliothek und
 * ohne PHP-Zip-Erweiterung: Die XML-Teile kommen unkomprimiert („stored") in ein
 * selbst gebautes ZIP. Kopfzeile fett, Zahlen als echte Zahlen (float = 2 Nachkommastellen).
 */
class XlsxSchreiber
{
    /**
     * @param  list<string>  $kopf
     * @param  list<list<string|int|float|null>>  $zeilen
     */
    public static function bauen(string $blatt, array $kopf, array $zeilen): string
    {
        $xml = fn (string $s) => htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $zeilenXml = '';
        foreach (array_merge([$kopf], $zeilen) as $r => $zeile) {
            $zeilenXml .= '<row r="'.($r + 1).'">';
            foreach (array_values($zeile) as $c => $wert) {
                $ref = self::spalte($c).($r + 1);
                if ($wert === null || $wert === '') {
                    continue;
                }
                if ($r > 0 && is_int($wert)) {
                    $zeilenXml .= '<c r="'.$ref.'"><v>'.$wert.'</v></c>';
                } elseif ($r > 0 && is_float($wert)) {
                    $zeilenXml .= '<c r="'.$ref.'" s="2"><v>'.round($wert, 2).'</v></c>';
                } else {
                    $zeilenXml .= '<c r="'.$ref.'" t="inlineStr"'.($r === 0 ? ' s="1"' : '').'><is><t>'.$xml((string) $wert).'</t></is></c>';
                }
            }
            $zeilenXml .= '</row>';
        }

        $teile = [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                .'<Default Extension="xml" ContentType="application/xml"/>'
                .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
                .'</Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                .'</Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                .'<sheets><sheet name="'.$xml(mb_substr($blatt, 0, 31)).'" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
                .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
                .'</Relationships>',
            'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
                .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
                .'<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
                .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
                .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
                .'<cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
                .'<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
                .'<xf numFmtId="4" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/></cellXfs>'
                .'</styleSheet>',
            'xl/worksheets/sheet1.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
                .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
                .'<sheetData>'.$zeilenXml.'</sheetData></worksheet>',
        ];

        return self::zip($teile);
    }

    /** 0 → A, 25 → Z, 26 → AA */
    private static function spalte(int $i): string
    {
        $s = '';
        for ($i++; $i > 0; $i = intdiv($i - 1, 26)) {
            $s = chr(65 + ($i - 1) % 26).$s;
        }

        return $s;
    }

    /** Minimales ZIP mit unkomprimierten Einträgen (Methode 0). */
    private static function zip(array $dateien): string
    {
        $daten = '';
        $verzeichnis = '';
        foreach ($dateien as $name => $inhalt) {
            $crc = crc32($inhalt);
            $laenge = strlen($inhalt);
            $kopf = pack('vvvvvVVVvv', 20, 0x0800, 0, 0, 0x21, $crc, $laenge, $laenge, strlen($name), 0);
            $verzeichnis .= pack('V', 0x02014b50).pack('v', 20).$kopf
                .pack('vvvVV', 0, 0, 0, 0, strlen($daten)).$name;
            $daten .= pack('V', 0x04034b50).$kopf.$name.$inhalt;
        }

        return $daten.$verzeichnis
            .pack('VvvvvVVv', 0x06054b50, 0, 0, count($dateien), count($dateien), strlen($verzeichnis), strlen($daten), 0);
    }
}
