{{-- Gemeinsames, auf .kantine-doc begrenztes Styling für Online-Ansicht UND PDF.
     Bewusst dompdf-tauglich: kein flex/grid, nur Block-Layout + Tabellen. Die
     .kantine-doc-Prefixe überschreiben auch Tailwinds Preflight in der Online-Ansicht. --}}
<style>
    .kantine-doc { color: #1f2937; font-size: 13px; line-height: 1.55; }
    .kantine-doc h2 { font-size: 19px; color: #3730a3; margin: 26px 0 8px; padding-bottom: 4px;
        border-bottom: 2px solid #e0e7ff; page-break-after: avoid; }
    .kantine-doc h2:first-child { margin-top: 0; }
    .kantine-doc h3 { font-size: 15px; color: #111827; margin: 18px 0 6px; page-break-after: avoid; }
    .kantine-doc p { margin: 6px 0; }
    .kantine-doc .lead { color: #4b5563; }
    .kantine-doc ul, .kantine-doc ol { margin: 6px 0; padding-left: 22px; }
    .kantine-doc li { margin: 3px 0; list-style: disc; }
    .kantine-doc ol > li { list-style: decimal; }
    .kantine-doc strong { color: #111827; }
    .kantine-doc table { width: 100%; border-collapse: collapse; margin: 10px 0; font-size: 12px;
        page-break-inside: avoid; }
    .kantine-doc th { background: #f3f4f6; text-align: left; padding: 6px 8px; border: 1px solid #e5e7eb;
        font-size: 11px; text-transform: uppercase; letter-spacing: .03em; color: #4b5563; }
    .kantine-doc td { padding: 6px 8px; border: 1px solid #e5e7eb; vertical-align: top; }
    .kantine-doc code { background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 3px;
        padding: 1px 5px; font-size: 12px; color: #0f172a; }

    /* Datei-/Format-Beispiel (kein Konsolen-Befehl – das ist .cmd).
       Bewusst mit eigener Klasse, damit die Regel nicht in die .cmd-Kästen durchschlägt. */
    .kantine-doc pre.beispiel { margin: 10px 0; border: 1px solid #e2e8f0; border-radius: 6px;
        background: #f8fafc; padding: 8px 11px; font-size: 12px; color: #0f172a;
        white-space: pre-wrap; page-break-inside: avoid; }

    /* Konsolen-Befehl – deutlich als Admin-/Server-Aktion markiert. */
    .kantine-doc .cmd { margin: 12px 0; border: 1px solid #fdba74; border-left: 5px solid #ea580c;
        border-radius: 6px; background: #fff7ed; padding: 9px 11px; page-break-inside: avoid; }
    .kantine-doc .cmd .badge { display: inline-block; background: #ea580c; color: #ffffff; font-size: 10px;
        font-weight: bold; text-transform: uppercase; letter-spacing: .04em; padding: 2px 7px;
        border-radius: 4px; margin-bottom: 6px; }
    .kantine-doc .cmd pre { margin: 4px 0 0; white-space: pre-wrap; font-size: 12px; color: #7c2d12; }

    /* Hinweis-Kasten. */
    .kantine-doc .note { margin: 10px 0; border: 1px solid #bfdbfe; border-left: 5px solid #2563eb;
        border-radius: 6px; background: #eff6ff; padding: 8px 11px; font-size: 12px; page-break-inside: avoid; }

    /* Testszenario-Kasten. */
    .kantine-doc .scenario { margin: 10px 0; border: 1px solid #e5e7eb; border-radius: 6px;
        padding: 8px 12px; background: #fafafa; page-break-inside: avoid; }
    .kantine-doc .scenario h4 { margin: 0 0 4px; font-size: 13px; color: #3730a3; }
    .kantine-doc .scenario .exp { color: #15803d; }

    /* Dunkles Farbschema der Online-Ansicht (Profil → Darstellung). Im PDF gibt es kein .dark.
       Die Farben kommen aus den Variablen des Cores, die dort bereits umgelegt sind. */
    .dark .kantine-doc { color: var(--color-gray-800); }
    .dark .kantine-doc h2 { color: var(--color-indigo-800); border-bottom-color: var(--color-indigo-100); }
    .dark .kantine-doc h3,
    .dark .kantine-doc strong { color: var(--color-gray-900); }
    .dark .kantine-doc .lead { color: var(--color-gray-600); }
    .dark .kantine-doc th { background: var(--color-gray-100); border-color: var(--color-gray-200); color: var(--color-gray-600); }
    .dark .kantine-doc td { border-color: var(--color-gray-200); }
    .dark .kantine-doc code { background: var(--color-slate-100); border-color: var(--color-slate-200); color: var(--color-slate-900); }
    .dark .kantine-doc pre.beispiel { background: var(--color-slate-50); border-color: var(--color-slate-200); color: var(--color-slate-900); }
    .dark .kantine-doc .cmd { background: var(--color-orange-50); border-color: var(--color-orange-300); border-left-color: #ea580c; }
    .dark .kantine-doc .cmd pre { color: var(--color-orange-900); }
    .dark .kantine-doc .note { background: var(--color-blue-50); border-color: var(--color-blue-200); border-left-color: #2563eb; }
    .dark .kantine-doc .scenario { background: var(--color-gray-50); border-color: var(--color-gray-200); }
    .dark .kantine-doc .scenario h4 { color: var(--color-indigo-800); }
    .dark .kantine-doc .scenario .exp { color: var(--color-green-700); }
</style>
