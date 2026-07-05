<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2">
            <x-module-icon name="restaurant" class="text-2xl text-indigo-600" />
            <h1 class="text-xl font-semibold text-gray-800">OGS-Sammelliste</h1>
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
                        <a href="{{ route('module.schulkantine.servings.ogs', ['date' => $prevDate]) }}"
                           class="inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">‹ Vorheriger Tag</a>
                    @endif
                </div>
                <div class="text-center">
                    <div class="text-sm font-semibold text-gray-800">{{ $date->isoFormat('dddd') }}, {{ $date->format('d.m.Y') }}</div>
                    <div class="text-xs text-gray-400">Saison „{{ $season->name }}"</div>
                </div>
                <div>
                    @if ($nextDate)
                        <a href="{{ route('module.schulkantine.servings.ogs', ['date' => $nextDate]) }}"
                           class="inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Nächster Tag ›</a>
                    @endif
                </div>
            </div>

            @if (! $open)
                <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-6 text-center text-sm text-amber-700">
                    🔒 Die Kantine hat an diesem Tag nicht geöffnet ({{ $closedReason }}).
                </div>
            @else
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
                    <div class="flex items-center justify-between border-b border-gray-100 bg-gray-50 px-4 py-2">
                        <span class="text-sm font-semibold text-gray-700">Heute essende OGS-Kinder</span>
                        <span class="rounded-full bg-indigo-100 px-2.5 py-0.5 text-sm font-semibold text-indigo-800">{{ $eaters->count() }}</span>
                    </div>

                    @if ($eaters->isEmpty())
                        <p class="px-4 py-6 text-center text-sm text-gray-500">Heute isst kein OGS-Kind.</p>
                    @else
                        <ol class="divide-y divide-gray-50 text-sm">
                            @foreach ($eaters as $i => $e)
                                <li class="flex items-center gap-3 px-4 py-2.5">
                                    <span class="w-6 shrink-0 text-right text-xs text-gray-400">{{ $i + 1 }}.</span>
                                    <span class="font-medium text-gray-800">{{ $e['user']->name }}</span>
                                    @if ($e['allergens'] || $e['diets'])
                                        <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700"
                                              title="{{ trim(($e['allergens'] ? 'Allergien: '.implode(', ', $e['allergens']).'. ' : '').($e['diets'] ? 'Diäten: '.implode(', ', $e['diets']).'.' : '')) }}">
                                            ⚠️ Verträglichkeiten
                                        </span>
                                    @endif
                                    @if ($e['served'])
                                        <span class="ml-auto text-xs font-medium text-green-700">✓ ausgegeben</span>
                                    @endif
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </div>

                <p class="mt-3 text-xs text-gray-400">
                    Diese Kinder haben heute ein OGS-Essen bestellt (Abo minus Abbestellungen bzw. Einzelbestellung).
                    Der OGS-Betreuer sammelt sie ein und bringt sie zur Kantine.
                </p>
            @endif
        @endif
    </div>
</x-app-layout>
