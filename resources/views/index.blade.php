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
                Das Modul-Grundgerüst steht! Wir bauen es Schritt für Schritt in Phasen auf.
                Unten siehst du den Fahrplan – jeder Bereich wird nach und nach lebendig.
            </p>
        </div>

        @php
            $phasen = [
                ['nr' => 1, 'titel' => 'Stammdaten', 'text' => 'Saison &amp; Kalender, Gruppen, Kategorien, Gerichte, Allergene, Teilnehmer', 'status' => 'Als Nächstes'],
                ['nr' => 2, 'titel' => 'Speiseplan', 'text' => 'Menüs je Gruppe und Tag', 'status' => 'Geplant'],
                ['nr' => 3, 'titel' => 'Vorbestellung', 'text' => 'Bestellen, stornieren, Fristen, OGS-Saison-Abo', 'status' => 'Geplant'],
                ['nr' => 4, 'titel' => 'Ausgabe &amp; Betrieb', 'text' => 'Ausgabelisten, Abhaken, spontane Abholung', 'status' => 'Geplant'],
                ['nr' => 5, 'titel' => 'Auswertung', 'text' => 'Mengen und Export für die Abrechnung', 'status' => 'Geplant'],
                ['nr' => 6, 'titel' => 'Feedback', 'text' => 'Daumen-Bewertung (anonym ausgewertet)', 'status' => 'Optional'],
            ];
        @endphp

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($phasen as $phase)
                <div class="rounded-xl border border-gray-200 bg-white p-5">
                    <div class="flex items-center justify-between">
                        <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-indigo-50 text-sm font-semibold text-indigo-600">
                            {{ $phase['nr'] }}
                        </span>
                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500">
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
