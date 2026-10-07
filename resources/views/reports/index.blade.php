<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2">
            <x-module-icon name="chart" class="text-2xl text-indigo-600" />
            <h1 class="text-xl font-semibold text-gray-800">Auswertung &amp; Abrechnung</h1>
        </div>
    </x-slot>

    <div class="w-full space-y-5">
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

            {{-- Linear: Stand des Monats, Versand immer gesammelt --}}
            @if ($linear)
                @php
                    $zp = $linear['sendezeitpunkt'];
                    $faellig = $zp && now()->gte($zp);
                @endphp
                @if ($errors->has('linear'))
                    <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first('linear') }}</div>
                @endif
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
                    <div class="space-y-0.5">
                        <div class="font-semibold">Linear</div>
                        <div>
                            {{ $linear['gesendet'] }} gesendet ({{ $euro($linear['gesendetSumme']) }})
                            · {{ $linear['bereit'] }} bereit ({{ $euro($linear['bereitSumme']) }})
                            · {{ $linear['ausgeschlossen'] }} nicht übertragbar
                        </div>
                        <div class="text-xs text-sky-700">
                            @if (! $zp)
                                In {{ $monthLabel }} gibt es keinen Kantinentag – nichts zu senden.
                            @elseif (! $faellig)
                                Wird automatisch am {{ $zp->format('d.m.Y') }} um {{ $zp->format('H:i') }} Uhr gesendet (letzter Kantinentag, nach Abbestellschluss).
                            @else
                                Sendezeitpunkt {{ $zp->format('d.m.Y H:i') }} erreicht – der Task Linear/KantineAbrechnung sendet alles Bereite zusammen.
                            @endif
                        </div>
                        @unless ($linear['lesbar'])
                            <div class="text-xs font-medium text-amber-700">⚠️ Linear ist gerade nicht lesbar – Abrechnungsstand der gesendeten Zeilen fehlt.</div>
                        @endunless
                        @unless ($linear['vertraegeStand'])
                            <div class="text-xs font-medium text-amber-700">⚠️ Noch keine Vertragsdaten aus Linear importiert.</div>
                        @endunless
                    </div>
                    @if ($faellig && $linear['bereit'] > 0)
                        <form method="POST" action="{{ route('module.schulkantine.reports.linear.send', ['monat' => $monthValue]) }}"
                              onsubmit="return confirm(@js('Jetzt alle '.$linear['bereit'].' bereiten Zeilen ('.$euro($linear['bereitSumme']).') für '.$monthLabel.' an Linear senden?'))">
                            @csrf
                            <button type="submit" class="whitespace-nowrap rounded-lg border border-sky-300 bg-white px-3 py-2 text-sm font-medium text-sky-800 hover:bg-sky-100">Jetzt alle an Linear senden</button>
                        </form>
                    @endif
                </div>
            @endif

            @if (empty($households))
                <div class="rounded-xl border border-gray-200 bg-white px-4 py-10 text-center text-sm text-gray-500">
                    Für {{ $monthLabel }} liegen keine abrechenbaren Posten vor.
                </div>
            @else
                @php
                    // Linear-Zustand einer Person als [Text, Farbe] – Personen- und Haushaltszeile nutzen dasselbe.
                    $linearZustand = function ($lz) use ($euro) {
                        if (! $lz) {
                            return null;
                        }
                        if (isset($lz['grund'])) {
                            return ['nicht übertragbar', 'text-amber-700', $lz['grund']];
                        }
                        if (! $lz['export']) {
                            return ['bereit', 'text-sky-700', 'AdrNr '.$lz['AdrNr'].' · Art '.$lz['Art'].' · Vertrag '.$lz['VertragNr']];
                        }
                        $ls = $lz['linear'] ?? null;

                        return match ($ls['zustand'] ?? null) {
                            null => ['übermittelt', 'text-gray-500', 'Stand in Linear unbekannt'],
                            'fehlt' => ['⚠️ fehlt in Linear', 'text-red-700', 'Übermittelt, aber in Linear weder in MgEsGeld noch abgerechnet zu finden'],
                            'nicht' => ['übermittelt', 'text-gray-600', 'noch nicht abgerechnet'],
                            'offen' => ['abgerechnet · offen', 'text-amber-700', 'Forderung '.$euro($ls['betrag']).', offen '.$euro($ls['offen'])],
                            default => ['abgerechnet · bezahlt', 'text-green-700', 'Forderung '.$euro($ls['betrag'])],
                        };
                    };
                @endphp
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-100 text-left text-xs uppercase tracking-wide text-gray-400">
                                    <th class="px-4 py-2 font-medium">Haushalt / Person</th>
                                    <th class="px-3 py-2 text-right font-medium">Menü</th>
                                    <th class="px-3 py-2 text-right font-medium">OGS</th>
                                    <th class="px-3 py-2 text-right font-medium">Spontan</th>
                                    <th class="px-3 py-2 text-right font-medium">Pfand</th>
                                    <th class="px-3 py-2 text-right font-medium" title="Bestellt, aber nicht abgeholt (wird trotzdem berechnet)">No-Show</th>
                                    <th class="px-3 py-2 text-right font-medium">Summe</th>
                                    <th class="px-4 py-2 text-center font-medium">Bezahlt</th>
                                    @if ($linear)<th class="px-4 py-2 font-medium">Linear</th>@endif
                                </tr>
                            </thead>
                            @foreach ($households as $hh)
                                @php
                                    $sum = fn ($k) => array_sum(array_map(fn ($m) => $m['line'][$k], $hh['members']));
                                    $hhLinear = [];
                                    if ($linear) {
                                        foreach ($hh['members'] as $m) {
                                            $z = $linearZustand($linear['je'][$m['user']->id] ?? null);
                                            if ($z) {
                                                $hhLinear[$z[0]] ??= [0, $z[1]];
                                                $hhLinear[$z[0]][0]++;
                                            }
                                        }
                                    }
                                    $wert = fn ($v, $leer = '–') => $v != 0 ? $euro($v) : $leer;
                                    // Einzelperson: nichts aufzuklappen – die Zeile IST die Person.
                                    $einzeln = count($hh['members']) === 1 ? $hh['members'][0] : null;
                                @endphp
                                <tbody @unless ($einzeln) x-data="{ auf: false }" @endunless class="border-b border-gray-100">
                                    {{-- Haushaltszeile: Summen groß, bei mehreren Personen klappt ein Klick sie auf --}}
                                    <tr @if ($einzeln) class="hover:bg-gray-50" @else class="cursor-pointer hover:bg-gray-50" @click="auf = ! auf" @endif>
                                        <td class="px-4 py-2.5">
                                            <div class="flex items-center gap-2">
                                                @if ($einzeln)
                                                    <span class="inline-block w-3"></span>
                                                    <a href="{{ route('module.schulkantine.reports.show', [$einzeln['user'], 'monat' => $monthValue]) }}"
                                                       class="whitespace-nowrap font-semibold text-indigo-600 hover:text-indigo-800 hover:underline">{{ $einzeln['user']->name }}</a>
                                                    <span class="whitespace-nowrap text-xs text-gray-400">{{ $einzeln['group'] }}</span>
                                                @else
                                                    <span class="inline-block w-3 text-gray-400 transition-transform" :class="auf && 'rotate-90'">▸</span>
                                                    <span class="whitespace-nowrap font-semibold text-gray-900">{{ $hh['name'] }}</span>
                                                    <span class="whitespace-nowrap text-xs text-gray-400">{{ count($hh['members']) }} Personen</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-2.5 text-right tabular-nums {{ $sum('menu_total') > 0 ? 'text-gray-800' : 'text-gray-300' }}">{{ $wert($sum('menu_total')) }}</td>
                                        <td class="whitespace-nowrap px-3 py-2.5 text-right tabular-nums {{ $sum('ogs_total') > 0 ? 'text-gray-800' : 'text-gray-300' }}">{{ $wert($sum('ogs_total')) }}</td>
                                        <td class="whitespace-nowrap px-3 py-2.5 text-right tabular-nums {{ $sum('spontan_total') > 0 ? 'text-gray-800' : 'text-gray-300' }}">{{ $wert($sum('spontan_total')) }}</td>
                                        <td class="whitespace-nowrap px-3 py-2.5 text-right tabular-nums {{ $sum('pfand_net') != 0 ? 'text-gray-800' : 'text-gray-300' }}">{{ $wert($sum('pfand_net')) }}</td>
                                        <td class="whitespace-nowrap px-3 py-2.5 text-right tabular-nums {{ $sum('no_show_count') > 0 ? 'font-medium text-rose-600' : 'text-gray-300' }}">{{ $sum('no_show_count') ?: '–' }}</td>
                                        <td class="whitespace-nowrap px-3 py-2.5 text-right text-base font-bold tabular-nums text-gray-900">{{ $euro($hh['subtotal']) }}</td>
                                        <td class="px-4 py-2.5 text-center">
                                            @if ($hh['open'] > 0)
                                                <span class="text-xs font-medium text-amber-600">offen {{ $euro($hh['open']) }}</span>
                                            @else
                                                <span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2.5 py-1 text-xs font-medium text-green-700">✓ Bezahlt</span>
                                            @endif
                                        </td>
                                        @if ($linear)
                                            <td class="px-4 py-2.5 text-xs">
                                                @if ($einzeln)
                                                    @php $lz = $linear['je'][$einzeln['user']->id] ?? null; @endphp
                                                    @include('schulkantine::reports.partials.linear-person', ['lz' => $lz, 'z' => $linearZustand($lz)])
                                                @else
                                                    @foreach ($hhLinear as $text => $info)
                                                        <div class="whitespace-nowrap font-medium {{ $info[1] }}">{{ $info[0] }}× {{ $text }}</div>
                                                    @endforeach
                                                    @if ($hhLinear === [])
                                                        <span class="text-gray-300">–</span>
                                                    @endif
                                                @endif
                                            </td>
                                        @endif
                                    </tr>

                                    {{-- Personen: klein, erst nach dem Aufklappen --}}
                                    @foreach ($einzeln ? [] : $hh['members'] as $m)
                                        @php $l = $m['line']; @endphp
                                        <tr x-show="auf" x-cloak class="bg-gray-50/60 text-xs text-gray-600">
                                            <td class="whitespace-nowrap py-1.5 pl-10 pr-4">
                                                <a href="{{ route('module.schulkantine.reports.show', [$m['user'], 'monat' => $monthValue]) }}"
                                                   class="font-medium text-indigo-600 hover:text-indigo-800 hover:underline">{{ $m['user']->name }}</a>
                                                <span class="ml-1 text-gray-400">{{ $m['group'] }}</span>
                                            </td>
                                            <td class="whitespace-nowrap px-3 py-1.5 text-right tabular-nums">
                                                {{ $wert($l['menu_total']) }}@if ($l['menu_count'] > 0)<span class="text-gray-400"> ({{ $l['menu_count'] }})</span>@endif
                                            </td>
                                            <td class="whitespace-nowrap px-3 py-1.5 text-right tabular-nums">
                                                {{ $wert($l['ogs_total']) }}@if ($l['ogs_days'] > 0)<span class="text-gray-400"> ({{ $l['ogs_days'] }} T.)</span>@endif
                                            </td>
                                            <td class="whitespace-nowrap px-3 py-1.5 text-right tabular-nums">
                                                {{ $wert($l['spontan_total']) }}@if ($l['spontan_count'] > 0)<span class="text-gray-400"> ({{ $l['spontan_count'] }})</span>@endif
                                            </td>
                                            <td class="whitespace-nowrap px-3 py-1.5 text-right tabular-nums">{{ $wert($l['pfand_net']) }}</td>
                                            <td class="whitespace-nowrap px-3 py-1.5 text-right tabular-nums {{ $l['no_show_count'] > 0 ? 'text-rose-600' : '' }}">{{ $l['no_show_count'] ?: '–' }}</td>
                                            <td class="whitespace-nowrap px-3 py-1.5 text-right tabular-nums">{{ $euro($l['total']) }}</td>
                                            {{-- Nur-Anzeige: Bezahlt-Status kommt aus Linear, nicht manuell setzbar. --}}
                                            <td class="px-4 py-1.5 text-center">
                                                @if ($m['paid'])
                                                    <span class="text-green-700" @if ($m['settlement']?->paid_at) title="Bezahlt am {{ $m['settlement']->paid_at->format('d.m.Y') }}" @endif>✓ bezahlt</span>
                                                @else
                                                    <span class="text-amber-600">offen</span>
                                                @endif
                                            </td>
                                            @if ($linear)
                                                @php $lz = $linear['je'][$m['user']->id] ?? null; @endphp
                                                <td class="px-4 py-1.5">
                                                    @include('schulkantine::reports.partials.linear-person', ['lz' => $lz, 'z' => $linearZustand($lz)])
                                                </td>
                                            @endif
                                        </tr>
                                    @endforeach
                                </tbody>
                            @endforeach
                            <tfoot>
                                <tr class="border-t-2 border-gray-200 bg-gray-50">
                                    <td class="px-4 py-3 font-semibold text-gray-700">Gesamt {{ $monthLabel }}</td>
                                    <td colspan="5"></td>
                                    <td class="px-3 py-3 text-right text-lg font-bold tabular-nums text-gray-900">{{ $euro($grandTotal) }}</td>
                                    <td class="px-4 py-3 text-center text-xs text-amber-600">
                                        @if ($openTotal > 0) offen {{ $euro($openTotal) }} @else <span class="text-green-600">alles bezahlt</span> @endif
                                    </td>
                                    @if ($linear)<td></td>@endif
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
