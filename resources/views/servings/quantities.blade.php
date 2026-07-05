<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2">
            <x-module-icon name="restaurant" class="text-2xl text-indigo-600" />
            <h1 class="text-xl font-semibold text-gray-800">Mengen</h1>
        </div>
    </x-slot>

    <div class="max-w-4xl">
        @if (! $season)
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Es ist keine Saison als „aktiv" markiert.
            </div>
        @else
            {{-- Tages-Navigation --}}
            <div class="mb-4 flex items-center justify-between gap-4">
                <div>
                    @if ($prevDate)
                        <a href="{{ route('module.schulkantine.servings.quantities', ['date' => $prevDate]) }}"
                           class="inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">‹ Vorheriger Tag</a>
                    @endif
                </div>
                <div class="text-center">
                    <div class="text-sm font-semibold text-gray-800">{{ $date->isoFormat('dddd') }}, {{ $date->format('d.m.Y') }}</div>
                    <div class="text-xs text-gray-400">Saison „{{ $season->name }}"</div>
                </div>
                <div>
                    @if ($nextDate)
                        <a href="{{ route('module.schulkantine.servings.quantities', ['date' => $nextDate]) }}"
                           class="inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Nächster Tag ›</a>
                    @endif
                </div>
            </div>

            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-4">
                    <a href="{{ route('module.schulkantine.servings.index', ['date' => $date->toDateString()]) }}"
                       class="inline-flex items-center gap-1 text-sm font-medium text-indigo-600 hover:text-indigo-800">‹ zur Ausgabeliste</a>
                    <a href="{{ route('module.schulkantine.servings.noshows', ['date' => $date->toDateString()]) }}"
                       class="inline-flex items-center gap-1 text-sm font-medium text-indigo-600 hover:text-indigo-800">
                        <x-module-icon name="search" class="text-base" /> No-Shows
                    </a>
                </div>
                @if ($open && ! empty($menuByDish))
                    <a href="{{ route('module.schulkantine.servings.mengen.pdf', ['date' => $date->toDateString()]) }}"
                       class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                        <x-module-icon name="download" class="text-base" /> Mengenliste als PDF
                    </a>
                @endif
            </div>

            @if (! $open)
                <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-6 text-center text-sm text-amber-700">
                    🔒 Die Kantine hat an diesem Tag nicht geöffnet ({{ $closedReason }}).
                </div>
            @else
                {{-- Mengen je Gericht --}}
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
                    <div class="border-b border-gray-100 bg-gray-50 px-4 py-2 text-sm font-semibold text-gray-700">
                        Portionen je Gericht (aus den Vorbestellungen)
                    </div>
                    @if (empty($menuByDish))
                        <p class="px-4 py-6 text-center text-sm text-gray-500">Für diesen Tag liegen keine Menü-Bestellungen vor.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead>
                                    <tr class="border-b border-gray-100 text-left text-xs uppercase tracking-wide text-gray-400">
                                        <th class="px-4 py-2 font-medium">Gericht</th>
                                        <th class="px-4 py-2 font-medium">Kategorie</th>
                                        <th class="px-4 py-2 text-center font-medium">Portionen</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50">
                                    @foreach ($menuByDish as $m)
                                        <tr>
                                            <td class="px-4 py-2 font-medium text-gray-800">{{ $m['dish']?->name ?? '—' }}</td>
                                            <td class="px-4 py-2">
                                                <span class="inline-flex items-center gap-1 text-gray-600">
                                                    @if ($m['color'])
                                                        <span class="inline-block h-2.5 w-2.5 rounded-full" style="background-color: {{ $m['color'] }};"></span>
                                                    @endif
                                                    {{ $m['category'] }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-2 text-center text-lg font-semibold text-gray-800">{{ $m['ordered'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="border-t border-gray-200 bg-gray-50">
                                        <td class="px-4 py-2 font-semibold text-gray-700" colspan="2">Portionen gesamt (Menü)</td>
                                        <td class="px-4 py-2 text-center text-lg font-bold text-gray-900">{{ collect($menuByDish)->sum('ordered') }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    @endif
                </div>

                {{-- OGS-Menge --}}
                <div class="mt-4 rounded-xl border border-gray-200 bg-white p-4">
                    <div class="flex flex-wrap items-center gap-x-6 gap-y-1 text-sm">
                        <span class="font-semibold text-gray-800">OGS-Essen</span>
                        <span class="text-gray-600">Portionen heute: <strong class="text-lg text-gray-900">{{ $ogs['attending'] }}</strong></span>
                        @if ($ogsPrice > 0)
                            <span class="text-gray-400">Fixpreis {{ number_format($ogsPrice, 2, ',', '.') }} €</span>
                        @endif
                    </div>
                </div>

                <p class="mt-3 text-xs text-gray-400">
                    Die Portionen ergeben sich aus den Vorbestellungen nach Bestellschluss – Grundlage für Einkauf und „wie viel muss ich kochen".
                    Spontane Abholungen sind hier nicht eingerechnet (der Vorrat dafür bleibt küchenintern).
                </p>
            @endif
        @endif
    </div>
</x-app-layout>
