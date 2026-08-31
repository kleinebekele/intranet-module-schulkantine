<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>OGS-Sammelliste {{ $date->format('d.m.Y') }}</title>
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
        td { padding: 7px 8px; border-bottom: 1px solid #f0f0f0; vertical-align: top; }
        td.num { width: 28px; text-align: right; color: #9ca3af; }
        td.name { font-weight: bold; }
        td.warn { color: #b91c1c; font-size: 11px; }
        td.check { width: 60px; text-align: center; color: #9ca3af; }
        .empty { color: #6b7280; font-style: italic; padding: 10px 0; }
        .count { font-size: 13px; margin-bottom: 12px; }
        .count strong { font-size: 16px; }
        .foot { margin-top: 22px; font-size: 10px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 8px; }
    </style>
</head>
<body>
    <div class="head">
        <h1>OGS-Sammelliste</h1>
        <div class="sub">{{ ucfirst($date->isoFormat('dddd')) }}, {{ $date->format('d.m.Y') }} &nbsp;·&nbsp; Saison „{{ $season->name }}"</div>
    </div>

    <div class="meta">Kinder, die heute ein OGS-Essen bekommen (Abo minus Abbestellungen bzw. Einzelbestellung).</div>

    <div class="count">Heute essende OGS-Kinder: <strong>{{ $eaters->count() }}</strong></div>

    @if ($eaters->isEmpty())
        <p class="empty">Heute isst kein OGS-Kind.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th></th>
                    <th>Name</th>
                    <th>Verträglichkeiten</th>
                    <th style="text-align:center;">Abgeholt</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($eaters as $i => $e)
                    <tr>
                        <td class="num">{{ $i + 1 }}.</td>
                        <td class="name">{{ $e['user']->name }}</td>
                        <td class="warn">
                            @if ($e['allergens'] || $e['diets'])
                                ⚠️
                                @if ($e['allergens']) Allergien: {{ implode(', ', $e['allergens']) }}.@endif
                                @if ($e['diets']) Diäten: {{ implode(', ', $e['diets']) }}.@endif
                            @endif
                        </td>
                        <td class="check">☐</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="foot">
        Erzeugt am {{ $generatedAt->format('d.m.Y H:i') }} Uhr · Schulkantine · Intranet.
        Bis zur Abbestell-Frist am Morgen kann sich die Liste noch ändern.
    </div>
</body>
</html>
