<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2">
            <x-module-icon name="tag" class="text-2xl text-indigo-600" />
            <h1 class="text-xl font-semibold text-gray-800">Meine Abrechnung</h1>
        </div>
    </x-slot>

    @php $euro = fn ($v) => number_format((float) $v, 2, ',', '.').' €'; @endphp

    <div class="w-full space-y-6">
        @if (! $season)
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Es ist keine Saison als „aktiv" markiert.
            </div>
        @else
            <div class="space-y-2">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <h2 class="text-base font-semibold text-gray-800">Saison „{{ $season->name }}"</h2>
                    <span class="text-xs text-gray-400">
                        Klick auf einen Betrag zeigt die Einzelposten.
                        @if ($linearStand) Zahlungsstand aus der Buchhaltung: {{ $linearStand->format('d.m.Y') }}. @endif
                    </span>
                </div>
                <p class="text-xs text-gray-500">
                    Ab November 2026 rechnet die Mensa über das Intranet ab. Aus dem alten System liegen nur die
                    Abrechnungsbeträge ab 2026 vor (ohne Einzelposten); wo wir nichts wissen, steht „keine Daten".
                </p>
                @include('schulkantine::billing._saison', ['daten' => $aktuell])
            </div>

            @foreach ($frueher as $daten)
                <details class="rounded-xl border border-gray-200 bg-white">
                    <summary class="cursor-pointer select-none px-4 py-3 text-sm font-semibold text-gray-700">
                        {{ $daten['season'] ? 'Frühere ' : '' }}{{ $daten['titel'] }}
                    </summary>
                    <div class="border-t border-gray-100 p-4">
                        @include('schulkantine::billing._saison', ['daten' => $daten])
                    </div>
                </details>
            @endforeach
        @endif
    </div>
</x-app-layout>
