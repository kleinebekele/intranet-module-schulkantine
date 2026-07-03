<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800">
            {{ $group->exists ? 'Kundengruppe bearbeiten' : 'Neue Kundengruppe' }}
        </h1>
    </x-slot>

    <div class="max-w-2xl space-y-6">
        <form method="POST"
              action="{{ $group->exists ? route('module.schulkantine.customer-groups.update', $group) : route('module.schulkantine.customer-groups.store') }}"
              class="space-y-5 rounded-xl border border-gray-200 bg-white p-6">
            @csrf
            @if ($group->exists) @method('PUT') @endif

            <div>
                <x-input-label for="name" value="Name der Gruppe" />
                <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                              :value="old('name', $group->name)" placeholder="z. B. OGS Grundschule, Personal, Kita …" required />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="ordering_mode" value="Bestellmodus" />
                <select id="ordering_mode" name="ordering_mode"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @foreach ($modes as $value => $label)
                        <option value="{{ $value }}" @selected(old('ordering_mode', $group->ordering_mode) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-400">„Essen ja/nein" z. B. für OGS-Kinder, „Menü-Auswahl" für die Größeren.</p>
                <x-input-error :messages="$errors->get('ordering_mode')" class="mt-2" />
            </div>

            <div>
                <x-input-label value="Ausgabe-Zeitfenster (optional)" />
                <div class="mt-1 flex items-center gap-2">
                    <x-text-input name="pickup_from" type="time" class="block" :value="old('pickup_from', $group->pickup_from)" />
                    <span class="text-sm text-gray-400">bis</span>
                    <x-text-input name="pickup_to" type="time" class="block" :value="old('pickup_to', $group->pickup_to)" />
                </div>
                <p class="mt-1 text-xs text-gray-400">Wann diese Gruppe ihr Essen bekommt (z. B. OGS zu eigener Zeit).</p>
                <x-input-error :messages="$errors->get('pickup_from')" class="mt-2" />
                <x-input-error :messages="$errors->get('pickup_to')" class="mt-2" />
            </div>

            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $group->is_active))
                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                Gruppe ist aktiv
            </label>

            <div class="flex items-center gap-3 pt-2">
                <x-primary-button class="gap-1.5">
                    <x-module-icon name="{{ $group->exists ? 'save' : 'plus' }}" class="text-base" />
                    {{ $group->exists ? 'Speichern' : 'Gruppe anlegen' }}
                </x-primary-button>
                <a href="{{ route('module.schulkantine.customer-groups.index') }}"
                   class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700">
                    <x-module-icon name="x" class="text-base" />
                    Abbrechen
                </a>
            </div>
        </form>

        @if ($group->exists)
            {{-- Gefahrenzone --}}
            <div class="rounded-xl border border-red-200 bg-red-50 p-6">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h2 class="text-base font-semibold text-red-800">Gefahrenzone</h2>
                        <p class="mt-1 text-sm text-red-600">Diese Kundengruppe dauerhaft löschen.</p>
                    </div>

                    <div x-data="{ open: false }" class="relative">
                        <button type="button" @click="open = ! open"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-red-300 bg-white px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-100">
                            <x-module-icon name="trash" class="text-base" />
                            Gruppe löschen
                        </button>

                        <div x-show="open" style="display: none;" @click.outside="open = false"
                             x-transition.origin.bottom.left
                             class="absolute bottom-full left-0 z-50 mb-2 w-72 rounded-xl border border-gray-200 bg-white p-4 text-left shadow-lg">
                            <p class="text-sm font-semibold text-gray-800">Wirklich löschen?</p>
                            <p class="mt-1 text-xs text-gray-500">„{{ $group->name }}" wird entfernt.</p>
                            <div class="mt-4 flex justify-end gap-2">
                                <button type="button" @click="open = false"
                                        class="rounded-lg px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100">Abbrechen</button>
                                <form method="POST" action="{{ route('module.schulkantine.customer-groups.destroy', $group) }}">
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
