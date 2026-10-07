<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2">
            <x-module-icon name="restaurant" class="text-2xl text-indigo-600" />
            <h1 class="text-xl font-semibold text-gray-800">Gerichte aus Menü&amp;Serve übernehmen</h1>
        </div>
    </x-slot>

    @php
        $artName = \Intranet\Modules\Schulkantine\Support\LinearPreise::ARTEN;
        $artText = \Intranet\Modules\Schulkantine\Support\MenueServeGerichte::ARTEN;
        $nichtFuer = \Intranet\Modules\Schulkantine\Support\MenueServeGerichte::NICHT_FUER;
        $neu = collect($gerichte)->where('vorhanden', false);
        // Vorauswahl: neu und in den letzten zwei Jahren noch auf dem Speiseplan (ohne Datum: alle neuen).
        $grenze = now()->subYears(2)->format('Y-m-d');
        $vorgewaehlt = fn ($g) => ! $g['vorhanden'] && (! $g['zuletzt'] || $g['zuletzt'] >= $grenze);
    @endphp

    <div class="w-full space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-gray-500">
                Menü&amp;Serve hat keine Gerichte-Liste – übernommen werden die verschiedenen Titel aus dem alten Speiseplan
                (Snacks ausgenommen). Die Fleischart setzt „nicht geeignet für" (z. B. vegetarisch, halal). Gleichnamige Gerichte, die es hier schon gibt, bleiben unberührt.
            </p>
            <a href="{{ route('module.schulkantine.dishes.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← zurück zu den Gerichten</a>
        </div>

        @if ($fehler)
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">Menü&amp;Serve ist nicht lesbar: {{ $fehler }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
        @endif

        @if ($gerichte)
            <form method="POST" action="{{ route('module.schulkantine.dishes.menueserve.import') }}" class="space-y-5"
                  x-data="{ zahl: {{ collect($gerichte)->filter($vorgewaehlt)->count() }}, zaehlen() { this.zahl = this.$root.querySelectorAll('input[name=\'auswahl[]\']:checked').length } }"
                  @change="zaehlen()">
                @csrf

                {{-- Menülinien → Kategorie und Preise --}}
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
                    <div class="border-b border-gray-100 px-4 py-2 text-sm font-semibold text-gray-700">Menülinien zuordnen</div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-100 text-left text-xs uppercase tracking-wide text-gray-400">
                                    <th class="px-4 py-2 font-medium">Menülinie</th>
                                    <th class="px-3 py-2 text-right font-medium">Titel</th>
                                    <th class="px-3 py-2 font-medium">Kategorie hier</th>
                                    @foreach ($arten as $art)
                                        <th class="px-3 py-2 font-medium" title="Vertragsart {{ $art }}">{{ $artName[$art] ?? $art }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($linien as $l)
                                    @php
                                        $treffer = $categories->first(fn ($c) => mb_strtolower($c->name) === mb_strtolower($l['titel']));
                                    @endphp
                                    <tr>
                                        <td class="whitespace-nowrap px-4 py-2 font-medium text-gray-900">{{ $l['titel'] }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums text-gray-500">{{ $l['anzahl'] }}</td>
                                        <td class="px-3 py-2">
                                            <select name="kategorie[{{ $l['id'] }}]" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                <option value="">– nicht übernehmen –</option>
                                                @foreach ($categories as $c)
                                                    <option value="{{ $c->id }}" @selected(old('kategorie.'.$l['id'], $treffer?->id) == $c->id)>{{ $c->name }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        @foreach ($arten as $art)
                                            <td class="px-3 py-2">
                                                <input type="text" inputmode="decimal" name="preis[{{ $l['id'] }}][{{ $art }}]"
                                                       value="{{ old('preis.'.$l['id'].'.'.$art, isset($l['preise'][$art]) ? number_format($l['preise'][$art], 2, ',', '') : '') }}"
                                                       class="w-20 rounded-lg border-gray-300 text-right text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="border-t border-gray-100 px-4 py-2 text-xs text-gray-400">
                        Preise vorbelegt aus Menü&amp;Serve (Spalten 1–4 = Vertragsarten {{ implode(', ', $arten) }}). Der Hauptpreis eines Gerichts wird der teuerste davon.
                    </p>
                </div>

                {{-- Gerichte --}}
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-4 py-2">
                        <div class="text-sm font-semibold text-gray-700">
                            {{ count($gerichte) }} Titel · {{ $neu->count() }} neu · vorausgewählt: neu und seit {{ now()->subYears(2)->format('Y') }} benutzt
                        </div>
                        <div class="flex items-center gap-3 text-xs">
                            <button type="button" class="text-indigo-600 hover:underline"
                                    @click="$root.querySelectorAll('input[name=\'auswahl[]\']:not(:disabled)').forEach(c => c.checked = true); zaehlen()">alle neuen</button>
                            <button type="button" class="text-indigo-600 hover:underline"
                                    @click="$root.querySelectorAll('input[name=\'auswahl[]\']').forEach(c => c.checked = false); zaehlen()">keine</button>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-100 text-left text-xs uppercase tracking-wide text-gray-400">
                                    <th class="w-8 px-4 py-2"></th>
                                    <th class="px-3 py-2 font-medium">Titel</th>
                                    <th class="px-3 py-2 font-medium">Menülinie</th>
                                    <th class="px-3 py-2 font-medium" title="Fleischart aus Menü&amp;Serve → „nicht geeignet für“">Art</th>
                                    <th class="px-3 py-2 text-right font-medium">Mal</th>
                                    <th class="px-3 py-2 font-medium">Zuletzt</th>
                                    <th class="px-3 py-2 font-medium">Notiz → Beschreibung</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($gerichte as $g)
                                    <tr class="{{ $g['vorhanden'] ? 'text-gray-400' : '' }}">
                                        <td class="px-4 py-1.5">
                                            <input type="checkbox" name="auswahl[]" value="{{ $g['key'] }}"
                                                   @checked(old('auswahl') ? in_array($g['key'], old('auswahl'), true) : $vorgewaehlt($g)) @disabled($g['vorhanden'])
                                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                        </td>
                                        <td class="px-3 py-1.5 font-medium {{ $g['vorhanden'] ? '' : 'text-gray-900' }}">
                                            {{ $g['titel'] }}
                                            @if ($g['vorhanden'])<span class="ml-1 text-xs font-normal">· schon vorhanden</span>@endif
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-1.5 text-gray-500">{{ $linien[$g['linie']]['titel'] ?? '–' }}</td>
                                        <td class="whitespace-nowrap px-3 py-1.5" title="nicht geeignet für: {{ implode(', ', $nichtFuer[$g['art']] ?? []) ?: '–' }}">
                                            @if ($g['art'] === 7)
                                                <span class="text-green-700">Vegetarisch</span>
                                            @elseif ($g['art'])
                                                <span class="text-gray-700">{{ $artText[$g['art']] }}</span>
                                            @else
                                                <span class="text-gray-300">–</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-1.5 text-right tabular-nums text-gray-500">{{ $g['anzahl'] }}</td>
                                        <td class="whitespace-nowrap px-3 py-1.5 text-gray-500">{{ $g['zuletzt'] ? \Illuminate\Support\Carbon::parse($g['zuletzt'])->format('d.m.Y') : '–' }}</td>
                                        <td class="px-3 py-1.5 text-xs text-gray-500">{{ \Illuminate\Support\Str::limit($g['notiz'], 120) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                            x-text="zahl + ' Gerichte übernehmen'">Gerichte übernehmen</button>
                </div>
            </form>
        @elseif (! $fehler)
            <div class="rounded-xl border border-gray-200 bg-white px-4 py-10 text-center text-sm text-gray-500">In Menü&amp;Serve wurden keine Gerichte gefunden.</div>
        @endif
    </div>
</x-app-layout>
