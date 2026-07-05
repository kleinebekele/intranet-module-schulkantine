<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800">
            {{ $season->exists ? 'Saison bearbeiten' : 'Neue Saison' }}
        </h1>
    </x-slot>

    <div class="max-w-2xl">
        <form method="POST"
              action="{{ $season->exists ? route('module.schulkantine.seasons.update', $season) : route('module.schulkantine.seasons.store') }}"
              class="space-y-5 rounded-xl border border-gray-200 bg-white p-6">
            @csrf
            @if ($season->exists) @method('PUT') @endif

            <div>
                <x-input-label for="name" value="Name der Saison" />
                <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                              :value="old('name', $season->name)" placeholder="Schuljahr 2026/2027" required />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="start_date" value="Beginn" />
                    <x-text-input id="start_date" name="start_date" type="date" class="mt-1 block w-full"
                                  :value="old('start_date', optional($season->start_date)->format('Y-m-d'))" required />
                    <x-input-error :messages="$errors->get('start_date')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="end_date" value="Ende" />
                    <x-text-input id="end_date" name="end_date" type="date" class="mt-1 block w-full"
                                  :value="old('end_date', optional($season->end_date)->format('Y-m-d'))" required />
                    <x-input-error :messages="$errors->get('end_date')" class="mt-2" />
                </div>
            </div>

            <div>
                <x-input-label for="bundesland" value="Bundesland (für den Ferien-Import)" />
                <select id="bundesland" name="bundesland"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">&mdash; bitte wählen &mdash;</option>
                    @foreach ($bundeslaender as $code => $label)
                        <option value="{{ $code }}" @selected(old('bundesland', $season->bundesland) === $code)>{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('bundesland')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="ogs_price" value="OGS-Essen: Fixpreis (€)" />
                <x-text-input id="ogs_price" name="ogs_price" type="number" step="0.01" min="0" class="mt-1 block w-40"
                              :value="old('ogs_price', $season->ogs_price)" placeholder="z. B. 3.50" />
                <p class="mt-1 text-xs text-gray-400">
                    Einheitlicher Preis für ein OGS-Essen in dieser Saison – gilt <strong>global</strong> für alle OGS-Kinder
                    (die essen pauschal, ohne Gericht-Auswahl). Leer lassen, wenn noch nicht festgelegt.
                </p>
                <x-input-error :messages="$errors->get('ogs_price')" class="mt-2" />
            </div>

            <div>
                <x-input-label value="Öffnungs-Wochentage" />
                @php
                    $wochentage = [1 => 'Mo', 2 => 'Di', 3 => 'Mi', 4 => 'Do', 5 => 'Fr', 6 => 'Sa', 7 => 'So'];
                    $gewaehlt = old('opening_weekdays', $season->opening_weekdays ?: [1, 2, 3, 4]);
                @endphp
                <div class="mt-2 flex flex-wrap gap-3">
                    @foreach ($wochentage as $nr => $label)
                        <label class="inline-flex items-center gap-1.5 text-sm text-gray-700">
                            <input type="checkbox" name="opening_weekdays[]" value="{{ $nr }}"
                                   @checked(in_array($nr, $gewaehlt))
                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
                <p class="mt-1 text-xs text-gray-400">Tage, an denen die Kantine grundsätzlich geöffnet hat.</p>
            </div>

            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $season->is_active))
                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                Diese Saison ist aktiv (es kann nur eine aktiv sein)
            </label>

            @if ($season->exists && isset($settings))
                {{-- Globale Bestell-Einstellungen. Gelten übergreifend (nicht je Saison),
                     werden aber hier gepflegt, damit es kein eigenes Einstellungen-Menü braucht. --}}
                <div class="border-t border-gray-100 pt-5">
                    <h2 class="text-sm font-semibold text-gray-700">Bestell-Fristen &amp; Freigabe</h2>
                    <p class="mt-0.5 text-xs text-gray-400">
                        Diese Werte gelten <strong>übergreifend</strong> für die gesamte Kantine, nicht nur für diese Saison.
                        Beide Fristen werden gegen den Öffnungskalender gerechnet (geschlossene Tage werden übersprungen).
                    </p>

                    <div class="mt-3 grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="order_deadline_time" value="Bestellschluss (voriger Öffnungstag)" />
                            <x-text-input id="order_deadline_time" name="order_deadline_time" type="time" class="mt-1 block"
                                          :value="old('order_deadline_time', $settings->order_deadline_time)" required />
                            <p class="mt-1 text-xs text-gray-400">Bis wann am Vortag (Öffnungstag) bestellt/geändert werden darf. Standard 14:00.</p>
                            <x-input-error :messages="$errors->get('order_deadline_time')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="cancel_deadline_time" value="Abbestellen (am selben Tag)" />
                            <x-text-input id="cancel_deadline_time" name="cancel_deadline_time" type="time" class="mt-1 block"
                                          :value="old('cancel_deadline_time', $settings->cancel_deadline_time)" required />
                            <p class="mt-1 text-xs text-gray-400">Bis wann am Essenstag noch abbestellt werden darf (z. B. Kind krank). Standard 09:00.</p>
                            <x-input-error :messages="$errors->get('cancel_deadline_time')" class="mt-2" />
                        </div>
                    </div>

                    <div class="mt-4">
                        <x-input-label for="release_lead_weeks" value="Automatischer Freigabe-Vorlauf (Wochen)" />
                        <x-text-input id="release_lead_weeks" name="release_lead_weeks" type="number" min="0" max="52" class="mt-1 block w-32"
                                      :value="old('release_lead_weeks', $settings->release_lead_weeks)" required />
                        <p class="mt-1 text-xs text-gray-400">
                            Wie viele Wochen im Voraus automatisch zum Bestellen freigegeben werden. Einzelne Wochen lassen sich
                            im Speiseplan manuell früher freigeben oder zurückhalten.
                        </p>
                        <x-input-error :messages="$errors->get('release_lead_weeks')" class="mt-2" />
                    </div>
                </div>
            @endif

            <div class="flex items-center gap-3 pt-2">
                <x-primary-button class="gap-1.5">
                    <x-module-icon name="{{ $season->exists ? 'save' : 'plus' }}" class="text-base" />
                    {{ $season->exists ? 'Speichern' : 'Saison anlegen' }}
                </x-primary-button>
                <a href="{{ route('module.schulkantine.seasons.index') }}"
                   class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700">
                    <x-module-icon name="x" class="text-base" />
                    Abbrechen
                </a>
            </div>
        </form>
    </div>
</x-app-layout>
