<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2">
            <x-module-icon name="chart" class="text-2xl text-indigo-600" />
            <h1 class="text-xl font-semibold text-gray-800">Abrechnung an Linear</h1>
        </div>
    </x-slot>

    @php $euro = fn ($v) => number_format((float) $v, 2, ',', '.').' €'; @endphp

    <div class="w-full space-y-5">
        <div class="rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
            So sähen die Zeilen für <code>Linear2.dbo.MgEsGeld</code> aus, wenn {{ $monthLabel }} jetzt abgeschlossen würde
            (Aufbau wie beim alten Menü&amp;Serve: je Esser eine Zeile, Datum = Monatsletzter). Der Betrag umfasst
            Menüs, OGS, spontane Abholungen und <strong>Chip-Pfand</strong>. Vertragsnehmer und Vertragsnummer stammen
            aus dem letzten nächtlichen Linear-Import.
            <strong>Gesendet wird nur, was Sie je Zeile mit „An Linear senden" auslösen</strong> – jeder Esser höchstens
            einmal je Monat. Soll/DatumSoll füllt Linear beim nächsten Sollstellungslauf.
        </div>

        @if ($errors->has('linear'))
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first('linear') }}</div>
        @endif

        @unless ($vertraegeStand)
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                ⚠️ Es liegen noch keine Vertragsdaten aus Linear vor (der Linear-Import muss einmal scharf laufen) –
                deshalb ist unten alles als nicht übertragbar aufgeführt.
            </div>
        @endunless

        <div class="flex flex-wrap items-end justify-between gap-4">
            <form method="GET" action="{{ route('module.schulkantine.reports.linear') }}" class="flex items-end gap-2">
                <div>
                    <label for="monat" class="block text-xs font-medium text-gray-500">Abrechnungsmonat</label>
                    <select id="monat" name="monat" onchange="this.form.submit()"
                            class="mt-1 rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach ($months as $m)
                            <option value="{{ $m['value'] }}" @selected($m['value'] === $monthValue)>{{ $m['label'] }}</option>
                        @endforeach
                    </select>
                </div>
            </form>
            <a href="{{ route('module.schulkantine.reports.index', ['monat' => $monthValue]) }}" class="text-sm text-gray-500 hover:text-gray-700">← zurück zur Auswertung</a>
        </div>

        <div class="grid gap-3 sm:grid-cols-3">
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <div class="text-xs uppercase tracking-wide text-gray-400">Zeilen für Linear</div>
                <div class="mt-1 text-2xl font-bold text-gray-900">{{ count($zeilen) }}</div>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <div class="text-xs uppercase tracking-wide text-gray-400">Summe</div>
                <div class="mt-1 text-2xl font-bold text-gray-900">{{ $euro($summe) }}</div>
            </div>
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                <div class="text-xs uppercase tracking-wide text-amber-600">Nicht übertragbar</div>
                <div class="mt-1 text-2xl font-bold text-amber-700">{{ count($ausgeschlossen) }}</div>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-xs uppercase tracking-wide text-gray-400">
                            <th class="px-3 py-2">AdrNr</th>
                            <th class="px-3 py-2">AbwAdrNr</th>
                            <th class="px-3 py-2">Esser</th>
                            <th class="px-3 py-2">Art</th>
                            <th class="px-3 py-2">VertragNr</th>
                            <th class="px-3 py-2 text-right">Anzahl</th>
                            <th class="px-3 py-2 text-right">Betrag = Gesamt</th>
                            <th class="px-3 py-2">Beschreibung</th>
                            <th class="px-3 py-2">Datum = DatumU</th>
                            <th class="px-3 py-2 text-right">davon Pfand</th>
                            <th class="px-3 py-2 text-right">Linear</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($zeilen as $z)
                            <tr class="hover:bg-gray-50">
                                <td class="px-3 py-2 font-mono">{{ $z['AdrNr'] }}</td>
                                <td class="px-3 py-2 font-mono">{{ $z['AbwAdrNr'] }}</td>
                                <td class="px-3 py-2 text-gray-700">{{ $z['user']?->name }}</td>
                                <td class="px-3 py-2">{{ $z['Art'] }}</td>
                                <td class="px-3 py-2 font-mono">{{ $z['VertragNr'] }}</td>
                                <td class="px-3 py-2 text-right">{{ $z['Anzahl'] }}</td>
                                <td class="px-3 py-2 text-right font-semibold" title="Menüs {{ $euro($z['menu']) }} · OGS {{ $euro($z['ogs']) }} · spontan {{ $euro($z['spontan']) }} · Pfand {{ $euro($z['pfand']) }}">{{ $euro($z['Betrag']) }}</td>
                                <td class="px-3 py-2 text-gray-500">{{ $z['Beschreibung'] }}</td>
                                <td class="px-3 py-2 text-gray-500">{{ $z['Datum']->format('d.m.Y') }}</td>
                                <td class="px-3 py-2 text-right text-gray-500">{{ $z['pfand'] != 0 ? $euro($z['pfand']) : '' }}</td>
                                <td class="px-3 py-2 text-right">
                                    @if ($z['export'])
                                        <span class="whitespace-nowrap text-xs font-medium text-green-700" title="{{ $z['export']->hinweis }}">
                                            ✓ gesendet {{ \Illuminate\Support\Carbon::parse($z['export']->sent_at)->format('d.m.Y H:i') }}
                                        </span>
                                        @if (round((float) $z['export']->betrag, 2) !== round((float) $z['Betrag'], 2))
                                            <div class="whitespace-nowrap text-xs font-medium text-amber-700">⚠️ gesendet {{ $euro($z['export']->betrag) }}, jetzt {{ $euro($z['Betrag']) }}</div>
                                        @endif
                                    @else
                                        @php
                                            $frage = "Diese Zeile jetzt an Linear (MgEsGeld) senden?\n\n"
                                                ."AdrNr {$z['AdrNr']} · AbwAdrNr {$z['AbwAdrNr']} ({$z['user']?->name})\n"
                                                ."Art {$z['Art']} · VertragNr {$z['VertragNr']} · Anzahl 1\n"
                                                .'Betrag = Gesamt '.$euro($z['Betrag'])."\n"
                                                .'Beschreibung '.$z['Beschreibung'].' · Datum = DatumU '.$z['Datum']->format('d.m.Y');
                                        @endphp
                                        <form method="POST" action="{{ route('module.schulkantine.reports.linear.send', ['user' => $z['user'], 'monat' => $monthValue]) }}"
                                              onsubmit="return confirm(@js($frage))">
                                            @csrf
                                            <button type="submit" class="whitespace-nowrap rounded-md border border-sky-300 bg-white px-2 py-1 text-xs font-medium text-sky-800 hover:bg-sky-50">An Linear senden</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="11" class="px-3 py-8 text-center text-gray-500">Für {{ $monthLabel }} gäbe es keine Zeilen für Linear.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($ausgeschlossen)
            <div class="overflow-hidden rounded-xl border border-amber-200 bg-white">
                <div class="border-b border-amber-100 bg-amber-50 px-4 py-2 text-sm font-medium text-amber-800">Nicht übertragbar – würde nicht an Linear gehen</div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 text-left text-xs uppercase tracking-wide text-gray-400">
                                <th class="px-3 py-2">Esser</th>
                                <th class="px-3 py-2">AdrNr (Linear)</th>
                                <th class="px-3 py-2 text-right">Betrag</th>
                                <th class="px-3 py-2">Grund</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($ausgeschlossen as $a)
                                <tr>
                                    <td class="px-3 py-2 text-gray-700">{{ $a['user']?->name ?? '—' }}</td>
                                    <td class="px-3 py-2 font-mono text-gray-500">{{ $a['user']?->externe_id ?: '—' }}</td>
                                    <td class="px-3 py-2 text-right">{{ $euro($a['betrag']) }}</td>
                                    <td class="px-3 py-2 text-amber-700">{{ $a['grund'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
