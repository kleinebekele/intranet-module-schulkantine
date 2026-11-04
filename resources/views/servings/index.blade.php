<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2">
            <x-module-icon name="restaurant" class="text-2xl text-indigo-600" />
            <h1 class="text-xl font-semibold text-gray-800">Ausgabe – Übersicht</h1>
        </div>
    </x-slot>

    @php
        $darfOgs = \Intranet\Modules\Schulkantine\Support\Access::canViewOgsList(auth()->user());
        $darfBetrieb = \Intranet\Modules\Schulkantine\Support\Access::canViewServings(auth()->user());
    @endphp

    <div class="max-w-full">
        @if (! $season)
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Es ist keine Saison als „aktiv" markiert – lege zuerst unter „Saisons &amp; Kalender" eine aktive Saison an.
            </div>
        @else
            {{-- Wochen-Navigation: die ganze KW, ein Öffnungstag unter dem anderen. --}}
            <div class="mb-4 flex items-center justify-between gap-4">
                <div>
                    @if ($prevWeek)
                        <a href="{{ route('module.schulkantine.servings.index', ['date' => $prevWeek]) }}"
                           class="inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">‹ Vorherige Woche</a>
                    @endif
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-gray-800">KW {{ $monday->isoWeek() }}</div>
                    <div class="text-sm font-semibold text-gray-600">{{ $monday->format('d.m.') }} – {{ $monday->copy()->addDays(6)->format('d.m.Y') }}</div>
                    <div class="mt-1 flex items-center justify-center gap-2 text-xs text-gray-400">
                        Saison „{{ $season->name }}"
                        @unless ($thisWeek)
                            {{-- Ohne Datum springt die Seite auf heute bzw. den nächsten Öffnungstag. --}}
                            <a href="{{ route('module.schulkantine.servings.index') }}"
                               class="rounded-md bg-indigo-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-indigo-700">Heute</a>
                        @endunless
                    </div>
                </div>
                <div>
                    @if ($nextWeek)
                        <a href="{{ route('module.schulkantine.servings.index', ['date' => $nextWeek]) }}"
                           class="inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Nächste Woche ›</a>
                    @endif
                </div>
            </div>

            @if (empty($days))
                <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-6 text-center text-sm text-amber-700">
                    🔒 In dieser Woche hat die Mensa nicht geöffnet.
                </div>
            @else
                <div x-data="{ tab: (() => { try { return localStorage.getItem('kantine_ausgabe_tab') || 'overview' } catch(e){ return 'overview' } })() }"
                     x-init="$watch('tab', v => { try { localStorage.setItem('kantine_ausgabe_tab', v) } catch(e){} });
                             $nextTick(() => { const h = document.getElementById(location.hash.slice(1)) || document.querySelector('[data-heute]'); if (h) h.scrollIntoView({ block: 'start' }); })">

                    {{-- Tab-Umschalter (gilt für alle Tage der Woche) --}}
                    <div class="sticky top-0 z-10 mb-4 flex flex-wrap items-center justify-between gap-3 bg-gray-100/90 py-2 backdrop-blur">
                        <div class="inline-flex rounded-lg border border-gray-200 bg-white p-0.5 text-sm">
                            <button type="button" @click="tab = 'overview'"
                                    :class="tab === 'overview' ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-50'"
                                    class="rounded-md px-3 py-1.5 font-medium">Ausgabe Übersicht</button>
                            <button type="button" @click="tab = 'details'"
                                    :class="tab === 'details' ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-50'"
                                    class="rounded-md px-3 py-1.5 font-medium">Details</button>
                        </div>
                        {{-- Sprungmarken zu den Tagen --}}
                        <div class="flex flex-wrap items-center gap-1.5">
                            @foreach ($days as $tag)
                                <a href="#tag-{{ $tag['date']->toDateString() }}"
                                   class="rounded-md px-2.5 py-1 text-sm font-semibold {{ $tag['isToday'] ? 'bg-indigo-600 text-white' : 'bg-white text-gray-600 hover:bg-indigo-50' }}">
                                    {{ ucfirst($tag['date']->isoFormat('dd D.M.')) }}
                                </a>
                            @endforeach
                        </div>
                    </div>

                    @error('ogs')
                        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700">{{ $message }}</div>
                    @enderror

                    <div class="space-y-8">
                    @foreach ($days as $tag)
                        @php
                            $d = $tag['date'];
                            $noShowCount = count($tag['noShowGroups']);
                        @endphp
                        <section id="tag-{{ $d->toDateString() }}" @if ($tag['isToday']) data-heute @endif
                                 class="scroll-mt-16 rounded-2xl p-4 {{ $tag['isToday'] ? 'border-4 border-indigo-500 bg-indigo-50/60 shadow-lg' : 'border border-gray-200 bg-white/60' }}">
                            {{-- Tageskopf: Datum groß, heute deutlich markiert, PDFs des Tages --}}
                            <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                                <div class="flex items-center gap-3">
                                    <h2 class="text-2xl font-bold {{ $tag['isToday'] ? 'text-indigo-700' : ($tag['begonnen'] ? 'text-gray-500' : 'text-gray-800') }}">
                                        {{ ucfirst($d->isoFormat('dddd')) }}, {{ $d->format('d.m.Y') }}
                                    </h2>
                                    @if ($tag['isToday'])
                                        <span class="rounded-full bg-indigo-600 px-3 py-1 text-sm font-bold uppercase tracking-wide text-white">Heute</span>
                                    @endif
                                </div>
                                <div class="flex items-center gap-4">
                                    <a href="{{ route('module.schulkantine.servings.protokoll', ['date' => $d->toDateString()]) }}"
                                       class="inline-flex items-center gap-1 rounded-md bg-indigo-600 px-2.5 py-1 text-sm font-semibold text-white hover:bg-indigo-700">
                                        <x-module-icon name="search" class="text-base" /> Tagesprotokoll
                                    </a>
                                    @if ($darfBetrieb)
                                        <a href="{{ route('module.schulkantine.servings.besteller.pdf', ['date' => $d->toDateString()]) }}"
                                           class="inline-flex items-center gap-1 text-sm font-medium text-indigo-600 hover:text-indigo-800">
                                            <x-module-icon name="download" class="text-base" /> Besteller-Liste-PDF
                                        </a>
                                    @endif
                                    <a href="{{ route('module.schulkantine.servings.ogs.pdf', ['date' => $d->toDateString()]) }}"
                                       class="inline-flex items-center gap-1 text-sm font-medium text-indigo-600 hover:text-indigo-800">
                                        <x-module-icon name="download" class="text-base" /> OGS-Sammelliste-PDF
                                    </a>
                                </div>
                            </div>

                            {{-- ======================== TAB 1: ÜBERSICHT ======================== --}}
                            <div x-show="tab === 'overview'">
                                {{-- No-Shows: bestellt, aber nicht abgeholt – erst ab dem Ausgabetag. --}}
                                @if ($tag['begonnen'])
                                    <div class="mb-4">
                                        <div x-data="{ open: false }" class="overflow-hidden rounded-xl border border-amber-200 bg-white">
                                            <button type="button" @click="open = ! open"
                                                    class="flex w-full items-center justify-between px-4 py-3 text-left hover:bg-amber-50">
                                                <span class="flex items-center gap-2 text-sm font-semibold text-amber-800">
                                                    <x-module-icon name="search" class="text-base" />
                                                    {{ $tag['isToday'] ? 'Bestellt, noch nicht abgeholt' : 'Bestellt, aber nicht abgeholt' }}
                                                    <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800">{{ $noShowCount }}</span>
                                                </span>
                                                @if ($noShowCount > 0)
                                                    <span class="text-sm text-amber-600" x-text="open ? '▲ einklappen' : '▼ Namen anzeigen'"></span>
                                                @endif
                                            </button>
                                            @if ($noShowCount > 0)
                                                <div x-show="open" x-cloak class="border-t border-amber-100">
                                                    <ul class="divide-y divide-gray-100">
                                                        @foreach ($tag['noShowGroups'] as $ns)
                                                            <li class="flex items-start gap-3 px-4 py-2 text-sm">
                                                                <span class="w-20 shrink-0 text-xs leading-5 text-gray-500">{{ $ns['klasse'] ?? '–' }}</span>
                                                                <span class="font-medium text-gray-800">{{ $ns['sortName'] }}</span>
                                                                <span class="ml-auto text-right text-gray-500">{{ implode(', ', array_filter($ns['dishes'])) }}</span>
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                @endif

                                {{-- Mengen je Menü und Gericht --}}
                                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
                                    <div class="border-b border-gray-100 bg-gray-50 px-4 py-2 text-sm font-semibold text-gray-700">Mengen je Menü und Gericht</div>
                                    {{-- OGS-Essen als eigene Zeile: bestellt = angemeldet, spontan = nachgetragen,
                                         offen gibt es nicht (OGS wird an der Ausgabe nicht abgehakt). --}}
                                    @php
                                        $ogsSpontan = $tag['ogsRows']->where('nachgetragen', true)->count();
                                        $ogsZeile = $tag['ogsQuant']['attending'] > 0
                                            ? ['ordered' => $tag['ogsQuant']['attending'] - $ogsSpontan, 'served' => $tag['ogsQuant']['served'], 'spontaneous' => $ogsSpontan]
                                            : null;
                                    @endphp
                                    @if (empty($tag['menuByDish']) && ! $ogsZeile)
                                        <p class="px-4 py-6 text-center text-sm text-gray-500">Für diesen Tag ist nichts im Speiseplan.</p>
                                    @else
                                        <div class="overflow-x-auto">
                                            <table class="min-w-full text-sm">
                                                <thead>
                                                    <tr class="border-b border-gray-100 text-left text-xs uppercase tracking-wide text-gray-400">
                                                        <th class="px-4 py-2 font-medium">Menü / Gericht</th>
                                                        <th class="px-3 py-2 font-medium">Kategorie</th>
                                                        <th class="px-3 py-2 text-center font-medium">Bestellt</th>
                                                        <th class="px-3 py-2 text-center font-medium">Ausgegeben</th>
                                                        <th class="px-3 py-2 text-center font-medium">Spontan</th>
                                                        <th class="px-3 py-2 text-center font-medium">Offen</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-gray-50">
                                                    @foreach ($tag['menuByDish'] as $m)
                                                        {{-- Nichts bestellt, ausgegeben oder spontan – Zeile weglassen. --}}
                                                        @continue(! $m['ordered'] && ! $m['served'] && ! $m['spontaneous'])
                                                        <tr>
                                                            <td class="px-4 py-2 font-medium text-gray-800">
                                                                {{ $m['name'] }}
                                                                @if ($m['inhalt'])
                                                                    <div class="text-xs font-normal text-gray-400">{{ implode(', ', $m['inhalt']) }}</div>
                                                                @endif
                                                            </td>
                                                            <td class="px-3 py-2">
                                                                <span class="inline-flex items-center gap-1 text-gray-600">
                                                                    @if ($m['color'])<span class="inline-block h-2.5 w-2.5 rounded-full" style="background-color: {{ $m['color'] }};"></span>@endif
                                                                    {{ $m['category'] }}
                                                                </span>
                                                            </td>
                                                            <td class="px-3 py-2 text-center font-semibold text-gray-800">{{ $m['ordered'] }}</td>
                                                            <td class="px-3 py-2 text-center text-green-700">{{ $m['served'] }}</td>
                                                            {{-- Menüs gibt es nur vorbestellt, spontan nur Einzelgerichte. --}}
                                                            <td class="px-3 py-2 text-center {{ $m['spontaneous'] ? 'text-indigo-600' : 'text-gray-300' }}">{{ $m['is_menu'] ? '–' : $m['spontaneous'] }}</td>
                                                            <td class="px-3 py-2 text-center {{ $m['openNoShow'] > 0 ? 'font-semibold text-amber-600' : 'text-gray-300' }}">{{ $m['openNoShow'] }}</td>
                                                        </tr>
                                                    @endforeach
                                                    @if ($ogsZeile)
                                                        <tr>
                                                            <td class="px-4 py-2 font-medium text-gray-800">OGS-Essen</td>
                                                            <td class="px-3 py-2"></td>
                                                            <td class="px-3 py-2 text-center font-semibold text-gray-800">{{ $ogsZeile['ordered'] }}</td>
                                                            <td class="px-3 py-2 text-center text-green-700">{{ $ogsZeile['served'] }}</td>
                                                            <td class="px-3 py-2 text-center {{ $ogsZeile['spontaneous'] ? 'text-indigo-600' : 'text-gray-300' }}">{{ $ogsZeile['spontaneous'] }}</td>
                                                            <td class="px-3 py-2 text-center text-gray-300">–</td>
                                                        </tr>
                                                    @endif
                                                </tbody>
                                                <tfoot>
                                                    <tr class="border-t border-gray-200 bg-gray-50">
                                                        <td class="px-4 py-2 font-semibold text-gray-700" colspan="2">Summe</td>
                                                        <td class="px-3 py-2 text-center font-bold text-gray-900">{{ collect($tag['menuByDish'])->sum('ordered') + ($ogsZeile['ordered'] ?? 0) }}</td>
                                                        <td class="px-3 py-2 text-center font-bold text-green-700">{{ collect($tag['menuByDish'])->sum('served') + ($ogsZeile['served'] ?? 0) }}</td>
                                                        <td class="px-3 py-2 text-center font-bold text-indigo-600">{{ collect($tag['menuByDish'])->sum('spontaneous') + ($ogsZeile['spontaneous'] ?? 0) }}</td>
                                                        <td class="px-3 py-2 text-center font-bold text-amber-600">{{ collect($tag['menuByDish'])->sum('openNoShow') }}</td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    @endif
                                </div>

                            </div>

                            {{-- ========================= TAB 2: DETAILS ========================= --}}
                            <div x-show="tab === 'details'" x-cloak class="space-y-6">
                                {{-- Tagesmenü: jeder Esser mit seiner Bestellung --}}
                                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
                                    <div class="flex items-center justify-between border-b border-gray-100 bg-gray-50 px-4 py-2">
                                        <span class="text-sm font-semibold text-gray-700">Tagesmenü</span>
                                        <span class="rounded-full bg-gray-200 px-2.5 py-0.5 text-sm font-semibold text-gray-700">{{ count($tag['menuRows']) }}</span>
                                    </div>
                                    @if (empty($tag['menuRows']))
                                        <p class="px-4 py-6 text-center text-sm text-gray-500">Für diesen Tag liegen keine Menü-Bestellungen vor.</p>
                                    @else
                                        <div class="divide-y divide-gray-100">
                                            @foreach ($tag['menuRows'] as $row)
                                                <div class="px-4 py-3">
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        <span class="w-20 shrink-0 text-xs text-gray-500">{{ $row['klasse'] ?? '–' }}</span>
                                                        <span class="font-semibold text-gray-800">{{ $row['sortName'] }}</span>
                                                        @if ($row['warn'])
                                                            <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700">⚠️ Verträglichkeiten prüfen</span>
                                                        @endif
                                                    </div>

                                                    @if ($row['dishes']->isNotEmpty())
                                                        <div class="mt-1 flex flex-wrap gap-1.5">
                                                            @foreach ($row['dishes'] as $p)
                                                                <span class="inline-flex flex-col gap-0.5 rounded-md border px-2 py-0.5 text-sm
                                                                             {{ ($p['allergenHits'] || $p['dietHits']) ? 'border-red-300 bg-red-50 text-red-800' : 'border-gray-200 bg-gray-50 text-gray-700' }}">
                                                                    <span class="inline-flex items-center gap-1" @if ($p['inhalt']) title="{{ implode(', ', $p['inhalt']) }}" @endif>
                                                                        {{ $p['name'] }}
                                                                        @if ($p['allergenHits'] || $p['dietHits'])
                                                                            <span class="text-xs font-medium">⚠️ {{ implode(', ', array_merge($p['allergenHits'], $p['dietHits'])) }}</span>
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

                                {{-- OGS: Sammelliste der an dem Tag angemeldeten Kinder --}}
                                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
                                    <div class="flex items-center justify-between border-b border-gray-100 bg-gray-50 px-4 py-2">
                                        <span class="text-sm font-semibold text-gray-700">OGS-Sammelliste</span>
                                        <div class="flex items-center gap-3">
                                            @if ($darfOgs)
                                                <button type="button" @click="$dispatch('ogs-nachtragen', @js($d->toDateString()))"
                                                        class="rounded-md bg-indigo-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-indigo-700">+ Kind nachtragen</button>
                                            @endif
                                            <span class="rounded-full bg-indigo-100 px-2.5 py-0.5 text-sm font-semibold text-indigo-800">{{ $tag['ogsRows']->count() }}</span>
                                        </div>
                                    </div>
                                    {{-- Nachtragen: Kind kam ohne Anmeldung zum Essen (auch rückwirkend) – danach
                                         steht es auf der Liste und der Tag wird abgerechnet. --}}
                                    @if ($darfOgs)
                                        <div x-data="{ offen: false, q: '', treffer: [], laedt: false,
                                                       async suchen() {
                                                           if (this.q.trim().length < 2) { this.treffer = []; return; }
                                                           this.laedt = true;
                                                           const r = await fetch(@js(route('module.schulkantine.servings.ogs.search')), { method: 'POST',
                                                               headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @js(csrf_token()) },
                                                               body: JSON.stringify({ q: this.q }) });
                                                           this.treffer = (await r.json()).results || [];
                                                           this.laedt = false;
                                                       } }"
                                             @ogs-nachtragen.window="if ($event.detail === @js($d->toDateString())) { offen = ! offen; $nextTick(() => $refs.feld && $refs.feld.focus()) }"
                                             x-show="offen" x-cloak class="border-b border-indigo-100 bg-indigo-50/50 px-4 py-3">
                                            <div class="text-sm font-medium text-gray-700">Kind nachtragen für {{ $d->isoFormat('dd, D.M.Y') }}</div>
                                            <input type="text" x-ref="feld" x-model="q" @input.debounce.250ms="suchen()" placeholder="Name eingeben …"
                                                   class="mt-2 w-full max-w-md rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            <div class="mt-2 flex flex-wrap gap-2">
                                                <template x-for="t in treffer" :key="t.id">
                                                    <form method="POST" action="{{ route('module.schulkantine.servings.ogs.add') }}">
                                                        @csrf
                                                        <input type="hidden" name="date" value="{{ $d->toDateString() }}">
                                                        <input type="hidden" name="eater_id" :value="t.id">
                                                        <button type="submit" class="rounded-lg border border-indigo-200 bg-white px-3 py-1.5 text-sm hover:border-indigo-500 hover:bg-indigo-50">
                                                            <span class="font-medium text-gray-800" x-text="t.name"></span>
                                                            <span x-show="t.klasse" class="ml-1 text-xs text-gray-500" x-text="t.klasse"></span>
                                                        </button>
                                                    </form>
                                                </template>
                                                <span x-show="q.trim().length >= 2 && ! laedt && ! treffer.length" class="text-sm text-gray-400">Kein OGS-Kind gefunden.</span>
                                            </div>
                                        </div>
                                    @endif
                                    @if ($tag['ogsRows']->isEmpty())
                                        <p class="px-4 py-6 text-center text-sm text-gray-500">An diesem Tag isst kein OGS-Kind (kein aktives Abo, keine Bestellung).</p>
                                    @else
                                        <ol class="divide-y divide-gray-50 text-sm">
                                            @foreach ($tag['ogsRows'] as $i => $e)
                                                <li class="flex items-center gap-3 px-4 py-2.5">
                                                    <span class="w-6 shrink-0 text-right text-xs text-gray-400">{{ $i + 1 }}.</span>
                                                    <span class="w-12 shrink-0 text-xs text-gray-500">{{ $e['klasse'] ?? '–' }}</span>
                                                    <span class="font-medium text-gray-800">{{ $e['sortName'] }}</span>
                                                    @if ($e['allergens'] || $e['diets'])
                                                        <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700"
                                                              title="{{ trim(($e['allergens'] ? 'Allergien: '.implode(', ', $e['allergens']).'. ' : '').($e['diets'] ? 'Diäten: '.implode(', ', $e['diets']).'.' : '')) }}">
                                                            ⚠️ Verträglichkeiten
                                                        </span>
                                                    @endif
                                                    @if ($e['nachgetragen'])
                                                        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">nachgetragen</span>
                                                        @if ($darfOgs)
                                                            <form method="POST" action="{{ route('module.schulkantine.servings.ogs.remove') }}"
                                                                  data-bestaetigen="{{ $e['sortName'] }} am {{ $d->format('d.m.Y') }} wieder von der OGS-Liste nehmen?"
                                                                  data-titel="Nachtrag entfernen" data-knopf="Entfernen">
                                                                @csrf
                                                                <input type="hidden" name="date" value="{{ $d->toDateString() }}">
                                                                <input type="hidden" name="eater_id" value="{{ $e['user']->id }}">
                                                                <button type="submit" class="text-xs text-gray-400 hover:text-red-600">entfernen</button>
                                                            </form>
                                                        @endif
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ol>
                                    @endif
                                </div>
                            </div>
                        </section>
                    @endforeach
                    </div>

                    <p class="mt-4 text-xs text-gray-400">
                        „Offen" = bestellt, aber noch nicht ausgegeben. Ausgegeben/abgehakt wird am <strong>Ausgabe-Terminal</strong>.
                        Die OGS-Sammelliste zeigt, wer an dem Tag angemeldet ist (Abo minus Abbestellungen bzw. Einzelbestellung).
                    </p>
                </div>
            @endif
        @endif
    </div>
</x-app-layout>
