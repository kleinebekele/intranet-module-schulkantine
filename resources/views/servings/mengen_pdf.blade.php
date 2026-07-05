<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Mengenliste {{ $date->format('d.m.Y') }}</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { margin: 0; color: #1f2937; font-size: 12px; }
        .head { border-bottom: 2px solid #4f46e5; padding-bottom: 8px; margin-bottom: 16px; }
        .head h1 { margin: 0 0 2px; font-size: 20px; color: #111827; }
        .head .sub { font-size: 12px; color: #6b7280; }
        .meta { font-size: 11px; color: #6b7280; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        th { text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: .04em;
             color: #6b7280; border-bottom: 1px solid #d1d5db; padding: 6px 8px; }
        th.num, td.num { text-align: center; }
        td { padding: 7px 8px; border-bottom: 1px solid #f0f0f0; }
        td.dish { font-weight: bold; }
        td.cat { color: #4b5563; }
        tfoot td { border-top: 2px solid #9ca3af; border-bottom: none; font-weight: bold; padding-top: 8px; }
        .num.big { font-size: 15px; font-weight: bold; }
        .ogs { border: 1px solid #d1d5db; border-radius: 6px; padding: 10px 12px; font-size: 13px; }
        .ogs strong { font-size: 16px; }
        .empty { color: #6b7280; font-style: italic; padding: 10px 0; }
        .foot { margin-top: 22px; font-size: 10px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 8px; }
    </style>
</head>
<body>
    <div class="head">
        <h1>Mengenliste Küche</h1>
        <div class="sub">{{ ucfirst($date->isoFormat('dddd')) }}, {{ $date->format('d.m.Y') }} &nbsp;·&nbsp; Saison „{{ $season->name }}"</div>
    </div>

    <div class="meta">Zu kochende Portionen aus den Vorbestellungen (nach Bestellschluss). Grundlage für Einkauf und Kochmenge.</div>

    @if (empty($menuByDish))
        <p class="empty">Für diesen Tag liegen keine Menü-Bestellungen vor.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Gericht</th>
                    <th>Kategorie</th>
                    <th class="num">Portionen</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($menuByDish as $m)
                    <tr>
                        <td class="dish">{{ $m['dish']?->name ?? '—' }}</td>
                        <td class="cat">{{ $m['category'] }}</td>
                        <td class="num big">{{ $m['ordered'] }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2">Portionen gesamt (Menü)</td>
                    <td class="num big">{{ collect($menuByDish)->sum('ordered') }}</td>
                </tr>
            </tfoot>
        </table>
    @endif

    <div class="ogs">
        OGS-Essen heute: <strong>{{ $ogs['attending'] }}</strong> Portionen
        @if ($ogsPrice > 0)
            &nbsp;·&nbsp; Fixpreis {{ number_format($ogsPrice, 2, ',', '.') }} €
        @endif
    </div>

    <div class="foot">
        Erzeugt am {{ $generatedAt->format('d.m.Y H:i') }} Uhr · Schulkantine · Intranet.
        Spontane Abholungen sind nicht eingerechnet (Vorrat bleibt küchenintern).
    </div>
</body>
</html>
