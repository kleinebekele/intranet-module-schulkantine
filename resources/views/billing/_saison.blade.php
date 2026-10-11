{{-- Abrechnungstabelle einer Saison. Erwartet $daten (HaushaltsAbrechnung::saison), $euro. --}}
@php
    $esser = $daten['esser'];
    $mehrere = count($esser) > 1;
    $sid = $daten['season']->id;
    $hinweis = $daten['season']->hinweisOffen();
    $hatFolgende = collect($daten['monate'])->contains('zukunft', true);
@endphp
<div x-data="{ folgende: false, dialog: null }" class="space-y-3">
    @if ($hatFolgende)
        <label class="flex items-center gap-2 text-sm text-gray-600">
            <input type="checkbox" x-model="folgende" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
            folgende Monate anzeigen
        </label>
    @endif

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 text-left text-xs uppercase tracking-wide text-gray-400">
                        <th class="px-4 py-2 font-medium">Monat</th>
                        @if ($mehrere)
                            @foreach ($esser as $u)
                                <th class="px-3 py-2 text-right font-medium">{{ $u->name }}</th>
                            @endforeach
                        @endif
                        <th class="px-3 py-2 text-right font-medium">Gesamtbetrag</th>
                        <th class="px-3 py-2 font-medium">Status</th>
                        <th class="px-3 py-2 text-center font-medium">Download</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($daten['monate'] as $mo)
                        @php $key = $sid.'-'.$mo['wert']; @endphp
                        <tr @if ($mo['zukunft']) x-show="folgende" x-cloak @endif class="{{ $mo['zukunft'] ? 'text-gray-400' : '' }}">
                            <td class="whitespace-nowrap px-4 py-2 font-medium text-gray-800">{{ $mo['label'] }}</td>
                            @if ($mehrere)
                                @foreach ($esser as $u)
                                    @php $z = $mo['zellen'][$u->id]; @endphp
                                    <td class="px-3 py-2 text-right">
                                        <button type="button" @click="dialog = '{{ $key }}-{{ $u->id }}'"
                                                class="tabular-nums hover:text-indigo-700 hover:underline {{ $z['betrag'] === null ? 'text-xs italic text-gray-400' : 'text-gray-700' }}">
                                            {{ $z['betrag'] === null ? 'keine Daten' : $euro($z['betrag']) }}
                                        </button>
                                    </td>
                                @endforeach
                            @endif
                            <td class="px-3 py-2 text-right">
                                <button type="button" @click="dialog = '{{ $key }}'"
                                        class="tabular-nums hover:text-indigo-700 hover:underline {{ $mo['gesamt'] === null ? 'text-xs italic text-gray-400' : 'font-semibold text-gray-900' }}">
                                    {{ $mo['gesamt'] === null ? 'keine Daten' : $euro($mo['gesamt']) }}
                                </button>
                            </td>
                            <td class="whitespace-nowrap px-3 py-2">
                                @switch ($mo['status'])
                                    @case ('bezahlt')
                                        <span class="text-xs font-semibold text-green-700">✓ bezahlt</span>
                                        @break
                                    @case ('teil_offen')
                                    @case ('komplett_offen')
                                        <span class="text-xs font-medium text-amber-700">{{ $mo['status'] === 'teil_offen' ? 'Teilbetrag' : 'Komplettbetrag' }} {{ $euro($mo['offen']) }} offen</span>
                                        <span class="ml-0.5 inline-flex h-4 w-4 cursor-help items-center justify-center rounded-full bg-amber-100 align-middle text-[10px] font-bold text-amber-700" title="{{ $hinweis }}">?</span>
                                        @break
                                    @case ('laufend')
                                        <span class="text-xs text-gray-500">läuft noch</span>
                                        @break
                                    @case ('nicht_abgerechnet')
                                        <span class="text-xs text-gray-500">noch nicht abgerechnet</span>
                                        @break
                                    @case ('teils_abgerechnet')
                                        <span class="text-xs text-gray-500">teilweise abgerechnet</span>
                                        @break
                                    @case ('keine_daten')
                                        <span class="text-xs italic text-gray-400">keine Daten</span>
                                        @break
                                    @default
                                        <span class="text-xs text-gray-300">–</span>
                                @endswitch
                            </td>
                            <td class="px-3 py-2 text-center">
                                @if ($mo['gesamt'] !== null && ! $mo['zukunft'])
                                    <a href="{{ route('module.schulkantine.abrechnung.pdf', ['saison' => $sid, 'monat' => $mo['wert']]) }}"
                                       class="inline-flex items-center gap-1 text-xs font-medium text-indigo-600 hover:text-indigo-800" title="Abrechnung {{ $mo['label'] }} als PDF">
                                        <x-module-icon name="download" class="text-base" /> PDF
                                    </a>
                                @else
                                    <span class="text-xs text-gray-300">–</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Detail-Dialoge: je Monat (alle Esser) und je Esser-Monat --}}
    @foreach ($daten['monate'] as $mo)
        @php $key = $sid.'-'.$mo['wert']; @endphp
        @foreach (array_merge([null], array_map(fn ($u) => $u->id, $esser)) as $nur)
            <div x-show="dialog === '{{ $key }}{{ $nur ? '-'.$nur : '' }}'" x-cloak @keydown.escape.window="dialog = null"
                 class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display:none;">
                <div class="absolute inset-0 bg-black/40" @click="dialog = null"></div>
                <div class="relative z-10 max-h-[85vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-5 shadow-xl">
                    <div class="flex items-start justify-between gap-3">
                        <h3 class="text-base font-semibold text-gray-800">
                            {{ $mo['label'] }}@if ($nur) · {{ collect($esser)->firstWhere('id', $nur)->name }}@endif
                        </h3>
                        <button type="button" @click="dialog = null" class="text-xl leading-none text-gray-400 hover:text-gray-700">×</button>
                    </div>
                    @foreach ($esser as $u)
                        @continue($nur && $u->id !== $nur)
                        <div class="mt-3">
                            @if (! $nur && count($esser) > 1)
                                <div class="border-b border-gray-100 pb-1 text-sm font-semibold text-gray-700">{{ $u->name }}</div>
                            @endif
                            @include('schulkantine::billing._posten', ['z' => $mo['zellen'][$u->id]])
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    @endforeach
</div>
