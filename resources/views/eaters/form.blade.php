<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800">
            {{ $eater->exists ? 'Teilnehmer bearbeiten' : 'Neuer Teilnehmer' }}
        </h1>
    </x-slot>

    @php
        $selGuardians = old('guardians', $eater->exists ? $eater->guardians->pluck('id')->all() : []);
        $selAllergens = old('allergens', $eater->exists ? $eater->allergens->pluck('id')->all() : []);
        $selDiets = old('diets', $eater->exists ? $eater->diets->pluck('id')->all() : []);
    @endphp

    <div class="max-w-2xl space-y-6">
        <form method="POST"
              action="{{ $eater->exists ? route('module.schulkantine.eaters.update', $eater) : route('module.schulkantine.eaters.store') }}"
              class="space-y-6 rounded-xl border border-gray-200 bg-white p-6">
            @csrf
            @if ($eater->exists) @method('PUT') @endif

            <div>
                <x-input-label for="name" value="Name des Teilnehmers" />
                <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                              :value="old('name', $eater->name)" placeholder="z. B. Max Mustermann" required />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="user_id" value="Eigenes Login / aus Benutzer übernehmen (optional)" />
                    <select id="user_id" name="user_id"
                            onchange="if (this.value &amp;&amp; !document.getElementById('name').value) { document.getElementById('name').value = this.selectedOptions[0].dataset.name; }"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">— kein eigenes Login —</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" data-name="{{ $user->name }}" @selected((int) old('user_id', $eater->user_id) === $user->id)>{{ $user->name }} ({{ $user->email }})</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-400">Für Esser, die selbst bestellen (Größere/Personal). Bei Auswahl wird der Name übernommen, falls noch leer.</p>
                    <x-input-error :messages="$errors->get('user_id')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="group_id" value="Kundengruppe" />
                    <select id="group_id" name="group_id" @disabled(! $activeSeason)
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100">
                        <option value="">— keine —</option>
                        @foreach ($groups as $group)
                            <option value="{{ $group->id }}" @selected((int) old('group_id', $currentGroupId) === $group->id)>{{ $group->name }}</option>
                        @endforeach
                    </select>
                    @if ($activeSeason)
                        <p class="mt-1 text-xs text-gray-400">Für die aktive Saison „{{ $activeSeason->name }}".</p>
                    @else
                        <p class="mt-1 text-xs text-amber-600">Keine aktive Saison – Gruppe kann erst danach gesetzt werden.</p>
                    @endif
                    <x-input-error :messages="$errors->get('group_id')" class="mt-2" />
                </div>
            </div>

            {{-- Vormunde --}}
            <div>
                <x-input-label value="Vormunde / Eltern (dürfen für diesen Esser bestellen)" />
                <div class="mt-2 max-h-48 space-y-1 overflow-y-auto rounded-lg border border-gray-200 p-3">
                    @forelse ($users as $user)
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="guardians[]" value="{{ $user->id }}" @checked(in_array($user->id, $selGuardians))
                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span>{{ $user->name }} <span class="text-xs text-gray-400">{{ $user->email }}</span></span>
                        </label>
                    @empty
                        <p class="text-sm text-gray-400">Keine Benutzer vorhanden.</p>
                    @endforelse
                </div>
            </div>

            {{-- Sonderkost: Allergene --}}
            <div>
                <x-input-label value="Allergien (verträgt diese Allergene NICHT)" />
                <div class="mt-2 grid grid-cols-1 gap-x-4 gap-y-2 sm:grid-cols-2">
                    @foreach ($allergens as $allergen)
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="allergens[]" value="{{ $allergen->id }}" @checked(in_array($allergen->id, $selAllergens))
                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span><span class="font-medium text-gray-400">{{ $allergen->code }}</span> {{ $allergen->name }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- Sonderkost: Diäten --}}
            <div>
                <x-input-label value="Diäten (Essen muss dafür geeignet sein)" />
                <div class="mt-2 flex flex-wrap gap-x-4 gap-y-2">
                    @foreach ($diets as $diet)
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="diets[]" value="{{ $diet->id }}" @checked(in_array($diet->id, $selDiets))
                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            {{ $diet->name }}
                        </label>
                    @endforeach
                </div>
            </div>

            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $eater->is_active))
                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                Teilnehmer ist aktiv
            </label>

            <div class="flex items-center gap-3 pt-2">
                <x-primary-button class="gap-1.5">
                    <x-module-icon name="{{ $eater->exists ? 'save' : 'plus' }}" class="text-base" />
                    {{ $eater->exists ? 'Speichern' : 'Teilnehmer anlegen' }}
                </x-primary-button>
                <a href="{{ route('module.schulkantine.eaters.index') }}"
                   class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700">
                    <x-module-icon name="x" class="text-base" />
                    Abbrechen
                </a>
            </div>
        </form>

        @if ($eater->exists)
            {{-- Gefahrenzone --}}
            <div class="rounded-xl border border-red-200 bg-red-50 p-6">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h2 class="text-base font-semibold text-red-800">Gefahrenzone</h2>
                        <p class="mt-1 text-sm text-red-600">Diesen Teilnehmer dauerhaft löschen.</p>
                    </div>

                    <div x-data="{ open: false }" class="relative">
                        <button type="button" @click="open = ! open"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-red-300 bg-white px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-100">
                            <x-module-icon name="trash" class="text-base" />
                            Teilnehmer löschen
                        </button>

                        <div x-show="open" style="display: none;" @click.outside="open = false"
                             x-transition.origin.bottom.left
                             class="absolute bottom-full left-0 z-50 mb-2 w-72 rounded-xl border border-gray-200 bg-white p-4 text-left shadow-lg">
                            <p class="text-sm font-semibold text-gray-800">Wirklich löschen?</p>
                            <p class="mt-1 text-xs text-gray-500">„{{ $eater->name }}" wird entfernt.</p>
                            <div class="mt-4 flex justify-end gap-2">
                                <button type="button" @click="open = false"
                                        class="rounded-lg px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100">Abbrechen</button>
                                <form method="POST" action="{{ route('module.schulkantine.eaters.destroy', $eater) }}">
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
        @endif
    </div>
</x-app-layout>
