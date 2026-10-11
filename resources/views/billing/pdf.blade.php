<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Abrechnung {{ $monat['label'] }}</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { margin: 0; color: #1f2937; font-size: 11px; }
        .head { border-bottom: 2px solid #4f46e5; padding-bottom: 8px; margin-bottom: 14px; }
        .head h1 { margin: 0 0 2px; font-size: 19px; color: #111827; }
        .head .sub { font-size: 12px; color: #6b7280; }
        h2 { font-size: 13px; margin: 16px 0 4px; color: #111827; }
        table.list { width: 100%; border-collapse: collapse; }
        table.list td { padding: 4px 6px; border-bottom: 1px solid #f0f0f0; vertical-align: top; }
        td.datum { width: 70px; color: #6b7280; white-space: nowrap; }
        td.num { text-align: right; white-space: nowrap; width: 70px; }
        .hinweis { color: #9ca3af; font-size: 9px; }
        .noshow { color: #be123c; font-size: 9px; }
        tr.summe td { border-top: 1px solid #9ca3af; border-bottom: none; font-weight: bold; padding-top: 6px; }
        .leer { color: #6b7280; font-style: italic; padding: 6px 0; }
        .gesamt { margin-top: 18px; width: 100%; border-collapse: collapse; }
        .gesamt td { border-top: 2px solid #4f46e5; padding-top: 8px; font-size: 13px; font-weight: bold; }
        .foot { margin-top: 22px; font-size: 9px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 8px; }
    </style>
    @php $euro = fn ($v) => number_format((float) $v, 2, ',', '.').' €'; @endphp
</head>
<body>
    <div class="head">
        <h1>Abrechnung Schulkantine</h1>
        <div class="sub">{{ $monat['label'] }} &nbsp;·&nbsp; Saison „{{ $season->name }}" &nbsp;·&nbsp; {{ $viewer->name }}</div>
    </div>

    @foreach ($esser as $u)
        @php
            $z = $monat['zellen'][$u->id];
            $posten = \Intranet\Modules\Schulkantine\Support\HaushaltsAbrechnung::posten($z);
        @endphp
        <h2>{{ $u->name }}</h2>
        @if ($z['details'] && $posten !== [])
            <table class="list">
                @foreach ($posten as $p)
                    <tr>
                        <td class="datum">{{ $p['datum']->locale('de')->isoFormat('dd DD.MM.') }}</td>
                        <td>
                            {{ $p['was'] }}
                            @if ($p['hinweis'])<br><span class="{{ str_starts_with($p['hinweis'], 'No-Show') ? 'noshow' : 'hinweis' }}">{{ $p['hinweis'] }}</span>@endif
                        </td>
                        <td class="num">{{ $euro($p['betrag']) }}</td>
                    </tr>
                @endforeach
                <tr class="summe"><td></td><td>Summe {{ $u->name }}</td><td class="num">{{ $euro($z['betrag']) }}</td></tr>
            </table>
        @elseif ($z['details'])
            <div class="leer">In diesem Monat ist nichts angefallen.</div>
        @elseif ($z['quelle'] === 'linear')
            <div class="leer">Abgerechnet im alten System: {{ $euro($z['betrag']) }} – Einzelposten liegen nicht vor.</div>
        @else
            <div class="leer">Für diesen Monat liegen keine Daten vor.</div>
        @endif
    @endforeach

    <table class="gesamt">
        <tr><td>Gesamtbetrag {{ $monat['label'] }}</td><td style="text-align:right">{{ $monat['gesamt'] !== null ? $euro($monat['gesamt']) : '–' }}</td></tr>
    </table>

    <div class="foot">
        {{ $season->hinweisOffen() }}<br>
        Erstellt am {{ $erstellt->format('d.m.Y H:i') }} · Intranet der Schulkantine
    </div>
</body>
</html>
