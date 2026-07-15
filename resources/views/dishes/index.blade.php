<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div class="flex items-center gap-2">
                <x-module-icon name="restaurant" class="text-2xl text-indigo-600" />
                <h1 class="text-xl font-semibold text-gray-800">Gerichte</h1>
            </div>
            <a href="{{ route('module.schulkantine.dishes.create') }}"
               class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                <x-module-icon name="plus" class="text-base" />
                Neues Gericht
            </a>
        </div>
    </x-slot>

    <div class="w-full">
        {{-- Filterleiste (GET, damit der Filter in der URL steht) --}}
        <form method="GET" action="{{ route('module.schulkantine.dishes.index') }}"
              class="mb-4 flex flex-wrap items-end gap-3">
            <div class="min-w-[12rem] flex-1">
                <label for="search" class="block text-xs font-medium text-gray-500">Suche (Name)</label>
                <input id="search" name="search" type="text" value="{{ $search }}"
                       placeholder="z. B. Spaghetti"
                       class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <label for="category" class="block text-xs font-medium text-gray-500">Kategorie</label>
                <select id="category" name="category"
                        class="mt-1 block rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Alle Kategorien</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected($categoryFilter === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                    <option value="none" @selected($categoryFilter === 'none')>— ohne Kategorie —</option>
                </select>
            </div>
            <div>
                <label for="status" class="block text-xs font-medium text-gray-500">Status</label>
                <select id="status" name="status"
                        class="mt-1 block rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Alle</option>
                    <option value="active" @selected($statusFilter === 'active')>Aktiv</option>
                    <option value="inactive" @selected($statusFilter === 'inactive')>Inaktiv</option>
                </select>
            </div>
            <button type="submit"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                <x-module-icon name="search" class="text-base" /> Filtern
            </button>
            @if ($search !== '' || $categoryFilter !== '' || $statusFilter !== '')
                <a href="{{ route('module.schulkantine.dishes.index') }}" class="px-2 py-2 text-sm text-gray-500 hover:text-gray-700">Zurücksetzen</a>
            @endif
        </form>

        <div class="mb-2 flex items-center justify-between gap-3">
            <div class="text-xs text-gray-400">
                {{ $dishes->count() }} Gerichte
                @if ($search !== '' || $categoryFilter !== '' || $statusFilter !== '')
                    <span class="text-gray-300">·</span> gefiltert
                @endif
            </div>
            <a href="{{ route('module.schulkantine.ratings.report') }}"
               class="inline-flex items-center gap-1.5 text-sm font-medium text-indigo-600 hover:text-indigo-800 hover:underline">
                <x-module-icon name="trophy" class="text-base" /> Alle Bewertungen
            </a>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-6">
            @if ($dishes->isEmpty())
                @if ($search !== '' || $categoryFilter !== '' || $statusFilter !== '')
                    <p class="text-sm text-gray-500">Keine Gerichte gefunden. Passe Suche oder Kategorie an.</p>
                @else
                    <p class="text-sm text-gray-500">Noch keine Gerichte. Lege das erste an! 🍝</p>
                @endif
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-left text-xs font-medium uppercase tracking-wide text-gray-400">
                                <th class="px-3 py-2"></th>
                                <th class="px-3 py-2">Name</th>
                                <th class="hidden px-3 py-2 md:table-cell">Kategorie</th>
                                <th class="px-3 py-2 text-right">Preis</th>
                                <th class="hidden px-3 py-2 xl:table-cell">Allergene</th>
                                <th class="hidden px-3 py-2 xl:table-cell">Zusatzstoffe</th>
                                <th class="hidden px-3 py-2 xl:table-cell">Nicht für</th>
                                <th class="px-3 py-2 xl:hidden">Verträglichkeiten</th>
                                <th class="px-3 py-2">Bewertung</th>
                                <th class="hidden px-3 py-2 sm:table-cell">Status</th>
                                <th class="px-3 py-2 text-right">Aktion</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($dishes as $dish)
                                <tr class="hover:bg-gray-50">
                                    <td class="w-24 px-3 py-2">
                                        @if ($dish->photoUrl())
                                            <img src="{{ $dish->photoUrl() }}" alt="" class="h-20 w-20 max-w-none shrink-0 rounded-lg border border-gray-200 object-cover">
                                        @else
                                            <div class="flex h-20 w-20 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-300">
                                                <x-module-icon name="restaurant" class="text-3xl" />
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 font-medium text-gray-800">
                                        {{ $dish->name }}
                                        @unless ($dish->is_active)
                                            {{-- Inaktiv-Hinweis, solange die Status-Spalte ausgeblendet ist --}}
                                            <span class="ml-1 text-xs font-medium text-gray-400 sm:hidden">(inaktiv)</span>
                                        @endunless
                                        @if ($dish->isBundle())
                                            {{-- Sparmenü: Bestandteile nennen, sonst steht hier ein Name ohne Inhalt --}}
                                            <div class="mt-0.5 text-xs font-normal text-teal-700">
                                                <span class="font-medium">Sparmenü:</span>
                                                {{ $dish->components->pluck('name')->join(' + ') }}
                                                <span class="text-gray-400">(einzeln {{ number_format($dish->componentsPrice(), 2, ',', '.') }} €)</span>
                                            </div>
                                        @endif
                                        {{-- Kategorie inline, wenn die eigene Spalte ausgeblendet ist --}}
                                        @if ($dish->category)
                                            <span class="mt-0.5 inline-flex rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700 md:hidden">{{ $dish->category->name }}</span>
                                        @endif
                                    </td>
                                    <td class="hidden px-3 py-2 text-gray-600 md:table-cell">
                                        @if ($dish->category)
                                            <span class="inline-flex rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700">{{ $dish->category->name }}</span>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-2 text-right font-medium text-gray-800">{{ number_format((float) $dish->price, 2, ',', '.') }} €</td>
                                    @php
                                        // Bewusst die effective*-Methoden: Bei einem Sparmenü stecken die
                                        // Allergene in den Bestandteilen, die eigenen Sets sind leer.
                                        $badgeSets = [
                                            ['short' => 'Allerg.', 'items' => $dish->effectiveAllergens()->map(fn ($a) => $a->code.' '.$a->name), 'class' => 'bg-rose-50 text-rose-700'],
                                            ['short' => 'Zusatz', 'items' => $dish->effectiveAdditives()->map(fn ($a) => $a->code.' '.$a->name), 'class' => 'bg-amber-50 text-amber-700'],
                                            ['short' => 'Nicht für', 'items' => $dish->effectiveUnsuitableDiets()->pluck('name'), 'class' => 'bg-red-50 text-red-700'],
                                        ];
                                    @endphp
                                    @foreach ($badgeSets as $set)
                                        <td class="hidden px-3 py-2 xl:table-cell">
                                            @if ($set['items']->isNotEmpty())
                                                <span x-data="{ show: false, coords: '', place() { const r = this.$refs.t.getBoundingClientRect(); this.coords = 'left:' + (r.left + r.width / 2) + 'px; top:' + (r.top - 8) + 'px'; } }"
                                                      @mouseenter="place(); show = true" @mouseleave="show = false"
                                                      class="relative inline-block">
                                                    <span x-ref="t" class="inline-flex cursor-help rounded-full px-2 py-0.5 text-xs font-medium {{ $set['class'] }}">{{ $set['items']->count() }}</span>
                                                    <template x-teleport="body">
                                                        <div x-show="show" x-transition.opacity :style="coords"
                                                             class="pointer-events-none fixed z-50 max-w-xs -translate-x-1/2 -translate-y-full rounded-lg border border-gray-200 bg-white p-2 shadow-lg">
                                                            <div class="flex flex-wrap gap-1">
                                                                @foreach ($set['items'] as $item)
                                                                    <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">{{ $item }}</span>
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                    </template>
                                                </span>
                                            @else
                                                <span class="text-gray-300">—</span>
                                            @endif
                                        </td>
                                    @endforeach
                                    {{-- Zusammengefasst, wenn die drei Einzelspalten (ab xl) ausgeblendet sind --}}
                                    <td class="px-3 py-2 xl:hidden">
                                        @php $anyBadge = collect($badgeSets)->contains(fn ($set) => $set['items']->isNotEmpty()); @endphp
                                        @if ($anyBadge)
                                            <div class="flex flex-wrap gap-1">
                                                @foreach ($badgeSets as $set)
                                                    @if ($set['items']->isNotEmpty())
                                                        <span x-data="{ show: false, coords: '', place() { const r = this.$refs.t.getBoundingClientRect(); this.coords = 'left:' + (r.left + r.width / 2) + 'px; top:' + (r.top - 8) + 'px'; } }"
                                                              @mouseenter="place(); show = true" @mouseleave="show = false"
                                                              class="relative inline-block">
                                                            <span x-ref="t" class="inline-flex cursor-help items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium {{ $set['class'] }}">
                                                                {{ $set['short'] }} {{ $set['items']->count() }}
                                                            </span>
                                                            <template x-teleport="body">
                                                                <div x-show="show" x-transition.opacity :style="coords"
                                                                     class="pointer-events-none fixed z-50 max-w-xs -translate-x-1/2 -translate-y-full rounded-lg border border-gray-200 bg-white p-2 shadow-lg">
                                                                    <div class="mb-1 text-xs font-semibold text-gray-500">{{ $set['short'] }}</div>
                                                                    <div class="flex flex-wrap gap-1">
                                                                        @foreach ($set['items'] as $item)
                                                                            <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">{{ $item }}</span>
                                                                        @endforeach
                                                                    </div>
                                                                </div>
                                                            </template>
                                                        </span>
                                                    @endif
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="text-gray-300">—</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-2">
                                        @php $r = $ratings->get($dish->id); @endphp
                                        @if ($r && ($r['up'] + $r['down']) > 0)
                                            <span class="inline-flex items-center gap-2 text-xs font-medium">
                                                <span class="text-green-600">👍 {{ $r['up'] }}</span>
                                                <span class="text-rose-600">👎 {{ $r['down'] }}</span>
                                            </span>
                                        @else
                                            <span class="text-gray-300">—</span>
                                        @endif
                                    </td>
                                    <td class="hidden px-3 py-2 sm:table-cell">
                                        @if ($dish->is_active)
                                            <span class="inline-flex rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">aktiv</span>
                                        @else
                                            <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500">inaktiv</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        <a href="{{ route('module.schulkantine.dishes.edit', $dish) }}" title="Bearbeiten"
                                           class="inline-flex items-center rounded-md p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-700">
                                            <x-module-icon name="edit" class="text-base" />
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
