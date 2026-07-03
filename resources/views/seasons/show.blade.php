<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div class="flex items-center gap-2">
                <x-module-icon name="calendar" class="text-2xl text-indigo-600" />
                <h1 class="text-xl font-semibold text-gray-800">{{ $season->name }}</h1>
                @if ($season->is_active)
                    <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">aktiv</span>
                @endif
                <a href="{{ route('module.schulkantine.seasons.edit', $season) }}" title="Saison bearbeiten"
                   class="inline-flex items-center rounded-md p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-700">
                    <x-module-icon name="edit" class="text-lg" />
                </a>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('module.schulkantine.seasons.index') }}"
                   class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    <x-module-icon name="back" class="text-base" />
                    Zurück
                </a>
            </div>
        </div>
    </x-slot>

    @php
        $wt = [1 => 'Mo', 2 => 'Di', 3 => 'Mi', 4 => 'Do', 5 => 'Fr', 6 => 'Sa', 7 => 'So'];
        $offen = collect($season->opening_weekdays ?: [1, 2, 3, 4])->map(fn ($n) => $wt[$n] ?? $n)->implode(', ');
        $typeStyles = [
            'feiertag' => 'bg-rose-50 text-rose-700',
            'ferien' => 'bg-amber-50 text-amber-700',
            'sonstiges' => 'bg-gray-100 text-gray-600',
        ];
        $typeLabels = ['feiertag' => 'Feiertag', 'ferien' => 'Ferien', 'sonstiges' => 'Sonstiges'];
    @endphp

    <div class="max-w-4xl space-y-6">
        @if (session('error'))
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        {{-- Eckdaten + Ferien-Import --}}
        <div class="rounded-xl border border-gray-200 bg-white p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="space-y-1 text-sm text-gray-600">
                    <p><span class="text-gray-400">Zeitraum:</span> {{ $season->start_date->format('d.m.Y') }} &ndash; {{ $season->end_date->format('d.m.Y') }}</p>
                    <p><span class="text-gray-400">Bundesland:</span> {{ $bundeslandName ?: '—' }}</p>
                    <p><span class="text-gray-400">Öffnungstage:</span> {{ $offen }}</p>
                </div>
                <form method="POST" action="{{ route('module.schulkantine.seasons.import', $season) }}">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                            @disabled(! $season->bundesland)>
                        <x-module-icon name="download" class="text-base" />
                        Ferien &amp; Feiertage importieren
                    </button>
                    @unless ($season->bundesland)
                        <p class="mt-1 text-xs text-gray-400">Zuerst ein Bundesland hinterlegen.</p>
                    @endunless
                </form>
            </div>
        </div>

        {{-- Schließtag manuell hinzufügen --}}
        <div class="rounded-xl border border-gray-200 bg-white p-6">
            <h2 class="text-base font-semibold text-gray-800">Schließtag hinzufügen</h2>
            <form method="POST" action="{{ route('module.schulkantine.seasons.closed-days.store', $season) }}"
                  class="mt-3 flex flex-wrap items-end gap-3">
                @csrf
                <div>
                    <x-input-label for="date_from" value="Von" />
                    <x-text-input id="date_from" name="date_from" type="date" class="mt-1 block"
                                  min="{{ $season->start_date->format('Y-m-d') }}"
                                  max="{{ $season->end_date->format('Y-m-d') }}" required />
                </div>
                <div>
                    <x-input-label for="date_to" value="Bis (optional)" />
                    <x-text-input id="date_to" name="date_to" type="date" class="mt-1 block"
                                  min="{{ $season->start_date->format('Y-m-d') }}"
                                  max="{{ $season->end_date->format('Y-m-d') }}" />
                </div>
                <div>
                    <x-input-label for="type" value="Art" />
                    <select id="type" name="type"
                            class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="feiertag">Feiertag</option>
                        <option value="ferien">Ferien</option>
                        <option value="sonstiges" selected>Sonstiges</option>
                    </select>
                </div>
                <div class="grow">
                    <x-input-label for="reason" value="Grund (optional)" />
                    <x-text-input id="reason" name="reason" type="text" class="mt-1 block w-full" placeholder="z. B. beweglicher Ferientag" />
                </div>
                <button type="submit"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    <x-module-icon name="plus" class="text-base" />
                    Hinzufügen
                </button>
            </form>
            <p class="mt-2 text-xs text-gray-400">„Bis" leer lassen = einzelner Tag. Es sind nur Tage innerhalb der Saison wählbar.</p>
            <x-input-error :messages="$errors->get('date_from')" class="mt-2" />
            <x-input-error :messages="$errors->get('date_to')" class="mt-2" />
        </div>

        {{-- Tabelle der Schließtage --}}
        <div class="rounded-xl border border-gray-200 bg-white p-6">
            <h2 class="text-base font-semibold text-gray-800">Schließtage ({{ $season->closedDays->count() }})</h2>

            @if ($season->closedDays->isEmpty())
                <p class="mt-3 text-sm text-gray-500">Noch keine Schließtage. Importiere Ferien &amp; Feiertage oder füge sie manuell hinzu.</p>
            @else
                <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-left text-xs font-medium uppercase tracking-wide text-gray-400">
                                <th class="px-3 py-2">Datum</th>
                                <th class="px-3 py-2">Tag</th>
                                <th class="px-3 py-2">Art</th>
                                <th class="px-3 py-2">Grund</th>
                                <th class="px-3 py-2">Quelle</th>
                                <th class="px-3 py-2 text-right">Aktion</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($season->closedDays as $tag)
                                <tr class="hover:bg-gray-50">
                                    <td class="whitespace-nowrap px-3 py-2 font-medium text-gray-800">{{ $tag->date->format('d.m.Y') }}</td>
                                    <td class="px-3 py-2 text-gray-500">{{ $wt[$tag->date->dayOfWeekIso] ?? '' }}</td>
                                    <td class="px-3 py-2">
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $typeStyles[$tag->type] ?? 'bg-gray-100 text-gray-600' }}">
                                            {{ $typeLabels[$tag->type] ?? ucfirst($tag->type) }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-gray-600">{{ $tag->reason ?: '—' }}</td>
                                    <td class="px-3 py-2">
                                        @if ($tag->source === 'api')
                                            <span class="inline-flex rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-600">API</span>
                                        @else
                                            <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500">manuell</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        <form method="POST" action="{{ route('module.schulkantine.seasons.closed-days.destroy', [$season, $tag]) }}">
                                            @csrf @method('DELETE')
                                            <button type="submit" title="Schließtag entfernen"
                                                    class="inline-flex items-center rounded-md p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600">
                                                <x-module-icon name="trash" class="text-base" />
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Gefahrenzone: Saison löschen (mit Bestätigungs-Popover statt JS-confirm) --}}
        <div class="rounded-xl border border-red-200 bg-red-50 p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="text-base font-semibold text-red-800">Gefahrenzone</h2>
                    <p class="mt-1 text-sm text-red-600">
                        Die Saison und alle {{ $season->closedDays->count() }} Schließtage werden dauerhaft gelöscht.
                        Diese Aktion kann nicht rückgängig gemacht werden.
                    </p>
                </div>

                <div x-data="{ open: false }" class="relative">
                    <button type="button" @click="open = ! open"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-red-300 bg-white px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-100">
                        <x-module-icon name="trash" class="text-base" />
                        Saison löschen
                    </button>

                    <div x-show="open" style="display: none;" @click.outside="open = false"
                         x-transition.origin.bottom.left
                         class="absolute bottom-full left-0 z-50 mb-2 w-72 rounded-xl border border-gray-200 bg-white p-4 text-left shadow-lg">
                        <p class="text-sm font-semibold text-gray-800">Wirklich unwiderruflich löschen?</p>
                        <p class="mt-1 text-xs text-gray-500">„{{ $season->name }}" inklusive aller Schließtage.</p>
                        <div class="mt-4 flex justify-end gap-2">
                            <button type="button" @click="open = false"
                                    class="rounded-lg px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100">
                                Abbrechen
                            </button>
                            <form method="POST" action="{{ route('module.schulkantine.seasons.destroy', $season) }}">
                                @csrf @method('DELETE')
                                <button type="submit"
                                        class="inline-flex items-center gap-1.5 rounded-lg bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700">
                                    <x-module-icon name="trash" class="text-base" />
                                    Endgültig löschen
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
