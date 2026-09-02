{{-- Formular für eine Menü-Vorlage (Anlegen + Bearbeiten).
     Erwartet: $season, $categories; optional $template (bestehende Vorlage). --}}
@php
    $isEdit = isset($template) && $template->exists;
    $action = $isEdit
        ? route('module.schulkantine.menu-templates.update', $template)
        : route('module.schulkantine.menu-templates.store', $season);
    $wd = [1 => 'Mo', 2 => 'Di', 3 => 'Mi', 4 => 'Do', 5 => 'Fr', 6 => 'Sa', 7 => 'So'];
    $selWeekdays = old('weekdays', $isEdit ? ($template->weekdays ?: []) : []);
    $initSlots = old('slots', $isEdit
        ? $template->slots->map(fn ($s) => ['category_id' => (string) $s->category_id, 'quantity' => $s->quantity])->all()
        : []);
    if (empty($initSlots)) {
        $initSlots = [['category_id' => '', 'quantity' => 1]];
    }
@endphp

<div class="rounded-xl border border-gray-200 bg-white p-6" x-data="{ slots: @js(array_values($initSlots)) }">
    <h2 class="text-base font-semibold text-gray-800">{{ $isEdit ? 'Menü bearbeiten' : 'Neues Menü' }}</h2>
    <form method="POST" action="{{ $action }}" class="mt-3 space-y-4">
        @csrf
        @if ($isEdit) @method('PUT') @endif

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="menu_name" value="Name des Menüs" />
                <x-text-input id="menu_name" name="name" type="text" class="mt-1 block w-full"
                              :value="old('name', $isEdit ? $template->name : '')" placeholder="z. B. Menü 1" required />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="menu_price" value="Preis (€)" />
                <x-text-input id="menu_price" name="price" type="number" step="0.01" min="0" class="mt-1 block w-40"
                              :value="old('price', $isEdit ? $template->price : '')" required />
                <x-input-error :messages="$errors->get('price')" class="mt-2" />
            </div>
        </div>

        <div>
            <x-input-label value="Angeboten an Wochentagen" />
            <div class="mt-2 flex flex-wrap gap-3">
                @foreach ($wd as $nr => $label)
                    <label class="inline-flex items-center gap-1.5 text-sm text-gray-700">
                        <input type="checkbox" name="weekdays[]" value="{{ $nr }}"
                               @checked(in_array($nr, $selWeekdays))
                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        {{ $label }}
                    </label>
                @endforeach
            </div>
            <p class="mt-1 text-xs text-gray-400">Keine Auswahl = an allen Öffnungstagen der Saison.</p>
        </div>

        <div>
            <x-input-label value="Enthält (Kategorie &amp; Anzahl)" />
            <div class="mt-2 space-y-2">
                <template x-for="(slot, i) in slots" :key="i">
                    <div class="flex items-center gap-2">
                        <select x-model="slot.category_id" :name="'slots['+i+'][category_id]'"
                                class="block w-56 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">— Kategorie —</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                        <input type="number" min="1" max="20" x-model.number="slot.quantity" :name="'slots['+i+'][quantity]'"
                               class="w-20 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <span class="text-xs text-gray-400">Gericht(e)</span>
                        <button type="button" @click="slots.splice(i, 1)" x-show="slots.length > 1"
                                class="rounded-md p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600" title="Slot entfernen">
                            <x-module-icon name="trash" class="text-base" />
                        </button>
                    </div>
                </template>
            </div>
            <button type="button" @click="slots.push({ category_id: '', quantity: 1 })"
                    class="mt-2 inline-flex items-center gap-1 text-sm font-medium text-indigo-600 hover:text-indigo-800">
                <x-module-icon name="plus" class="text-sm" /> Kategorie hinzufügen
            </button>
            <x-input-error :messages="$errors->get('slots')" class="mt-2" />
        </div>

        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $isEdit ? $template->is_active : true))
                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
            Menü ist aktiv
        </label>

        <div class="flex items-center gap-3 pt-1">
            <button type="submit"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                <x-module-icon name="{{ $isEdit ? 'save' : 'plus' }}" class="text-base" />
                {{ $isEdit ? 'Speichern' : 'Menü anlegen' }}
            </button>
            @if ($isEdit)
                <a href="{{ route('module.schulkantine.seasons.show', ['season' => $season, 'tab' => 'menues']) }}"
                   class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700">
                    <x-module-icon name="x" class="text-base" />
                    Abbrechen
                </a>
            @endif
        </div>
    </form>
</div>
