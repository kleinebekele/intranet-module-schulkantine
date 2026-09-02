<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div class="flex items-center gap-2">
                <x-module-icon name="calendar" class="text-2xl text-indigo-600" />
                <h1 class="text-xl font-semibold text-gray-800">{{ $season->name }}</h1>
                @if ($season->is_active)
                    <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">aktiv</span>
                @endif
            </div>
            <a href="{{ route('module.schulkantine.seasons.index') }}"
               class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                <x-module-icon name="back" class="text-base" />
                Zurück
            </a>
        </div>
    </x-slot>

    @php
        $wt = [1 => 'Mo', 2 => 'Di', 3 => 'Mi', 4 => 'Do', 5 => 'Fr', 6 => 'Sa', 7 => 'So'];
        $typeStyles = [
            'feiertag' => 'bg-rose-50 text-rose-700',
            'ferien' => 'bg-amber-50 text-amber-700',
            'sonstiges' => 'bg-gray-100 text-gray-600',
        ];
        $typeLabels = ['feiertag' => 'Feiertag', 'ferien' => 'Ferien', 'sonstiges' => 'Sonstiges'];
        // Aktiver Tab: aus ?tab, sonst bei Validierungsfehlern der passende Tab
        // (damit die Fehlermeldung im richtigen Tab sichtbar ist).
        if (in_array(request('tab'), ['schliesstage', 'menues', 'einstellungen'], true)) {
            $initialTab = request('tab');
        } elseif ($errors->hasAny(['slots', 'price', 'weekdays'])) {
            $initialTab = 'menues';
        } elseif ($errors->hasAny(['start_date', 'end_date', 'bundesland', 'ogs_price', 'opening_weekdays', 'order_deadline_time', 'cancel_deadline_time', 'release_lead_weeks'])) {
            $initialTab = 'einstellungen';
        } else {
            $initialTab = 'schliesstage';
        }
    @endphp

    <div class="max-w-4xl space-y-6" x-data="{ tab: @js($initialTab) }">
        @if (session('error'))
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        {{-- Tab-Umschalter --}}
        <div class="inline-flex rounded-lg border border-gray-200 bg-white p-0.5 text-sm">
            <button type="button" @click="tab = 'schliesstage'"
                    :class="tab === 'schliesstage' ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-50'"
                    class="rounded-md px-3 py-1.5 font-medium">Schließtage</button>
            <button type="button" @click="tab = 'menues'"
                    :class="tab === 'menues' ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-50'"
                    class="rounded-md px-3 py-1.5 font-medium">Menüs</button>
            <button type="button" @click="tab = 'einstellungen'"
                    :class="tab === 'einstellungen' ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-50'"
                    class="rounded-md px-3 py-1.5 font-medium">Einstellungen</button>
        </div>

        {{-- ========================= TAB 1: SCHLIESSTAGE ========================= --}}
        <div x-show="tab === 'schliesstage'" class="space-y-6">
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
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <h2 class="text-base font-semibold text-gray-800">Schließtage ({{ $season->closedDays->count() }})</h2>
                    <form method="POST" action="{{ route('module.schulkantine.seasons.import', $season) }}" class="shrink-0">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                                @disabled(! $season->bundesland)>
                            <x-module-icon name="download" class="text-base" />
                            Ferien &amp; Feiertage importieren
                        </button>
                        @unless ($season->bundesland)
                            <p class="mt-1 text-right text-xs text-gray-400">Zuerst ein Bundesland hinterlegen (Einstellungen).</p>
                        @endunless
                    </form>
                </div>

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
        </div>

        {{-- ============================ TAB 2: MENÜS ============================ --}}
        <div x-show="tab === 'menues'" x-cloak class="space-y-6">
            <p class="text-sm text-gray-500">
                Ein Menü ist eine Hülle mit Name, Preis und Kategorie-Slots (aus welcher Kategorie wie viele Gerichte).
                <strong>Welche</strong> Gerichte konkret enthalten sind, wählst du je Öffnungstag im Speiseplan.
            </p>

            {{-- Vorhandene Menüs --}}
            <div class="rounded-xl border border-gray-200 bg-white p-6">
                <h2 class="text-base font-semibold text-gray-800">Menüs dieser Saison ({{ $season->menuTemplates->count() }})</h2>
                @if ($season->menuTemplates->isEmpty())
                    <p class="mt-3 text-sm text-gray-500">Noch keine Menüs angelegt.</p>
                @else
                    <ul class="mt-3 divide-y divide-gray-100">
                        @foreach ($season->menuTemplates as $t)
                            @php
                                $tage = ($t->weekdays ?: []) === []
                                    ? 'alle Öffnungstage'
                                    : collect($t->weekdays)->map(fn ($n) => $wt[$n] ?? $n)->implode(', ');
                                $slots = $t->slots->map(fn ($s) => $s->quantity.'× '.($s->category?->name ?? '—'))->implode(', ');
                            @endphp
                            <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="font-semibold text-gray-800">{{ $t->name }}</span>
                                        <span class="text-sm font-bold text-indigo-700">{{ number_format((float) $t->price, 2, ',', '.') }} €</span>
                                        @unless ($t->is_active)
                                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500">inaktiv</span>
                                        @endunless
                                    </div>
                                    <div class="mt-0.5 text-xs text-gray-500">{{ $slots ?: 'keine Slots' }}</div>
                                    <div class="text-xs text-gray-400">Angeboten: {{ $tage }}</div>
                                </div>
                                <div class="flex shrink-0 items-center gap-1">
                                    <a href="{{ route('module.schulkantine.menu-templates.edit', $t) }}" title="Menü bearbeiten"
                                       class="inline-flex items-center rounded-md p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-700">
                                        <x-module-icon name="edit" class="text-base" />
                                    </a>
                                    <form method="POST" action="{{ route('module.schulkantine.menu-templates.destroy', $t) }}"
                                          onsubmit="return confirm('Menü „{{ $t->name }}“ löschen?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" title="Menü löschen"
                                                class="inline-flex items-center rounded-md p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600">
                                            <x-module-icon name="trash" class="text-base" />
                                        </button>
                                    </form>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            {{-- Neues Menü anlegen --}}
            @include('schulkantine::seasons._menu-form')
        </div>

        {{-- ========================= TAB 3: EINSTELLUNGEN ========================= --}}
        <div x-show="tab === 'einstellungen'" x-cloak class="space-y-6">
            @include('schulkantine::seasons._settings-form')

            {{-- Gefahrenzone: Saison löschen --}}
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
    </div>
</x-app-layout>
