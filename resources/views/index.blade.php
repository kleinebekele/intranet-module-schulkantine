<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2">
            <x-module-icon name="restaurant" class="text-2xl text-indigo-600" />
            <h1 class="text-xl font-semibold text-gray-800">Schulkantine</h1>
        </div>
    </x-slot>

    <div class="max-w-4xl space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-6">
            <h2 class="text-lg font-semibold text-gray-800">Willkommen in der Schulkantine 🍽️</h2>
            <p class="mt-2 text-gray-600">
                <strong>Stammdaten</strong> und <strong>Speiseplan</strong> stehen. Als Nächstes kommt die
                <strong>Vorbestellung</strong> – hier siehst du den Fahrplan.
            </p>
        </div>

        @php
            $phasen = [
                ['nr' => 1, 'titel' => 'Stammdaten', 'text' => 'Saison &amp; Kalender, Gruppen, Kategorien, Gerichte, Allergene, Teilnehmer', 'status' => 'Erledigt'],
                ['nr' => 2, 'titel' => 'Speiseplan', 'text' => 'Tagesangebot je Öffnungstag im Wochen-Raster (Kategorie-Farben, Schließtage)', 'status' => 'Erledigt'],
                ['nr' => 3, 'titel' => 'Vorbestellung', 'text' => 'Bestellen &amp; stornieren, Fristen, Wochen-Freigabe, OGS-Abo', 'status' => 'Als Nächstes'],
                ['nr' => 4, 'titel' => 'Ausgabe &amp; Betrieb', 'text' => 'Ausgabelisten, Abhaken, spontane Abholung', 'status' => 'Geplant'],
                ['nr' => 5, 'titel' => 'Auswertung', 'text' => 'Mengen und Export für die Abrechnung', 'status' => 'Geplant'],
                ['nr' => 6, 'titel' => 'Feedback', 'text' => 'Daumen-Bewertung (anonym ausgewertet)', 'status' => 'Optional'],
            ];

            $styles = [
                'Erledigt'     => ['badge' => 'bg-green-50 text-green-700', 'circle' => 'bg-green-100 text-green-700', 'card' => 'border-green-200'],
                'Als Nächstes' => ['badge' => 'bg-amber-100 text-amber-800', 'circle' => 'bg-amber-100 text-amber-800', 'card' => 'border-amber-300 ring-1 ring-amber-200'],
                'Geplant'      => ['badge' => 'bg-gray-100 text-gray-500', 'circle' => 'bg-gray-100 text-gray-400', 'card' => 'border-gray-200'],
                'Optional'     => ['badge' => 'bg-gray-100 text-gray-500', 'circle' => 'bg-gray-100 text-gray-400', 'card' => 'border-gray-200 border-dashed'],
            ];
        @endphp

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($phasen as $phase)
                @php $s = $styles[$phase['status']] ?? $styles['Geplant']; @endphp
                <div class="rounded-xl border bg-white p-5 {{ $s['card'] }}">
                    <div class="flex items-center justify-between">
                        <span class="inline-flex h-7 w-7 items-center justify-center rounded-full text-sm font-semibold {{ $s['circle'] }}">
                            @if ($phase['status'] === 'Erledigt')
                                &check;
                            @else
                                {{ $phase['nr'] }}
                            @endif
                        </span>
                        <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $s['badge'] }}">
                            {{ $phase['status'] }}
                        </span>
                    </div>
                    <h3 class="mt-3 font-semibold text-gray-800">{!! $phase['titel'] !!}</h3>
                    <p class="mt-1 text-sm text-gray-500">{!! $phase['text'] !!}</p>
                </div>
            @endforeach
        </div>
    </div>
</x-app-layout>
