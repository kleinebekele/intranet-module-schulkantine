<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>{{ $titel }} {{ $date->format('d.m.Y') }}</title>
    <style>
        @page { margin: 28px 32px; }
        * { font-family: DejaVu Sans, sans-serif; }
        body { margin: 0; color: #1f2937; font-size: 12px; }
        .head { border-bottom: 2px solid #4f46e5; padding-bottom: 6px; margin-bottom: 10px; }
        .head h1 { margin: 0 0 2px; font-size: 18px; color: #111827; }
        .head .sub { font-size: 11px; color: #6b7280; }
        .count { font-size: 12px; margin-bottom: 8px; color: #6b7280; }
        .count strong { font-size: 15px; color: #111827; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-size: 9px; text-transform: uppercase; letter-spacing: .04em;
             color: #6b7280; border-bottom: 1px solid #d1d5db; padding: 4px 4px; }
        td { padding: 4px 4px; border-bottom: 1px solid #ececec; vertical-align: top; }
        td.num { width: 20px; text-align: right; color: #9ca3af; font-size: 10px; padding-top: 5px; }
        td.klasse { width: 30px; white-space: nowrap; }
        td.klasse.text { font-size: 9.5px; padding-top: 5px; color: #4b5563; }
        td.name { font-weight: bold; }
        td.check { width: 16px; text-align: center; color: #9ca3af; font-size: 13px; padding-top: 2px; }
        td.luecke, th.luecke { width: 14px; border: 0; }
        .zeile { color: #4b5563; font-size: 9.5px; font-weight: normal; }
        .warn { color: #b91c1c; font-size: 9.5px; font-weight: normal; }
        .empty { color: #6b7280; font-style: italic; padding: 10px 0; }
        .foot { margin-top: 10px; font-size: 9px; color: #9ca3af; }
        .umbruch { page-break-after: always; }
    </style>
</head>
<body>
    {{-- Gemeinsame Druckliste für OGS-Sammelliste und Besteller-Liste: je Eintrag
         klasse, sortName und zeilen (kleine Zusatzzeilen unter dem Namen, warn = rot). --}}
    <div class="head">
        <h1>{{ $titel }}</h1>
        <div class="sub">{{ ucfirst($date->isoFormat('dddd')) }}, {{ $date->format('d.m.Y') }} &nbsp;·&nbsp; Saison „{{ $season->name }}"</div>
    </div>

    <div class="count">{{ $zaehler }}: <strong>{{ $eintraege->count() }}</strong> &nbsp;·&nbsp; {{ $sortierung ?? 'sortiert nach Klasse, Nachname' }}</div>

    @if ($eintraege->isEmpty())
        <p class="empty">{{ $leer }}</p>
    @else
        {{-- Zweispaltig für möglichst wenig Seiten. dompdf kennt keine CSS-Spalten und bricht
             verschachtelte Tabellen schlecht um – daher je Seite EINE flache Tabelle: links die
             ersten n, rechts die nächsten n Einträge (Lesefolge spaltenweise). Eine Tabellenzeile
             ist so hoch wie ihr höherer Eintrag; jede gedruckte Zusatzzeile (~45 Zeichen breit,
             lange Texte brechen um) macht sie ~0,6 Zeilen höher. Platz je Seite in einfachen
             Zeilen (erste Seite mit Kopf etwas weniger). --}}
        @php
            $hoehe = fn ($e) => $e ? 1 + 0.6 * array_sum(array_map(fn ($z) => max(1, (int) ceil(mb_strlen($z['text']) / 45)), $e['zeilen'])) : 1;
            $liste = $eintraege->values()->map(fn ($e, $i) => ['nr' => $i + 1] + $e)->all();
            $seiten = [];
            $pos = 0;
            while ($pos < count($liste)) {
                $platz = $seiten === [] ? 35 : 39;
                $rest = count($liste) - $pos;
                $passt = function (int $n) use ($liste, $pos, $hoehe, $platz) {
                    $summe = 0;
                    for ($z = 0; $z < $n; $z++) {
                        $summe += max($hoehe($liste[$pos + $z] ?? null), $hoehe($liste[$pos + $n + $z] ?? null));
                    }

                    return $summe <= $platz;
                };
                // Kleinstes n, das den Rest aufnimmt (gleichmäßige letzte Seite), sonst das größte, das passt.
                $n = 1;
                while ($n * 2 < $rest && $passt($n + 1)) {
                    $n++;
                }
                $seiten[] = [array_slice($liste, $pos, $n), array_slice($liste, $pos + $n, $n)];
                $pos += 2 * $n;
            }
        @endphp
        @foreach ($seiten as [$links, $rechts])
            <table @class(['umbruch' => ! $loop->last])>
                <thead>
                    <tr>
                        @foreach ([0, 1] as $s)
                            @if ($s === 1)<th class="luecke"></th>@endif
                            <th></th><th>Kl.</th><th>Name</th><th>✓</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @for ($z = 0; $z < max(count($links), count($rechts)); $z++)
                        <tr>
                            @foreach ([$links[$z] ?? null, $rechts[$z] ?? null] as $s => $e)
                                @if ($s === 1)<td class="luecke"></td>@endif
                                @if ($e)
                                    <td class="num">{{ $e['nr'] }}.</td>
                                    <td @class(['klasse', 'text' => $e['klasse'] !== null && ! preg_match('/^\d/', $e['klasse'])])>{{ $e['klasse'] ?? '–' }}</td>
                                    <td class="name">
                                        {{ $e['sortName'] }}
                                        @foreach ($e['zeilen'] as $zeile)
                                            <div class="{{ $zeile['warn'] ? 'warn' : 'zeile' }}">{{ $zeile['text'] }}</div>
                                        @endforeach
                                    </td>
                                    <td class="check">☐</td>
                                @else
                                    <td style="border:0"></td><td style="border:0"></td><td style="border:0"></td><td style="border:0"></td>
                                @endif
                            @endforeach
                        </tr>
                    @endfor
                </tbody>
            </table>
        @endforeach
    @endif

    <div class="foot">
        Erzeugt am {{ $generatedAt->format('d.m.Y H:i') }} Uhr · Mensa · Intranet. {{ $hinweis }}
    </div>
</body>
</html>
