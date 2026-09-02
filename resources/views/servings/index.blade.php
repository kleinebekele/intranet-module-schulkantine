<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2">
            <x-module-icon name="restaurant" class="text-2xl text-indigo-600" />
            <h1 class="text-xl font-semibold text-gray-800">Ausgabe – Übersicht</h1>
        </div>
    </x-slot>

    <div class="max-w-full">
        @if (! $season)
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Es ist keine Saison als „aktiv" markiert – lege zuerst unter „Saisons &amp; Kalender" eine aktive Saison an.
            </div>
        @else
            {{-- Tages-Navigation --}}
            <div class="mb-4 flex items-center justify-between gap-4">
                <div>
                    @if ($prevDate)
                        <a href="{{ route('module.schulkantine.servings.index', ['date' => $prevDate]) }}"
                           class="inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">‹ Vorheriger Tag</a>
                    @endif
                </div>
                <div class="text-center">
                    <div class="text-sm font-semibold text-gray-800">{{ ucfirst($date->isoFormat('dddd')) }}, {{ $date->format('d.m.Y') }}</div>
                    <div class="text-xs text-gray-400">Saison „{{ $season->name }}"</div>
                </div>
                <div>
                    @if ($nextDate)
                        <a href="{{ route('module.schulkantine.servings.index', ['date' => $nextDate]) }}"
                           class="inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Nächster Tag ›</a>
                    @endif
                </div>
            </div>

            @if (! $open)
                <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-6 text-center text-sm text-amber-700">
                    🔒 Die Kantine hat an diesem Tag nicht geöffnet ({{ $closedReason }}).
                </div>
            @else
                <div x-data="{ tab: (() => { try { return localStorage.getItem('kantine_ausgabe_tab') || 'overview' } catch(e){ return 'overview' } })() }"
                     x-init="$watch('tab', v => { try { localStorage.setItem('kantine_ausgabe_tab', v) } catch(e){} })">

                    {{-- Tab-Umschalter --}}
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <div class="inline-flex rounded-lg border border-gray-200 bg-white p-0.5 text-sm">
                            <button type="button" @click="tab = 'overview'"
                                    :class="tab === 'overview' ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-50'"
                                    class="rounded-md px-3 py-1.5 font-medium">Ausgabe Übersicht</button>
                            <button type="button" @click="tab = 'details'"
                                    :class="tab === 'details' ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-50'"
                                    class="rounded-md px-3 py-1.5 font-medium">Details</button>
                        </div>
                        <div class="flex items-center gap-4">
                            <a x-show="tab === 'overview'" href="{{ route('module.schulkantine.servings.mengen.pdf', ['date' => $date->toDateString()]) }}"
                               class="inline-flex items-center gap-1 text-sm font-medium text-indigo-600 hover:text-indigo-800">
                                <x-module-icon name="download" class="text-base" /> Mengen-PDF
                            </a>
                            <a x-show="tab === 'details'" x-cloak href="{{ route('module.schulkantine.servings.ogs.pdf', ['date' => $date->toDateString()]) }}"
                               class="inline-flex items-center gap-1 text-sm font-medium text-indigo-600 hover:text-indigo-800">
                                <x-module-icon name="download" class="text-base" /> OGS-Sammelliste-PDF
                            </a>
                        </div>
                    </div>

                    {{-- ============================ TAB 1: ÜBERSICHT ============================ --}}
                    <div x-show="tab === 'overview'">
                        {{-- No-Shows: bestellt, aber nicht abgeholt. --}}
                        <div class="mb-4">
                            <div x-data="{ open: false }" class="overflow-hidden rounded-xl border border-amber-200 bg-white">
                                <button type="button" @click="open = ! open"
                                        class="flex w-full items-center justify-between px-4 py-3 text-left hover:bg-amber-50">
                                    <span class="flex items-center gap-2 text-sm font-semibold text-amber-800">
                                        <x-module-icon name="search" class="text-base" />
                                        Bestellt, aber nicht abgeholt
                                        <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800">{{ $noShowCount }}</span>
                                    </span>
                                    @if ($noShowCount > 0)
                                        <span class="text-sm text-amber-600" x-text="open ? '▲ einklappen' : '▼ Namen anzeigen'"></span>
                                    @endif
                                </button>
                                @if ($noShowCount > 0)
                                    <div x-show="open" x-cloak class="border-t border-amber-100">
                                        <ul class="divide-y divide-gray-100">
                                            @foreach ($noShowGroups as $name => $dishes)
                                                <li class="flex items-start justify-between gap-3 px-4 py-2 text-sm">
                                                    <span class="font-medium text-gray-800">{{ $name }}</span>
                                                    <span class="text-right text-gray-500">{{ implode(', ', array_filter($dishes)) }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- Mengen je Gericht --}}
                        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
                            <div class="border-b border-gray-100 bg-gray-50 px-4 py-2 text-sm font-semibold text-gray-700">Mengen je Gericht</div>
                            @if (empty($menuByDish))
                                <p class="px-4 py-6 text-center text-sm text-gray-500">Für diesen Tag liegen keine Menü-Bestellungen vor.</p>
                            @else
                                <div class="overflow-x-auto">
                                    <table class="min-w-full text-sm">
                                        <thead>
                                            <tr class="border-b border-gray-100 text-left text-xs uppercase tracking-wide text-gray-400">
                                                <th class="px-4 py-2 font-medium">Gericht</th>
                                                <th class="px-3 py-2 font-medium">Kategorie</th>
                                                <th class="px-3 py-2 text-center font-medium">Bestellt</th>
                                                <th class="px-3 py-2 text-center font-medium">Ausgegeben</th>
                                                <th class="px-3 py-2 text-center font-medium">Spontan</th>
                                                <th class="px-3 py-2 text-center font-medium">Offen</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-50">
                                            @foreach ($menuByDish as $m)
                                                <tr>
                                                    <td class="px-4 py-2 font-medium text-gray-800">{{ $m['dish']?->name ?? '—' }}</td>
                                                    <td class="px-3 py-2">
                                                        <span class="inline-flex items-center gap-1 text-gray-600">
                                                            @if ($m['color'])<span class="inline-block h-2.5 w-2.5 rounded-full" style="background-color: {{ $m['color'] }};"></span>@endif
                                                            {{ $m['category'] }}
                                                        </span>
                                                    </td>
                                                    <td class="px-3 py-2 text-center font-semibold text-gray-800">{{ $m['ordered'] }}</td>
                                                    <td class="px-3 py-2 text-center text-green-700">{{ $m['served'] }}</td>
                                                    <td class="px-3 py-2 text-center text-indigo-600">{{ $m['spontaneous'] }}</td>
                                                    <td class="px-3 py-2 text-center {{ $m['openNoShow'] > 0 ? 'font-semibold text-amber-600' : 'text-gray-300' }}">{{ $m['openNoShow'] }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                        <tfoot>
                                            <tr class="border-t border-gray-200 bg-gray-50">
                                                <td class="px-4 py-2 font-semibold text-gray-700" colspan="2">Summe</td>
                                                <td class="px-3 py-2 text-center font-bold text-gray-900">{{ collect($menuByDish)->sum('ordered') }}</td>
                                                <td class="px-3 py-2 text-center font-bold text-green-700">{{ collect($menuByDish)->sum('served') }}</td>
                                                <td class="px-3 py-2 text-center font-bold text-indigo-600">{{ collect($menuByDish)->sum('spontaneous') }}</td>
                                                <td class="px-3 py-2 text-center font-bold text-amber-600">{{ collect($menuByDish)->sum('openNoShow') }}</td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            @endif
                        </div>

                        {{-- OGS-Menge (Info) --}}
                        <div class="mt-3 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm">
                            <span class="font-semibold text-gray-800">OGS-Essen</span>
                            <span class="ml-3 text-gray-600">heute: <strong>{{ $ogsQuant['attending'] }}</strong></span>
                            <span class="ml-3 text-gray-400">ausgegeben: {{ $ogsQuant['served'] }}</span>
                        </div>

                        <p class="mt-3 text-xs text-gray-400">
                            „Offen" = bestellt, aber noch nicht ausgegeben. Ausgegeben/abgehakt wird am <strong>Ausgabe-Terminal</strong>.
                        </p>
                    </div>

                    {{-- ============================ TAB 2: DETAILS ============================ --}}
                    <div x-show="tab === 'details'" x-cloak class="space-y-6">
                        {{-- Tagesmenü: jeder Esser mit seiner Bestellung --}}
                        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
                            <div class="flex items-center justify-between border-b border-gray-100 bg-gray-50 px-4 py-2">
                                <span class="text-sm font-semibold text-gray-700">Tagesmenü</span>
                                <span class="rounded-full bg-gray-200 px-2.5 py-0.5 text-sm font-semibold text-gray-700">{{ count($menuRows) }}</span>
                            </div>
                            @if (empty($menuRows))
                                <p class="px-4 py-6 text-center text-sm text-gray-500">Für diesen Tag liegen keine Menü-Bestellungen vor.</p>
                            @else
                                <div class="divide-y divide-gray-100">
                                    @foreach ($menuRows as $row)
                                        <div class="px-4 py-3">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="font-semibold text-gray-800">{{ $row['user']->name }}</span>
                                                @if ($row['group'])
                                                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500">{{ $row['group'] }}</span>
                                                @endif
                                                @if ($row['warn'])
                                                    <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700">⚠️ Verträglichkeiten prüfen</span>
                                                @endif
                                            </div>

                                            @if ($row['dishes']->isNotEmpty())
                                                <div class="mt-1 flex flex-wrap gap-1.5">
                                                    @foreach ($row['dishes'] as $d)
                                                        <span class="inline-flex flex-col gap-0.5 rounded-md border px-2 py-0.5 text-sm
                                                                     {{ ($d['allergenHits'] || $d['dietHits']) ? 'border-red-300 bg-red-50 text-red-800' : 'border-gray-200 bg-gray-50 text-gray-700' }}">
                                                            <span class="inline-flex items-center gap-1">
                                                                {{ $d['dish']?->name ?? '—' }}
                                                                @if ($d['allergenHits'] || $d['dietHits'])
                                                                    <span class="text-xs font-medium">⚠️ {{ implode(', ', array_merge($d['allergenHits'], $d['dietHits'])) }}</span>
                                                                @endif
                                                            </span>
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @endif

                                            @if ($row['allergens'] || $row['diets'])
                                                <div class="mt-1 text-xs text-gray-400">
                                                    @if ($row['allergens']) Allergien: {{ implode(', ', $row['allergens']) }}. @endif
                                                    @if ($row['diets']) Diäten: {{ implode(', ', $row['diets']) }}. @endif
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        {{-- OGS: Sammelliste der heute essenden Kinder --}}
                        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
                            <div class="flex items-center justify-between border-b border-gray-100 bg-gray-50 px-4 py-2">
                                <span class="text-sm font-semibold text-gray-700">OGS-Sammelliste</span>
                                <span class="rounded-full bg-indigo-100 px-2.5 py-0.5 text-sm font-semibold text-indigo-800">{{ $ogsRows->count() }}</span>
                            </div>
                            @if ($ogsRows->isEmpty())
                                <p class="px-4 py-6 text-center text-sm text-gray-500">Heute isst kein OGS-Kind (kein aktives Abo, keine Bestellung).</p>
                            @else
                                <ol class="divide-y divide-gray-50 text-sm">
                                    @foreach ($ogsRows as $i => $e)
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

                        <p class="text-xs text-gray-400">
                            Die OGS-Sammelliste zeigt, wer heute ein OGS-Essen bekommt (Abo minus Abbestellungen bzw. Einzelbestellung).
                            Zum Drucken für den OGS-Betreuer oben das <strong>OGS-Sammelliste-PDF</strong> laden.
                        </p>
                    </div>
                </div>
            @endif
        @endif
    </div>
</x-app-layout>
