<x-app-layout>
    @php
        $money = fn ($v) => number_format((float) $v, 2, ',', '.').' €';
    @endphp

    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <x-module-icon name="restaurant" class="text-2xl text-indigo-600" />
                <h1 class="text-xl font-semibold text-gray-800">Essen bestellen</h1>
            </div>
            @if ($season)
                <div class="flex flex-wrap items-stretch gap-3">
                {{-- Allergien pflegt die Verwaltung: Kachel mit dem Hinweis aus den Saison-Einstellungen. --}}
                @if (! $season->daten_selbst && trim((string) $season->allergie_hinweis) !== '')
                    <div x-data="{ offen: false }">
                        <button type="button" @click="offen = true"
                                class="flex h-full items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-1.5 text-left hover:bg-amber-100">
                            <span class="text-xl">⚠️</span>
                            <span class="text-sm font-semibold text-amber-800">Fragen zu Allergenen?</span>
                        </button>
                        <div x-show="offen" x-cloak @keydown.escape.window="offen = false"
                             class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="offen = false">
                            <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl">
                                <h2 class="text-lg font-semibold text-gray-800">Fragen zu Allergenen?</h2>
                                <p class="mt-3 whitespace-pre-line text-sm text-gray-700">{{ $season->allergie_hinweis }}</p>
                                <div class="mt-5 text-right">
                                    <button type="button" @click="offen = false"
                                            class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Schließen</button>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
                <div class="rounded-lg border border-indigo-100 bg-indigo-50 px-4 py-1.5 text-right">
                    <div class="text-[11px] uppercase tracking-wide text-indigo-400">Kosten im {{ $monthStart->isoFormat('MMMM YYYY') }}</div>
                    <div class="text-lg font-bold text-indigo-700" id="month-total">{{ $money($monthTotal) }}</div>
                </div>
                </div>
            @endif
        </div>
    </x-slot>

    @include('schulkantine::orders._woche', [
        'routen' => [
            'woche' => 'module.schulkantine.orders.index',
            'bestellen' => 'module.schulkantine.orders.store',
            'abo' => 'module.schulkantine.orders.subscription',
        ],
        'ichId' => auth()->id(),
    ])
</x-app-layout>
