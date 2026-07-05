<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2">
            <x-module-icon name="restaurant" class="text-2xl text-indigo-600" />
            <h1 class="text-xl font-semibold text-gray-800">No-Shows</h1>
        </div>
    </x-slot>

    <div class="max-w-3xl">
        @if (! $season)
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Es ist keine Saison als „aktiv" markiert.
            </div>
        @else
            {{-- Tages-Navigation --}}
            <div class="mb-4 flex items-center justify-between gap-4">
                <div>
                    @if ($prevDate)
                        <a href="{{ route('module.schulkantine.servings.noshows', ['date' => $prevDate]) }}"
                           class="inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">‹ Vorheriger Tag</a>
                    @endif
                </div>
                <div class="text-center">
                    <div class="text-sm font-semibold text-gray-800">{{ $date->isoFormat('dddd') }}, {{ $date->format('d.m.Y') }}</div>
                    <div class="text-xs text-gray-400">Saison „{{ $season->name }}"</div>
                </div>
                <div>
                    @if ($nextDate)
                        <a href="{{ route('module.schulkantine.servings.noshows', ['date' => $nextDate]) }}"
                           class="inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Nächster Tag ›</a>
                    @endif
                </div>
            </div>

            <div class="mb-4 flex items-center gap-4">
                <a href="{{ route('module.schulkantine.servings.index', ['date' => $date->toDateString()]) }}"
                   class="inline-flex items-center gap-1 text-sm font-medium text-indigo-600 hover:text-indigo-800">‹ zur Ausgabeliste</a>
                <a href="{{ route('module.schulkantine.servings.quantities', ['date' => $date->toDateString()]) }}"
                   class="inline-flex items-center gap-1 text-sm font-medium text-indigo-600 hover:text-indigo-800">
                    <x-module-icon name="bar-chart-alt-2" class="text-base" /> Mengen
                </a>
            </div>

            @if (! $open)
                <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-6 text-center text-sm text-amber-700">
                    🔒 Die Kantine hat an diesem Tag nicht geöffnet ({{ $closedReason }}).
                </div>
            @else
                @php $ogsNoShows = $ogs['noShows']; $total = count($noShows) + $ogsNoShows->count(); @endphp

                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
                    <div class="flex items-center justify-between border-b border-gray-100 bg-gray-50 px-4 py-2">
                        <span class="text-sm font-semibold text-gray-700">Bestellt, aber nicht abgeholt</span>
                        <span class="rounded-full px-2.5 py-0.5 text-sm font-semibold {{ $total ? 'bg-amber-100 text-amber-800' : 'bg-green-100 text-green-800' }}">{{ $total }}</span>
                    </div>

                    @if ($total === 0)
                        <p class="px-4 py-6 text-center text-sm text-green-700">Alles abgehakt – keine offenen Ausgaben. 🎉</p>
                    @else
                        <ul class="divide-y divide-gray-50 text-sm">
                            @foreach ($noShows as $n)
                                <li class="flex items-center justify-between px-4 py-2.5">
                                    <span class="font-medium text-gray-800">{{ $n['user']?->name ?? 'Unbekannt' }}</span>
                                    <span class="text-gray-500">{{ $n['dish'] ?? '—' }}</span>
                                </li>
                            @endforeach
                            @foreach ($ogsNoShows as $u)
                                <li class="flex items-center justify-between px-4 py-2.5">
                                    <span class="font-medium text-gray-800">{{ $u->name }}</span>
                                    <span class="text-gray-500">OGS-Essen</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                <p class="mt-3 text-xs text-gray-400">
                    „No-Show" = bestellt, aber (noch) nicht als ausgegeben abgehakt. Vor dem Mittag sind das die noch offenen Ausgaben,
                    danach die echten No-Shows. Verbindlich bestelltes Essen wird unabhängig von der Abholung berechnet.
                </p>
            @endif
        @endif
    </div>
</x-app-layout>
