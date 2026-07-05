<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2">
            <x-module-icon name="chart" class="text-2xl text-indigo-600" />
            <h1 class="text-xl font-semibold text-gray-800">Auswertung &amp; Abrechnung</h1>
        </div>
    </x-slot>

    <div class="max-w-5xl space-y-5">
        @if (! $season)
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Es ist keine Saison als „aktiv" markiert. Lege zuerst eine aktive Saison an.
            </div>
        @else
            @php $euro = fn ($v) => number_format((float) $v, 2, ',', '.').' €'; @endphp

            {{-- Monatsauswahl + Export --}}
            <div class="flex flex-wrap items-end justify-between gap-4">
                <form method="GET" action="{{ route('module.schulkantine.reports.index') }}" class="flex items-end gap-2">
                    <div>
                        <label for="monat" class="block text-xs font-medium text-gray-500">Abrechnungsmonat</label>
                        <select id="monat" name="monat" onchange="this.form.submit()"
                                class="mt-1 rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach ($months as $m)
                                <option value="{{ $m['value'] }}" @selected($m['value'] === $monthValue)>{{ $m['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <span class="text-xs text-gray-400">Saison „{{ $season->name }}"</span>
                </form>

                @if ($isAdmin)
                    <div class="flex items-center gap-2">
                        <a href="{{ route('module.schulkantine.reports.csv', ['monat' => $monthValue]) }}"
                           class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            <x-module-icon name="download" class="text-base" /> CSV
                        </a>
                        <a href="{{ route('module.schulkantine.reports.pdf', ['monat' => $monthValue]) }}"
                           class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                            <x-module-icon name="download" class="text-base" /> PDF
                        </a>
                    </div>
                @endif
            </div>

            {{-- Kennzahlen --}}
            <div class="grid gap-3 sm:grid-cols-4">
                <div class="rounded-xl border border-gray-200 bg-white p-4">
                    <div class="text-xs uppercase tracking-wide text-gray-400">Personen</div>
                    <div class="mt-1 text-2xl font-bold text-gray-900">{{ $personCount }}</div>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-4">
                    <div class="text-xs uppercase tracking-wide text-gray-400">Gesamt</div>
                    <div class="mt-1 text-2xl font-bold text-gray-900">{{ $euro($grandTotal) }}</div>
                </div>
                <div class="rounded-xl border border-green-200 bg-green-50 p-4">
                    <div class="text-xs uppercase tracking-wide text-green-600">Bezahlt</div>
                    <div class="mt-1 text-2xl font-bold text-green-700">{{ $euro($paidTotal) }}</div>
                </div>
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                    <div class="text-xs uppercase tracking-wide text-amber-600">Offen</div>
                    <div class="mt-1 text-2xl font-bold text-amber-700">{{ $euro($openTotal) }}</div>
                </div>
            </div>

            @if (empty($households))
                <div class="rounded-xl border border-gray-200 bg-white px-4 py-10 text-center text-sm text-gray-500">
                    Für {{ $monthLabel }} liegen keine abrechenbaren Posten vor.
                </div>
            @else
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-100 text-left text-xs uppercase tracking-wide text-gray-400">
                                    <th class="px-4 py-2 font-medium">Person</th>
                                    <th class="px-3 py-2 text-right font-medium">Menü</th>
                                    <th class="px-3 py-2 text-right font-medium">OGS</th>
                                    <th class="px-3 py-2 text-right font-medium">Spontan</th>
                                    <th class="px-3 py-2 text-right font-medium">Pfand</th>
                                    <th class="px-3 py-2 text-right font-medium">Summe</th>
                                    <th class="px-4 py-2 text-center font-medium">Bezahlt</th>
                                </tr>
                            </thead>
                            @foreach ($households as $hh)
                                <tbody class="divide-y divide-gray-50 border-b-4 border-gray-100">
                                    <tr class="bg-gray-50/70">
                                        <td class="px-4 py-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500" colspan="5">
                                            Haushalt {{ $hh['name'] }}
                                        </td>
                                        <td class="px-3 py-1.5 text-right text-xs font-semibold text-gray-600">{{ $euro($hh['subtotal']) }}</td>
                                        <td class="px-4 py-1.5 text-center text-xs text-gray-400">
                                            @if ($hh['open'] > 0)
                                                offen {{ $euro($hh['open']) }}
                                            @else
                                                <span class="text-green-600">vollständig</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @foreach ($hh['members'] as $m)
                                        @php $l = $m['line']; @endphp
                                        <tr class="{{ $m['paid'] ? 'bg-green-50/40' : '' }}">
                                            <td class="px-4 py-2">
                                                <a href="{{ route('module.schulkantine.reports.show', [$m['user'], 'monat' => $monthValue]) }}"
                                                   class="font-medium text-indigo-600 hover:text-indigo-800 hover:underline">{{ $m['user']->name }}</a>
                                                <div class="flex items-center gap-2 text-xs text-gray-400">
                                                    <span>{{ $m['group'] }}</span>
                                                    @if ($l['no_show_count'] > 0)
                                                        <span class="rounded-full bg-rose-50 px-1.5 py-0.5 font-medium text-rose-600"
                                                              title="Bestellt, aber nicht abgeholt (wird trotzdem berechnet)">
                                                            {{ $l['no_show_count'] }}× No-Show
                                                        </span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="px-3 py-2 text-right tabular-nums {{ $l['menu_total'] > 0 ? 'text-gray-700' : 'text-gray-300' }}">
                                                {{ $l['menu_total'] > 0 ? $euro($l['menu_total']) : '–' }}
                                                @if ($l['menu_count'] > 0)<span class="text-xs text-gray-400"> ({{ $l['menu_count'] }})</span>@endif
                                            </td>
                                            <td class="px-3 py-2 text-right tabular-nums {{ $l['ogs_total'] > 0 ? 'text-gray-700' : 'text-gray-300' }}">
                                                {{ $l['ogs_total'] > 0 ? $euro($l['ogs_total']) : '–' }}
                                                @if ($l['ogs_days'] > 0)<span class="text-xs text-gray-400"> ({{ $l['ogs_days'] }} T.)</span>@endif
                                            </td>
                                            <td class="px-3 py-2 text-right tabular-nums {{ $l['spontan_total'] > 0 ? 'text-gray-700' : 'text-gray-300' }}">
                                                {{ $l['spontan_total'] > 0 ? $euro($l['spontan_total']) : '–' }}
                                                @if ($l['spontan_count'] > 0)<span class="text-xs text-gray-400"> ({{ $l['spontan_count'] }})</span>@endif
                                            </td>
                                            <td class="px-3 py-2 text-right tabular-nums {{ $l['pfand_net'] != 0 ? 'text-gray-700' : 'text-gray-300' }}">
                                                {{ $l['pfand_net'] != 0 ? $euro($l['pfand_net']) : '–' }}
                                            </td>
                                            <td class="px-3 py-2 text-right font-semibold tabular-nums text-gray-900">{{ $euro($l['total']) }}</td>
                                            <td class="px-4 py-2 text-center">
                                                @if ($isAdmin)
                                                    @if ($m['paid'])
                                                        <form method="POST" action="{{ route('module.schulkantine.reports.unpaid', $m['user']) }}" class="inline">
                                                            @csrf
                                                            @method('DELETE')
                                                            <input type="hidden" name="monat" value="{{ $monthValue }}">
                                                            <button type="submit"
                                                                    title="Als bezahlt am {{ optional($m['settlement']->paid_at)->format('d.m.Y') }} – klicken zum Zurücknehmen"
                                                                    class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2.5 py-1 text-xs font-medium text-green-700 hover:bg-green-200">
                                                                ✓ Bezahlt
                                                            </button>
                                                        </form>
                                                    @else
                                                        <form method="POST" action="{{ route('module.schulkantine.reports.paid', $m['user']) }}" class="inline">
                                                            @csrf
                                                            <input type="hidden" name="monat" value="{{ $monthValue }}">
                                                            <button type="submit"
                                                                    class="inline-flex items-center gap-1 rounded-full border border-gray-300 bg-white px-2.5 py-1 text-xs font-medium text-gray-600 hover:bg-gray-50">
                                                                als bezahlt
                                                            </button>
                                                        </form>
                                                    @endif
                                                @else
                                                    @if ($m['paid'])
                                                        <span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2.5 py-1 text-xs font-medium text-green-700">✓ Bezahlt</span>
                                                    @else
                                                        <span class="text-xs font-medium text-amber-600">offen</span>
                                                    @endif
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            @endforeach
                            <tfoot>
                                <tr class="border-t-2 border-gray-200 bg-gray-50">
                                    <td class="px-4 py-2.5 font-semibold text-gray-700">Gesamt {{ $monthLabel }}</td>
                                    <td colspan="4"></td>
                                    <td class="px-3 py-2.5 text-right text-base font-bold text-gray-900 tabular-nums">{{ $euro($grandTotal) }}</td>
                                    <td class="px-4 py-2.5 text-center text-xs text-amber-600">
                                        @if ($openTotal > 0) offen {{ $euro($openTotal) }} @else <span class="text-green-600">alles bezahlt</span> @endif
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <p class="text-xs text-gray-400">
                    Abrechnungsbasis sind die <strong>verbindlichen Vorbestellungen</strong> (No-Shows zahlen trotzdem),
                    die <strong>spontanen Abholungen</strong> und das <strong>Chip-Pfand</strong> (Ausgabe +, Rückgabe −).
                    Rechtzeitig stornierte Bestellungen sind nicht enthalten. Der Export liefert dieselben Zahlen für die externe Abrechnung.
                </p>
            @endif
        @endif
    </div>
</x-app-layout>
