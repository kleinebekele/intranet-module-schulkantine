<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800">
            Sonderkost: {{ $user->name }}
        </h1>
    </x-slot>

    @php
        $selAllergens = old('allergens', $selAllergens);
        $selDiets = old('diets', $selDiets);
    @endphp

    <div class="max-w-2xl space-y-6">
        {{-- Stammdaten & abgeleitete Gruppe (read-only) --}}
        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm">
            <div class="flex flex-wrap items-center gap-x-6 gap-y-1">
                <div><span class="text-gray-400">Name:</span> <span class="font-medium text-gray-800">{{ $user->name }}</span></div>
                <div><span class="text-gray-400">E-Mail:</span> <span class="text-gray-700">{{ $user->email }}</span></div>
                <div><span class="text-gray-400">Gruppe:</span>
                    @if ($group)
                        <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">{{ $group->name }}</span>
                    @else
                        <span class="text-gray-300">—</span>
                    @endif
                </div>
            </div>
            <p class="mt-2 text-xs text-gray-400">Name/E-Mail werden im Benutzer-Bereich gepflegt; die Gruppe ergibt sich aus der Rolle des Benutzers.</p>
        </div>

        <form method="POST"
              action="{{ route('module.schulkantine.eaters.update', $user) }}"
              class="space-y-6 rounded-xl border border-gray-200 bg-white p-6">
            @csrf
            @method('PUT')

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
