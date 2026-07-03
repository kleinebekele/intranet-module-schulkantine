<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800">
            Kantinen-Daten: {{ $user->name }}
        </h1>
    </x-slot>

    @php
        $selAllergens = old('allergens', $selAllergens);
        $selDiets = old('diets', $selDiets);
    @endphp

    <div class="max-w-2xl space-y-6">
        {{-- Stammdaten (read-only – kommen aus dem Benutzer) --}}
        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm">
            <div class="flex flex-wrap items-center gap-x-6 gap-y-1">
                <div><span class="text-gray-400">Name:</span> <span class="font-medium text-gray-800">{{ $user->name }}</span></div>
                <div><span class="text-gray-400">E-Mail:</span> <span class="text-gray-700">{{ $user->email }}</span></div>
            </div>
            <p class="mt-2 text-xs text-gray-400">Name und E-Mail werden im Benutzer-Bereich gepflegt, nicht hier.</p>
        </div>

        <form method="POST"
              action="{{ route('module.schulkantine.eaters.update', $user) }}"
              class="space-y-6 rounded-xl border border-gray-200 bg-white p-6">
            @csrf
            @method('PUT')

            {{-- Kundengruppe je Saison --}}
            <div>
                <x-input-label for="group_id" value="Kundengruppe" />
                <select id="group_id" name="group_id" @disabled(! $activeSeason)
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100">
                    <option value="">— keine (nimmt nicht teil) —</option>
                    @foreach ($groups as $group)
                        <option value="{{ $group->id }}" @selected((int) old('group_id', $currentGroupId) === $group->id)>{{ $group->name }}</option>
                    @endforeach
                </select>
                @if ($activeSeason)
                    <p class="mt-1 text-xs text-gray-400">Für die aktive Saison „{{ $activeSeason->name }}". Ohne Gruppe nimmt der Benutzer diese Saison nicht an der Kantine teil.</p>
                @else
                    <p class="mt-1 text-xs text-amber-600">Keine aktive Saison – Gruppe kann erst danach gesetzt werden.</p>
                @endif
                <x-input-error :messages="$errors->get('group_id')" class="mt-2" />
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

            <div class="flex items-center gap-3 pt-2">
                <x-primary-button class="gap-1.5">
                    <x-module-icon name="save" class="text-base" />
                    Speichern
                </x-primary-button>
                <a href="{{ route('module.schulkantine.eaters.index') }}"
                   class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700">
                    <x-module-icon name="x" class="text-base" />
                    Abbrechen
                </a>
            </div>
        </form>
    </div>
</x-app-layout>
