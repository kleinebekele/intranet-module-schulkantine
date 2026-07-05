<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800">
            Kundengruppe: {{ $group->name }}
        </h1>
    </x-slot>

    <div class="max-w-2xl space-y-6">
        {{-- Feste Eckdaten (nicht änderbar) --}}
        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm">
            <div class="flex flex-wrap items-center gap-x-6 gap-y-1">
                <div><span class="text-gray-400">Name:</span> <span class="font-medium text-gray-800">{{ $group->name }}</span></div>
                <div><span class="text-gray-400">Rolle:</span>
                    <span class="inline-flex rounded-full bg-violet-50 px-2 py-0.5 text-xs font-medium text-violet-700">{{ $group->role_id }}</span>
                </div>
            </div>
            <p class="mt-2 text-xs text-gray-400">Name und Rolle sind feste Konventionen und können nicht geändert werden.</p>
        </div>

        <form method="POST"
              action="{{ route('module.schulkantine.customer-groups.update', $group) }}"
              class="space-y-5 rounded-xl border border-gray-200 bg-white p-6">
            @csrf
            @method('PUT')

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

            <div class="flex items-center gap-3 pt-2">
                <button type="submit"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    <x-module-icon name="save" class="text-base" />
                    Speichern
                </button>
                <a href="{{ route('module.schulkantine.customer-groups.index') }}"
                   class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700">
                    <x-module-icon name="x" class="text-base" />
                    Abbrechen
                </a>
            </div>
        </form>
    </div>
</x-app-layout>
