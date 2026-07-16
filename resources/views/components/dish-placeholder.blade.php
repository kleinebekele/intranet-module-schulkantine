{{-- Platzhalter-Illustration für Gerichte ohne Foto: gedeckter Teller mit Besteck. --}}
<svg viewBox="0 0 96 96" xmlns="http://www.w3.org/2000/svg" {{ $attributes->merge(['class' => 'h-full w-full']) }} role="img" aria-label="Kein Foto">
    <defs>
        <linearGradient id="dish-ph-bg" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="#eef2ff"/>
            <stop offset="100%" stop-color="#e2e8f0"/>
        </linearGradient>
    </defs>
    <rect width="96" height="96" rx="14" fill="url(#dish-ph-bg)"/>
    {{-- Besteck --}}
    <g stroke="#c3cbd9" stroke-width="3" stroke-linecap="round" fill="none">
        <line x1="21" y1="30" x2="21" y2="66"/>
        <path d="M17 30 v9 M21 30 v9 M25 30 v9" />
        <line x1="75" y1="30" x2="75" y2="66"/>
        <path d="M75 30 c-6 2 -6 12 0 14" fill="#c3cbd9" stroke="none"/>
    </g>
    {{-- Teller --}}
    <circle cx="48" cy="50" r="24" fill="#f8fafc" stroke="#cbd5e1" stroke-width="2.5"/>
    <circle cx="48" cy="50" r="15" fill="none" stroke="#dbe2ea" stroke-width="2"/>
    {{-- kleines „Gericht" --}}
    <circle cx="48" cy="50" r="7" fill="#cdd6e3"/>
</svg>
