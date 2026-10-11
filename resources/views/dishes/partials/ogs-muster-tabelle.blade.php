{{-- Eine Tabelle OGS-Kinder (offen oder erledigt). $erledigt: Wochentage stehen auf dem jetzigen Abo statt auf dem Vorschlag. --}}
<div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-gray-100 text-left text-xs uppercase tracking-wide text-gray-400">
                <th class="w-8 px-4 py-2"></th>
                <th class="px-3 py-2 font-medium">Kind</th>
                @foreach ($wt as $d)
                    <th class="px-3 py-2 text-center font-medium">{{ $namen[$d] }}</th>
                @endforeach
                <th class="px-3 py-2 font-medium">Vorschlag</th>
                <th class="px-3 py-2 font-medium">Abo jetzt</th>
                <th class="px-3 py-2 font-medium">Änderung</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @foreach ($kinder as $k)
                @php
                    $id = $k['user']->id;
                    $haken = $erledigt ? $k['aktuell'] : $k['vorschlag'];
                @endphp
                {{-- Zeigt laufend, ob die angehakten Tage vom jetzigen Abo abweichen. --}}
                <tr x-data="{
                        tage: @js(array_map('strval', $haken)),
                        aktuell: @js(array_map('strval', $k['aktuell'])),
                        alle: @js(array_map('strval', $wt)),
                        namen: @js($namen),
                        text(liste) {
                            if (!liste.length) return 'kein Abo';
                            if (this.alle.every(d => liste.includes(d))) return 'alle Tage';
                            return [...liste].sort().map(d => this.namen[d]).join(', ');
                        },
                        get aendert() { return [...this.tage].sort().join() !== [...this.aktuell].sort().join(); },
                    }"
                    :class="aendert ? 'bg-amber-50' : '{{ $k['unklar'] && ! $erledigt ? 'bg-amber-50/40' : '' }}'">
                    <td class="px-4 py-1.5">
                        <input type="checkbox" name="auswahl[]" value="{{ $id }}" @checked(! $erledigt && ! $k['unklar'])
                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    </td>
                    <td class="whitespace-nowrap px-3 py-1.5">
                        <span class="font-medium text-gray-900">{{ $k['user']->name }}</span>
                        <span class="ml-1 text-xs text-gray-400">{{ $k['tage'] }} Tage</span>
                        @if ($k['unklar'])<span class="ml-1 text-xs font-medium text-amber-700">unklar</span>@endif
                    </td>
                    @foreach ($wt as $d)
                        @php $a = $k['anteile'][$d] ?? 0; @endphp
                        <td class="px-3 py-1.5 text-center">
                            <label class="inline-flex items-center gap-1.5">
                                <input type="checkbox" name="tage[{{ $id }}][]" value="{{ $d }}" x-model="tage" @checked(in_array($d, $haken, true))
                                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                <span class="w-9 text-right text-xs tabular-nums {{ $a >= 0.75 ? 'text-gray-900' : ($a > 0.25 ? 'text-amber-700' : 'text-gray-400') }}">{{ (int) round($a * 100) }} %</span>
                            </label>
                        </td>
                    @endforeach
                    <td class="whitespace-nowrap px-3 py-1.5 text-gray-700">{{ $kurz($k['vorschlag'], $wt) }}</td>
                    <td class="whitespace-nowrap px-3 py-1.5 {{ $k['vorschlag'] !== $k['aktuell'] ? 'text-amber-700' : 'text-gray-500' }}">{{ $k['abo'] }}</td>
                    <td class="whitespace-nowrap px-3 py-1.5 text-xs">
                        <span x-show="aendert" class="rounded-full bg-amber-100 px-2 py-0.5 font-semibold text-amber-800"
                              x-text="'ändert sich: ' + text(aktuell) + ' → ' + text(tage)"></span>
                        <span x-show="!aendert" class="text-gray-400">bleibt</span>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
