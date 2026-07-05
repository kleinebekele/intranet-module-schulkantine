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
