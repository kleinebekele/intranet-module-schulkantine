<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2">
            <x-module-icon name="restaurant" class="text-2xl text-indigo-600" />
            <h1 class="text-xl font-semibold text-gray-800">Buchungen aus Menü&amp;Serve übernehmen</h1>
        </div>
    </x-slot>

    @php
        $tage = collect($plan)->groupBy('tag')->sortKeys();
        $bereit = collect($plan)->where('status', 'bereit')->count();
        $farbe = fn ($s) => match ($s) {
            'bereit' => 'text-sky-700',
            'uebernommen', 'vorhanden' => 'text-green-700',
            'storniert', 'ogs' => 'text-gray-400',
            default => 'text-amber-700',
        };
    @endphp

    <div class="w-full space-y-5">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <form method="GET" action="{{ route('module.schulkantine.dishes.menueserve.buchungen') }}" class="flex items-end gap-2">
                <div>
                    <label for="ab" class="block text-xs font-medium text-gray-500">Buchungen ab</label>
                    <input id="ab" name="ab" type="date" value="{{ $ab->format('Y-m-d') }}" onchange="this.form.submit()"
                           class="mt-1 rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
            </form>
            <a href="{{ route('module.schulkantine.dishes.menueserve', ['ab' => $ab->format('Y-m-d')]) }}" class="text-sm text-gray-500 hover:text-gray-700">← zurück zur Menü-Zuordnung</a>
        </div>

        <p class="text-sm text-gray-500">
            Jede Buchung wird über die Linear-Nummer einer Person zugeordnet und als Bestellung für das Speiseplan-Menü
            des Tages angelegt (ohne Bestellfristen). Voraussetzung: das Menü ist zugeordnet und steht vollständig im Speiseplan.
            OGS-Kinder laufen bei uns über das Abo und werden übersprungen; nichts wird doppelt angelegt.
        </p>

        @if ($fehler)
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">Menü&amp;Serve ist nicht lesbar: {{ $fehler }}</div>
        @endif

        @if ($plan)
            {{-- Je Tag: wie viele Buchungen mit welchem Ergebnis --}}
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 text-left text-xs uppercase tracking-wide text-gray-400">
                                <th class="px-4 py-2 font-medium">Tag</th>
                                <th class="px-3 py-2 text-right font-medium">Buchungen</th>
                                @foreach ($statusText as $s => $text)
                                    <th class="px-3 py-2 text-right font-medium {{ $farbe($s) }}">{{ $text }}</th>
                                @endforeach
                                <th class="px-3 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($tage as $tag => $liste)
                                <tr>
                                    <td class="whitespace-nowrap px-4 py-2 font-medium text-gray-900">
                                        {{ \Illuminate\Support\Carbon::parse($tag)->locale('de')->isoFormat('dd DD.MM.YYYY') }}
                                        <span class="ml-1 text-xs font-normal text-gray-400">{{ $liste->pluck('titel')->filter()->unique()->implode(', ') }}</span>
                                    </td>
                                    <td class="px-3 py-2 text-right tabular-nums text-gray-700">{{ $liste->count() }}</td>
                                    @foreach ($statusText as $s => $text)
                                        @php $n = $liste->where('status', $s)->count(); @endphp
                                        <td class="px-3 py-2 text-right tabular-nums {{ $n ? $farbe($s) : 'text-gray-300' }}">{{ $n ?: '–' }}</td>
                                    @endforeach
                                    @php $tagBereit = $liste->where('status', 'bereit')->count(); @endphp
                                    <td class="px-3 py-2 text-right">
                                        @if ($tagBereit > 0)
                                            <form method="POST" action="{{ route('module.schulkantine.dishes.menueserve.buchungen.uebernehmen') }}"
                                                  onsubmit="return confirm(@js($tagBereit.' Buchungen vom '.\Illuminate\Support\Carbon::parse($tag)->format('d.m.Y').' als Bestellung anlegen?'))">
                                                @csrf
                                                <input type="hidden" name="ab" value="{{ $ab->format('Y-m-d') }}">
                                                <input type="hidden" name="tag" value="{{ $tag }}">
                                                <button type="submit" class="whitespace-nowrap rounded-md border border-sky-300 bg-white px-2 py-1 text-xs font-medium text-sky-800 hover:bg-sky-50">Tag übernehmen</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- OGS-Abgleich: Menü&Serve-Buchung gegen Abo + An-/Abmeldungen bei uns --}}
            @if ($ogs && $ogs['kinder'])
                @php
                    $abweichend = collect($ogs['kinder'])->filter(fn ($k) => $k['abweichungen']);
                    $ogsSumme = $abweichend->sum(fn ($k) => count($k['abweichungen']));
                @endphp
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-4 py-2">
                        <div class="text-sm font-semibold text-gray-700">
                            OGS-Abgleich – {{ count($ogs['kinder']) }} Kinder mit Menü&amp;Serve-Buchung,
                            {{ $abweichend->count() }} weichen an {{ $ogsSumme }} Tagen ab
                        </div>
                        @if ($ogsSumme > 0)
                            <form method="POST" action="{{ route('module.schulkantine.dishes.menueserve.buchungen.ogs') }}"
                                  onsubmit="return confirm(@js('Alle '.$ogsSumme.' abweichenden OGS-Tage an Menü&Serve angleichen (An-/Abmeldung je Tag)?'))">
                                @csrf
                                <input type="hidden" name="ab" value="{{ $ab->format('Y-m-d') }}">
                                <button type="submit" class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700">Alle angleichen</button>
                            </form>
                        @endif
                    </div>
                    <p class="px-4 py-2 text-xs text-gray-500">
                        <span class="font-semibold text-green-700">+</span> = in Menü&amp;Serve gebucht, isst bei uns nicht → wird angemeldet ·
                        <span class="font-semibold text-red-700">−</span> = isst bei uns laut Abo, in Menü&amp;Serve nicht gebucht → wird abgemeldet ·
                        <span class="text-gray-400">·</span> = stimmt überein.
                    </p>
                    @if ($ogsSumme > 0)
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-gray-100 text-left text-xs uppercase tracking-wide text-gray-400">
                                        <th class="px-4 py-2 font-medium">Kind</th>
                                        <th class="px-3 py-2 font-medium">bei uns</th>
                                        @foreach ($ogs['tage'] as $t)
                                            <th class="px-2 py-2 text-center font-medium">{{ \Illuminate\Support\Carbon::parse($t)->locale('de')->isoFormat('dd DD.MM.') }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($abweichend as $k)
                                        <tr>
                                            <td class="whitespace-nowrap px-4 py-1.5 font-medium text-gray-900">{{ $k['user']->name }}</td>
                                            <td class="whitespace-nowrap px-3 py-1.5 text-xs text-gray-500">{{ $k['abo'] }}</td>
                                            @foreach ($ogs['tage'] as $t)
                                                @php $a = $k['abweichungen'][$t] ?? null; @endphp
                                                <td class="px-2 py-1.5 text-center font-semibold {{ $a === 'anmelden' ? 'text-green-700' : ($a ? 'text-red-700' : 'text-gray-300') }}"
                                                    title="{{ $a === 'anmelden' ? 'wird angemeldet' : ($a ? 'wird abgemeldet' : 'stimmt') }}">{{ $a === 'anmelden' ? '+' : ($a ? '−' : '·') }}</td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="border-t border-gray-200 bg-gray-50">
                                        <td colspan="2" class="px-4 py-1.5 text-xs text-gray-500">je Tag</td>
                                        @foreach ($ogs['tage'] as $t)
                                            @php $nTag = $abweichend->filter(fn ($k) => isset($k['abweichungen'][$t]))->count(); @endphp
                                            <td class="px-2 py-1.5 text-center">
                                                @if ($nTag > 0)
                                                    <form method="POST" action="{{ route('module.schulkantine.dishes.menueserve.buchungen.ogs') }}"
                                                          onsubmit="return confirm(@js($nTag.' OGS-Kinder am '.\Illuminate\Support\Carbon::parse($t)->format('d.m.Y').' angleichen?'))">
                                                        @csrf
                                                        <input type="hidden" name="ab" value="{{ $ab->format('Y-m-d') }}">
                                                        <input type="hidden" name="tag" value="{{ $t }}">
                                                        <button type="submit" class="rounded-md border border-sky-300 bg-white px-1.5 py-0.5 text-xs font-medium text-sky-800 hover:bg-sky-50" title="Diesen Tag angleichen">{{ $nTag }}</button>
                                                    </form>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    @endif
                    @if ($ogs['ohneBuchung'])
                        <div class="border-t border-gray-100 px-4 py-2 text-xs text-gray-500">
                            Mit Abo bei uns, aber in Menü&amp;Serve im Zeitraum nichts gebucht (bleibt unverändert):
                            {{ collect($ogs['ohneBuchung'])->map(fn ($o) => $o['user']->name.' ('.$o['abo'].')')->implode(', ') }}
                        </div>
                    @endif
                </div>
            @endif

            {{-- Unbekannte Konten: wer ist das? --}}
            @if ($unbekannt)
                <div class="overflow-hidden rounded-xl border border-amber-200 bg-white">
                    <div class="border-b border-amber-100 bg-amber-50 px-4 py-2 text-sm font-medium text-amber-800">Bei uns unbekannt – {{ count($unbekannt) }} Konten</div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-100 text-left text-xs uppercase tracking-wide text-gray-400">
                                    <th class="px-4 py-2 font-medium">Konto in Menü&amp;Serve</th>
                                    <th class="px-3 py-2 font-medium">Linear</th>
                                    <th class="px-3 py-2 text-right font-medium">Buchungen</th>
                                    <th class="px-3 py-2 font-medium">Warum unbekannt</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($unbekannt as $u)
                                    <tr>
                                        <td class="px-4 py-2">
                                            <div class="font-medium text-gray-900">{{ $u['name'] !== '' ? $u['name'] : '(ohne Namen)' }}</div>
                                            @if ($u['kartenart'])<div class="text-xs text-gray-400">{{ $u['kartenart'] }}</div>@endif
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-2 text-gray-600">
                                            {{ $u['adrnr'] !== '' ? $u['adrnr'] : '–' }}@if ($u['linear']) · {{ $u['linear'] }}@endif
                                        </td>
                                        <td class="px-3 py-2 text-right tabular-nums text-gray-600"
                                            title="{{ collect($u['tage'])->map(fn ($t) => \Illuminate\Support\Carbon::parse($t)->format('d.m.'))->implode(', ') }}">{{ $u['buchungen'] }}</td>
                                        <td class="px-3 py-2 text-amber-700">{{ $u['hinweis'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            {{-- Was sonst nicht geht – damit man es nachziehen kann --}}
            @php $probleme = collect($plan)->whereIn('status', ['zuordnung', 'speiseplan']); @endphp
            @if ($probleme->isNotEmpty())
                <div class="overflow-hidden rounded-xl border border-amber-200 bg-white">
                    <div class="border-b border-amber-100 bg-amber-50 px-4 py-2 text-sm font-medium text-amber-800">Nicht übernehmbar – {{ $probleme->count() }} Buchungen</div>
                    <ul class="divide-y divide-gray-100 text-sm">
                        @foreach ($probleme->sortBy('tag') as $b)
                            <li class="flex flex-wrap gap-x-3 px-4 py-1.5">
                                <span class="w-24 text-gray-500">{{ \Illuminate\Support\Carbon::parse($b['tag'])->format('d.m.Y') }}</span>
                                <span class="w-56 font-medium text-gray-900">{{ $b['user']?->name ?? 'Linear-Nr. '.$b['adrnr'] }}</span>
                                <span class="text-amber-700">{{ $statusText[$b['status']] }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('module.schulkantine.dishes.menueserve.buchungen.uebernehmen') }}" class="flex justify-end"
                  onsubmit="return confirm(@js($bereit.' Buchungen als Bestellung anlegen?'))">
                @csrf
                <input type="hidden" name="ab" value="{{ $ab->format('Y-m-d') }}">
                <button type="submit" @disabled($bereit === 0)
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50">
                    {{ $bereit }} Buchungen übernehmen
                </button>
            </form>
        @elseif (! $fehler)
            <div class="rounded-xl border border-gray-200 bg-white px-4 py-10 text-center text-sm text-gray-500">Ab {{ $ab->format('d.m.Y') }} gibt es in Menü&amp;Serve keine Buchungen.</div>
        @endif
    </div>
</x-app-layout>
