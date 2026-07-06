<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Schulkantine – Anleitung</title>
    @include('schulkantine::guide._styles')
    <style>
        @page { margin: 24mm 16mm 20mm; }
        body { margin: 0; }
        /* dompdf-Unicode-Schrift, damit Umlaute und € sauber erscheinen. */
        .kantine-doc, .kantine-doc * { font-family: 'DejaVu Sans', sans-serif; }
        .kantine-doc code, .kantine-doc pre { font-family: 'DejaVu Sans Mono', monospace; }
        .pdf-title { border-bottom: 3px solid #4f46e5; padding-bottom: 10px; margin-bottom: 4px; }
        .pdf-title h1 { margin: 0; font-size: 23px; color: #111827; }
        .pdf-title .sub { color: #6b7280; font-size: 12px; margin-top: 2px; }
    </style>
</head>
<body>
    <div class="kantine-doc">
        <div class="pdf-title">
            <h1>Schulkantine – Bedienungsanleitung</h1>
            <div class="sub">Admin-Dokumentation &nbsp;·&nbsp; Stand {{ now()->format('d.m.Y') }}</div>
        </div>
        @include('schulkantine::guide._content')
    </div>
</body>
</html>
