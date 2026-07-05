<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Abrechnung {{ $monthLabel }}</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { margin: 0; color: #1f2937; font-size: 11px; }
        .head { border-bottom: 2px solid #4f46e5; padding-bottom: 8px; margin-bottom: 14px; }
        .head h1 { margin: 0 0 2px; font-size: 19px; color: #111827; }
        .head .sub { font-size: 12px; color: #6b7280; }
        .cards { width: 100%; margin-bottom: 14px; }
        .cards td { width: 33%; border: 1px solid #e5e7eb; padding: 8px 10px; }
        .cards .lbl { font-size: 9px; text-transform: uppercase; letter-spacing: .04em; color: #6b7280; }
        .cards .val { font-size: 16px; font-weight: bold; color: #111827; }
        table.list { width: 100%; border-collapse: collapse; }
        table.list th { text-align: left; font-size: 9px; text-transform: uppercase; letter-spacing: .03em;
             color: #6b7280; border-bottom: 1px solid #d1d5db; padding: 5px 6px; }
        table.list th.num, table.list td.num { text-align: right; }
        table.list th.ctr, table.list td.ctr { text-align: center; }
        table.list td { padding: 5px 6px; border-bottom: 1px solid #f0f0f0; }
        tr.hh td { background: #f3f4f6; font-size: 9px; text-transform: uppercase; letter-spacing: .03em;
             font-weight: bold; color: #4b5563; padding-top: 7px; }
        td.name { font-weight: bold; }
        td.grp { color: #6b7280; font-size: 9px; }
        td.sum { font-weight: bold; }
        .paid { color: #15803d; font-weight: bold; }
        .open { color: #b45309; }
        tfoot td { border-top: 2px solid #9ca3af; border-bottom: none; font-weight: bold; padding-top: 8px; font-size: 12px; }
        .empty { color: #6b7280; font-style: italic; padding: 12px 0; }
        .foot { margin-top: 18px; font-size: 9px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 8px; }
    </style>
    @php $euro = fn ($v) => number_format((float) $v, 2, ',', '.').' €'; @endphp
</head>
<body>
    <div class="head">
        <h1>Abrechnung Schulkantine</h1>
        <div class="sub">{{ $monthLabel }} &nbsp;·&nbsp; Saison „{{ $season->name }}"</div>
    </div>

    <table class="cards">
        <tr>
            <td><div class="lbl">Personen</div><div class="val">{{ $personCount }}</div></td>
            <td><div class="lbl">Bezahlt</div><div class="val">{{ $euro($paidTotal) }}</div></td>
            <td><div class="lbl">Offen</div><div class="val">{{ $euro($openTotal) }}</div></td>
        </tr>
    </table>

    @if (empty($households))
        <p class="empty">Für {{ $monthLabel }} liegen keine abrechenbaren Posten vor.</p>
    @else
        <table class="list">
            <thead>
                <tr>
                    <th>Person</th>
                    <th class="num">Menü</th>
                    <th class="num">OGS</th>
                    <th class="num">Spontan</th>
                    <th class="num">Pfand</th>
                    <th class="num">Summe</th>
                    <th class="ctr">Bezahlt</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($households as $hh)
                    <tr class="hh">
                        <td colspan="5">Haushalt {{ $hh['name'] }}</td>
                        <td class="num">{{ $euro($hh['subtotal']) }}</td>
                        <td class="ctr">{{ $hh['open'] > 0 ? 'offen '.$euro($hh['open']) : 'ok' }}</td>
                    </tr>
                    @foreach ($hh['members'] as $m)
                        @php $l = $m['line']; @endphp
                        <tr>
                            <td class="name">{{ $m['user']->name }}
                                <span class="grp">· {{ $m['group'] }}@if ($l['no_show_count'] > 0) · {{ $l['no_show_count'] }}× No-Show @endif</span>
                            </td>
                            <td class="num">{{ $l['menu_total'] > 0 ? $euro($l['menu_total']) : '–' }}</td>
                            <td class="num">{{ $l['ogs_total'] > 0 ? $euro($l['ogs_total']) : '–' }}</td>
                            <td class="num">{{ $l['spontan_total'] > 0 ? $euro($l['spontan_total']) : '–' }}</td>
                            <td class="num">{{ $l['pfand_net'] != 0 ? $euro($l['pfand_net']) : '–' }}</td>
                            <td class="num sum">{{ $euro($l['total']) }}</td>
                            <td class="ctr">{!! $m['paid'] ? '<span class="paid">✓</span>' : '<span class="open">offen</span>' !!}</td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5">Gesamt {{ $monthLabel }}</td>
                    <td class="num">{{ $euro($grandTotal) }}</td>
                    <td class="ctr">{{ $openTotal > 0 ? 'offen '.$euro($openTotal) : 'bezahlt' }}</td>
                </tr>
            </tfoot>
        </table>
    @endif

    <div class="foot">
        Erzeugt am {{ $generatedAt->format('d.m.Y H:i') }} Uhr · Schulkantine · Intranet.
        Basis: verbindliche Vorbestellungen (No-Shows zahlen), spontane Abholungen, Chip-Pfand (Ausgabe +, Rückgabe −).
        Rechtzeitig stornierte Bestellungen sind nicht enthalten.
    </div>
</body>
</html>
