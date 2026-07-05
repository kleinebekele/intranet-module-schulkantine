<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800">
            {{ $dish->exists ? 'Gericht bearbeiten' : 'Neues Gericht' }}
        </h1>
    </x-slot>

    @php
        $selAllergens = old('allergens', $dish->exists ? $dish->allergens->pluck('id')->all() : []);
        $selAdditives = old('additives', $dish->exists ? $dish->additives->pluck('id')->all() : []);
        $selDiets = old('diets', $dish->exists ? $dish->unsuitableDiets->pluck('id')->all() : []);
    @endphp

    <div class="max-w-2xl space-y-6">
        <form method="POST"
              action="{{ $dish->exists ? route('module.schulkantine.dishes.update', $dish) : route('module.schulkantine.dishes.store') }}"
              enctype="multipart/form-data"
              class="space-y-6 rounded-xl border border-gray-200 bg-white p-6">
            @csrf
            @if ($dish->exists) @method('PUT') @endif

            <div>
                <x-input-label for="name" value="Name des Gerichts" />
                <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                              :value="old('name', $dish->name)" placeholder="z. B. Spaghetti Bolognese" required />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="category_id" value="Kategorie" />
                    <select id="category_id" name="category_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">— keine —</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected((int) old('category_id', $dish->category_id) === $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('category_id')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="price" value="Preis (€)" />
                    <x-text-input id="price" name="price" type="number" step="0.01" min="0" class="mt-1 block w-full"
                                  :value="old('price', $dish->price)" required />
                    <x-input-error :messages="$errors->get('price')" class="mt-2" />
                </div>
            </div>

            <div>
                <x-input-label for="description" value="Beschreibung (optional)" />
                <textarea id="description" name="description" rows="3"
                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                          placeholder="kurze Beschreibung, Zutaten …">{{ old('description', $dish->description) }}</textarea>
                <x-input-error :messages="$errors->get('description')" class="mt-2" />
            </div>

            {{-- Foto --}}
            <div>
                <x-input-label value="Foto (optional)" />
                @if ($dish->photoUrl())
                    <div class="mt-2 flex items-center gap-4">
                        <img src="{{ $dish->photoUrl() }}" alt="{{ $dish->name }}"
                             class="h-32 w-32 rounded-lg border border-gray-200 object-cover">
                        <label class="inline-flex items-center gap-2 text-sm text-gray-600">
                            <input type="checkbox" name="remove_photo" value="1"
                                   class="rounded border-gray-300 text-red-600 focus:ring-red-500">
                            Foto entfernen
                        </label>
                    </div>
                @endif
                <input type="file" name="photo" accept="image/*"
                       class="mt-2 block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100">
                <p class="mt-1 text-xs text-gray-400">JPG, PNG oder WebP, max. 4 MB.</p>
                <x-input-error :messages="$errors->get('photo')" class="mt-2" />
            </div>

            {{-- Allergene --}}
            <div>
                <x-input-label value="Allergene" />
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

            {{-- Zusatzstoffe --}}
            <div>
                <x-input-label value="Zusatzstoffe" />
                <div class="mt-2 grid grid-cols-1 gap-x-4 gap-y-2 sm:grid-cols-2">
                    @foreach ($additives as $additive)
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="additives[]" value="{{ $additive->id }}" @checked(in_array($additive->id, $selAdditives))
                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span><span class="font-medium text-gray-400">{{ $additive->code }}</span> {{ $additive->name }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- Diäten: NICHT geeignet für (nur Ausnahmen ankreuzen) --}}
            <div>
                <x-input-label value="NICHT geeignet für (Diäten)" />
                <p class="mt-0.5 text-xs text-gray-400">
                    Standard: für alles geeignet. Nur ankreuzen, wofür das Gericht <strong>nicht</strong> geeignet ist
                    (z. B. ein Fleischgericht → „vegetarisch" &amp; „vegan"). Esser mit dieser Diät bekommen dann eine Warnung.
                </p>
                <div class="mt-2 flex flex-wrap gap-x-4 gap-y-2">
                    @foreach ($diets as $diet)
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="diets[]" value="{{ $diet->id }}" @checked(in_array($diet->id, $selDiets))
                                   class="rounded border-gray-300 text-red-600 focus:ring-red-500">
                            {{ $diet->name }}
                        </label>
                    @endforeach
                </div>
            </div>

            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $dish->is_active))
                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                Gericht ist aktiv
            </label>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    <x-module-icon name="{{ $dish->exists ? 'save' : 'plus' }}" class="text-base" />
                    {{ $dish->exists ? 'Speichern' : 'Gericht anlegen' }}
                </button>
                <a href="{{ route('module.schulkantine.dishes.index') }}"
                   class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700">
                    <x-module-icon name="x" class="text-base" />
                    Abbrechen
                </a>
            </div>
        </form>

        @if ($dish->exists)
            {{-- Gefahrenzone --}}
            <div class="rounded-xl border border-red-200 bg-red-50 p-6">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h2 class="text-base font-semibold text-red-800">Gefahrenzone</h2>
                        <p class="mt-1 text-sm text-red-600">Dieses Gericht dauerhaft löschen.</p>
                    </div>

                    <div x-data="{ open: false }" class="relative">
                        <button type="button" @click="open = ! open"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-red-300 bg-white px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-100">
                            <x-module-icon name="trash" class="text-base" />
                            Gericht löschen
                        </button>

                        <div x-show="open" style="display: none;" @click.outside="open = false"
                             x-transition.origin.bottom.left
                             class="absolute bottom-full left-0 z-50 mb-2 w-72 rounded-xl border border-gray-200 bg-white p-4 text-left shadow-lg">
                            <p class="text-sm font-semibold text-gray-800">Wirklich löschen?</p>
                            <p class="mt-1 text-xs text-gray-500">„{{ $dish->name }}" wird entfernt.</p>
                            <div class="mt-4 flex justify-end gap-2">
                                <button type="button" @click="open = false"
                                        class="rounded-lg px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100">Abbrechen</button>
                                <form method="POST" action="{{ route('module.schulkantine.dishes.destroy', $dish) }}">
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
