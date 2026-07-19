<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800">
            {{ $category->exists ? 'Kategorie bearbeiten' : 'Neue Kategorie' }}
        </h1>
    </x-slot>

    <div class="max-w-2xl space-y-6">
        <form method="POST"
              action="{{ $category->exists ? route('module.schulkantine.categories.update', $category) : route('module.schulkantine.categories.store') }}"
              class="space-y-5 rounded-xl border border-gray-200 bg-white p-6">
            @csrf
            @if ($category->exists) @method('PUT') @endif

            <div>
                <x-input-label for="name" value="Name der Kategorie" />
                <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                              :value="old('name', $category->name)" placeholder="z. B. Hauptmenü, Nachtisch, Getränk, Eis" required />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="color" value="Farbe" />
                <div class="mt-1 flex items-center gap-3">
                    <input id="color" name="color" type="color" value="{{ old('color', $category->color ?? '#9ca3af') }}"
                           class="h-9 w-14 cursor-pointer rounded border border-gray-300 bg-white p-0.5">
                    <span class="text-xs text-gray-400">Hintergrund dieser Kategorie im Speiseplan – hilft, Kategorien auf einen Blick zu erkennen.</span>
                </div>
                <x-input-error :messages="$errors->get('color')" class="mt-2" />
            </div>

            {{-- Woher darf ein Gericht dieser Kategorie kommen? Die beiden Flags gehören
                 zusammen; „nur spontan" ist schlicht: vorbestellbar aus, spontan an. --}}
            <fieldset class="rounded-lg border border-gray-200 p-4"
                      x-data="{
                          preorder: {{ Illuminate\Support\Js::from((bool) old('allows_preorder', $category->allows_preorder ?? true)) }},
                          walkin: {{ Illuminate\Support\Js::from((bool) old('allows_walkin', $category->allows_walkin)) }},
                      }">
                <legend class="px-1 text-sm font-medium text-gray-700">Erhältlich über</legend>

                <div class="space-y-3">
                    <label class="flex items-start gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="allows_preorder" value="1" x-model="preorder"
                               class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <span>
                            Vorbestellung
                            <span class="block text-xs text-gray-400">Erscheint unter „Essen bestellen" und kann vorab gewählt werden.</span>
                        </span>
                    </label>

                    <label class="flex items-start gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="allows_walkin" value="1" x-model="walkin"
                               class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <span>
                            Spontane Abholung
                            <span class="block text-xs text-gray-400">Kann bei der Ausgabe direkt am Tresen geholt werden (z. B. Getränke &amp; Eis).</span>
                        </span>
                    </label>
                </div>

                {{-- Klartext, was die gewählte Kombination bedeutet. --}}
                <p class="mt-3 border-t border-gray-100 pt-2 text-xs" x-cloak
                   :class="(! preorder && ! walkin) ? 'text-red-700 font-medium' : 'text-gray-500'"
                   x-text="(! preorder && ! walkin)
                            ? '⚠️ So wäre diese Kategorie nirgends erhältlich – bitte mindestens einen Weg wählen.'
                            : (preorder && walkin)
                                ? 'Wird vorbestellt und ist zusätzlich spontan am Tresen zu haben.'
                                : (preorder
                                    ? 'Muss vorbestellt werden – am Tresen gibt es sie nicht spontan (z. B. Hauptmenü).'
                                    : 'Nur spontan: steht auf dem Speiseplan und ist bei der Ausgabe zu haben, taucht aber in der Vorbestellung nicht auf.')"></p>

                <x-input-error :messages="$errors->get('allows_preorder')" class="mt-2" />
            </fieldset>

            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $category->is_active))
                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                Kategorie ist aktiv
            </label>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    <x-module-icon name="{{ $category->exists ? 'save' : 'plus' }}" class="text-base" />
                    {{ $category->exists ? 'Speichern' : 'Kategorie anlegen' }}
                </button>
                @unless ($category->exists)
                    <span class="text-xs text-gray-400">Neue Kategorien landen unten in der Liste – dort per Ziehen einsortieren.</span>
                @endunless
                <a href="{{ route('module.schulkantine.categories.index') }}"
                   class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700">
                    <x-module-icon name="x" class="text-base" />
                    Abbrechen
                </a>
            </div>
        </form>

        @if ($category->exists)
            {{-- Gefahrenzone --}}
            <div class="rounded-xl border border-red-200 bg-red-50 p-6">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h2 class="text-base font-semibold text-red-800">Gefahrenzone</h2>
                        <p class="mt-1 text-sm text-red-600">Diese Kategorie dauerhaft löschen.</p>
                    </div>

                    <div x-data="{ open: false }" class="relative">
                        <button type="button" @click="open = ! open"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-red-300 bg-white px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-100">
                            <x-module-icon name="trash" class="text-base" />
                            Kategorie löschen
                        </button>

                        <div x-show="open" style="display: none;" @click.outside="open = false"
                             x-transition.origin.bottom.left
                             class="absolute bottom-full left-0 z-50 mb-2 w-72 rounded-xl border border-gray-200 bg-white p-4 text-left shadow-lg">
                            <p class="text-sm font-semibold text-gray-800">Wirklich löschen?</p>
                            <p class="mt-1 text-xs text-gray-500">„{{ $category->name }}" wird entfernt.</p>
                            <div class="mt-4 flex justify-end gap-2">
                                <button type="button" @click="open = false"
                                        class="rounded-lg px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100">Abbrechen</button>
                                <form method="POST" action="{{ route('module.schulkantine.categories.destroy', $category) }}">
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
