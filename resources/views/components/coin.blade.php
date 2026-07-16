@props(['value' => '50c'])
@php
    // Euro-Münzen als SVG (keine echten Fotos verfügbar): 50 ct = Nordisches Gold,
    // 1 € = Silberring/Goldkern, 2 € = Goldring/Silberkern. Farbe des Kerns bestimmt
    // die Textfarbe.
    $faces = [
        '50c' => ['ring' => 'gold',   'center' => 'gold',   'label' => '50', 'sub' => 'CENT'],
        '1e'  => ['ring' => 'silver', 'center' => 'gold',   'label' => '1',  'sub' => 'EURO'],
        '2e'  => ['ring' => 'gold',   'center' => 'silver', 'label' => '2',  'sub' => 'EURO'],
    ];
    $f = $faces[$value] ?? $faces['50c'];

    $grad = [
        'gold'   => ['#fbe7a1', '#e6b422', '#a9791a'],
        'silver' => ['#f6f7f8', '#c8ccd0', '#8a9199'],
    ];
    $ring = $grad[$f['ring']];
    $center = $grad[$f['center']];
    $textColor = $f['center'] === 'gold' ? '#6b4e12' : '#3f4652';
    $rid = 'coin-ring-'.$value;
    $cid = 'coin-center-'.$value;
@endphp
<svg viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" class="h-full w-full" role="img" aria-label="{{ $f['label'] }} {{ $f['sub'] }}">
    <defs>
        <radialGradient id="{{ $rid }}" cx="50%" cy="36%" r="72%">
            <stop offset="0%" stop-color="{{ $ring[0] }}"/>
            <stop offset="58%" stop-color="{{ $ring[1] }}"/>
            <stop offset="100%" stop-color="{{ $ring[2] }}"/>
        </radialGradient>
        <radialGradient id="{{ $cid }}" cx="50%" cy="36%" r="72%">
            <stop offset="0%" stop-color="{{ $center[0] }}"/>
            <stop offset="58%" stop-color="{{ $center[1] }}"/>
            <stop offset="100%" stop-color="{{ $center[2] }}"/>
        </radialGradient>
    </defs>
    <circle cx="32" cy="32" r="31" fill="url(#{{ $rid }})" stroke="rgba(0,0,0,0.18)" stroke-width="1"/>
    <circle cx="32" cy="32" r="21.5" fill="url(#{{ $cid }})" stroke="rgba(0,0,0,0.12)" stroke-width="1"/>
    <text x="32" y="30" text-anchor="middle" dominant-baseline="central" font-family="Figtree, sans-serif" font-size="18" font-weight="800" fill="{{ $textColor }}">{{ $f['label'] }}</text>
    <text x="32" y="43" text-anchor="middle" font-family="Figtree, sans-serif" font-size="7" font-weight="700" letter-spacing="0.5" fill="{{ $textColor }}">{{ $f['sub'] }}</text>
</svg>
