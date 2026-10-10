<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2">
            <x-module-icon name="restaurant" class="text-2xl text-indigo-600" />
            <h1 class="text-xl font-semibold text-gray-800">Menü&amp;Serve-Menüs zuordnen</h1>
        </div>
    </x-slot>

    <div class="w-full space-y-5">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <form method="GET" action="{{ route('module.schulkantine.dishes.menueserve') }}" class="flex items-end gap-2">
                <div>
                    <label for="ab" class="block text-xs font-medium text-gray-500">Menüs ab</label>
                    <input id="ab" name="ab" type="date" value="{{ $ab->format('Y-m-d') }}" onchange="this.form.submit()"
                           class="mt-1 rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
            </form>
            <div class="flex items-center gap-4">
                @darfRoute('module.schulkantine.dishes.menueserve.symbole')
                    <form method="POST" action="{{ route('module.schulkantine.dishes.menueserve.symbole') }}"
                          onsubmit="return confirm('Fleischarten aus allen bisherigen Menü&Serve-Menüs nachtragen? Gerichte mit eigener Fleischart bleiben unverändert.')">
                        @csrf
                        <button type="submit" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                                title="Fleischart (Symbol) aus der Menü&amp;Serve-Historie nachtragen – nur bei Gerichten ohne Angabe oder mit „Fleisch“">🐄🥦 Fleischarten nachholen</button>
                    </form>
                @enddarfRoute
                @darfRoute('module.schulkantine.dishes.menueserve.buchungen.uebernehmen')
                    <a href="{{ route('module.schulkantine.dishes.menueserve.buchungen', ['ab' => $ab->format('Y-m-d')]) }}"
                       class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Buchungen übernehmen</a>
                @enddarfRoute
                <a href="{{ route('module.schulkantine.dishes.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← zurück zu den Gerichten</a>
            </div>
        </div>

        @if (session('symbole_ergebnis'))
            @php $se = session('symbole_ergebnis'); @endphp
            <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm">
                <div class="font-semibold text-gray-700">Fleischarten aus Menü&amp;Serve: {{ count($se['gesetzt']) }} nachgetragen, {{ $se['behalten'] }} mit eigener Angabe behalten.</div>
                @if ($se['gesetzt'])
                    <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-gray-600">
                        @foreach ($se['gesetzt'] as $g)
                            <span>{{ $g['symbol'] }} {{ $g['name'] }}</span>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        <p class="text-sm text-gray-500">
            Je Menü&amp;Serve-Menü: welche Hauptspeise und welche Nachspeise aus unseren Gerichten ist es?
            Eindeutige Treffer sind vorausgewählt (<span class="text-sky-700">blau</span> = Vorschlag, noch nicht gespeichert);
            Snacks sind mit dem festen Snack-Gericht vorbelegt und kommen als Einzelgericht auf den Tagesplan.
            Gespeichert wird nur die Zuordnung – der Speiseplan bleibt unberührt.
        </p>

        @if ($fehler)
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">Menü&amp;Serve ist nicht lesbar: {{ $fehler }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
        @endif

        @if (session('speiseplan_ergebnis'))
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
                <div class="border-b border-gray-100 px-4 py-2 text-sm font-semibold text-gray-700">Übernahme in den Speiseplan</div>
                <ul class="divide-y divide-gray-100 text-sm">
                    @foreach (session('speiseplan_ergebnis') as $e)
                        <li class="flex flex-wrap gap-x-3 px-4 py-1.5">
                            <span class="w-24 text-gray-500">{{ $e['datum'] }}</span>
                            <span class="w-40 font-medium text-gray-900">{{ $e['titel'] }}</span>
                            <span class="{{ $e['ok'] ? 'text-green-700' : 'text-amber-700' }}">{{ $e['ok'] ? '✓' : '⚠️' }} {{ $e['text'] }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($menues)
            <form method="POST" action="{{ route('module.schulkantine.dishes.menueserve.save') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="ab" value="{{ $ab->format('Y-m-d') }}">

                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-100 text-left text-xs uppercase tracking-wide text-gray-400">
                                    <th class="px-4 py-2 font-medium">Tag</th>
                                    <th class="px-3 py-2 font-medium">Menü&amp;Serve</th>
                                    <th class="px-3 py-2 font-medium">Hauptspeise bei uns</th>
                                    <th class="px-3 py-2 font-medium">Nachspeise bei uns</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($menues as $m)
                                    <tr class="align-top">
                                        <td class="whitespace-nowrap px-4 py-3">
                                            <div class="font-medium text-gray-900">{{ \Illuminate\Support\Carbon::parse($m['datum'])->locale('de')->isoFormat('dd DD.MM.YYYY') }}</div>
                                            <div class="text-xs text-gray-400">{{ $m['linie'] }}</div>
                                        </td>
                                        <td class="px-3 py-3">
                                            <div class="font-medium text-gray-900">
                                                @if ($m['snack'])
                                                    <span class="mr-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">Snack</span>
                                                @endif
                                                {{ $m['titel'] }}
                                                @if ($m['art'])
                                                    <span class="ml-1 text-xs font-normal {{ $m['art'] === 7 ? 'text-green-700' : 'text-gray-500' }}">{{ $arten[$m['art']] }}</span>
                                                @endif
                                            </div>
                                            <div class="text-xs text-gray-500">Haupt: {{ $m['hauptspeise'] ?? '–' }}</div>
                                            <div class="text-xs text-gray-500">Nach: {{ $m['nachspeise'] ?? '–' }}</div>
                                        </td>
                                        @foreach (['hauptspeise_id', 'nachspeise_id'] as $feld)
                                            @php $gewaehlt = old('menue.'.$m['ms_id'].'.'.$feld, $m[$feld]); @endphp
                                            @if ($m['snack'] && $feld === 'nachspeise_id')
                                                <td class="px-3 py-3 text-xs text-gray-400">Snack: kommt als Einzelgericht auf den Tagesplan</td>
                                                @continue
                                            @endif
                                            <td class="px-3 py-3">
                                                <select name="menue[{{ $m['ms_id'] }}][{{ $feld }}]"
                                                        class="w-full min-w-[14rem] rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 {{ $gewaehlt && ! $m['gemerkt'] ? 'text-sky-700' : '' }}">
                                                    <option value="">– keine –</option>
                                                    @foreach ($gruppen as $kategorie => $liste)
                                                        <optgroup label="{{ $kategorie }}">
                                                            @foreach ($liste as $d)
                                                                <option value="{{ $d->id }}" @selected((int) $gewaehlt === $d->id)>{{ $d->name }}{{ $d->is_active ? '' : ' (inaktiv)' }}</option>
                                                            @endforeach
                                                        </optgroup>
                                                    @endforeach
                                                </select>
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="flex flex-wrap justify-end gap-2">
                    <button type="submit" name="aktion" value="speichern" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Zuordnung speichern</button>
                    <button type="submit" name="aktion" value="speiseplan"
                            onclick="return confirm('Zuordnung speichern und alle Menüs in den Speiseplan eintragen? Die Plätze für Haupt- und Nachspeise werden dort überschrieben.')"
                            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Speichern und in den Speiseplan übernehmen</button>
                </div>
            </form>
        @elseif (! $fehler)
            <div class="rounded-xl border border-gray-200 bg-white px-4 py-10 text-center text-sm text-gray-500">Ab {{ $ab->format('d.m.Y') }} gibt es in Menü&amp;Serve keine Menüs.</div>
        @endif
    </div>
</x-app-layout>
