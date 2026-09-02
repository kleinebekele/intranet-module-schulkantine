<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2">
            <x-module-icon name="restaurant" class="text-2xl text-indigo-600" />
            <h1 class="text-xl font-semibold text-gray-800">Speiseplan</h1>
        </div>
    </x-slot>

    <div class="max-w-full">
        {{-- Erfolgsmeldungen zeigt das App-Layout bereits global; hier nur Fehler. --}}
        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                {{ $errors->first() }}
            </div>
        @endif

        @if (! $season)
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Es ist keine Saison als „aktiv" markiert – lege zuerst unter „Saisons &amp; Kalender" eine aktive Saison an.
            </div>
        @else
            @php $dishesByCat = $dishes->groupBy(fn ($d) => $d->category?->name ?? 'Ohne Kategorie'); @endphp

            {{-- Wochen-Navigation --}}
            <div class="mb-4 flex items-center justify-between gap-4">
                <div>
                    @if ($canPrev)
                        <a href="{{ route('module.schulkantine.menus.index', ['week' => $prevWeek]) }}"
                           class="inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">‹ Vorige Woche</a>
                    @endif
                </div>
                <div class="text-center">
                    <div class="text-sm font-semibold text-gray-800">{{ $weekStart->format('d.m.') }} – {{ $weekEnd->format('d.m.Y') }}</div>
                    <div class="text-xs text-gray-400">Saison „{{ $season->name }}"</div>
                </div>
                <div>
                    @if ($canNext)
                        <a href="{{ route('module.schulkantine.menus.index', ['week' => $nextWeek]) }}"
                           class="inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Nächste Woche ›</a>
                    @endif
                </div>
            </div>

            {{-- Wochen-Freigabe (hybrid): Status + manuelle Übersteuerung --}}
            <div class="mb-4 flex flex-col gap-2 rounded-lg border px-4 py-3 sm:flex-row sm:items-center sm:justify-between
                        {{ $weekReleased ? 'border-green-200 bg-green-50' : 'border-gray-200 bg-gray-50' }}">
                <div class="text-sm">
                    @if ($weekReleased)
                        <span class="font-semibold text-green-800">✅ Woche freigegeben</span>
                        <span class="text-green-700">– es kann bestellt werden.</span>
                    @else
                        <span class="font-semibold text-gray-700">🔒 Woche nicht freigegeben</span>
                        <span class="text-gray-500">– Bestellen ist gesperrt.</span>
                    @endif
                    <span class="ml-1 text-xs text-gray-400">
                        @if ($weekOverride === 'released')
                            (manuell freigegeben)
                        @elseif ($weekOverride === 'held')
                            (manuell zurückgehalten)
                        @else
                            (automatisch)
                        @endif
                    </span>
                </div>
                <div class="flex items-center gap-2">
                    @if ($weekOverride !== 'released')
                        <form method="POST" action="{{ route('module.schulkantine.menus.release') }}">
                            @csrf
                            <input type="hidden" name="week" value="{{ $weekStart->toDateString() }}">
                            <input type="hidden" name="action" value="release">
                            <button type="submit" class="rounded-md border border-green-300 bg-white px-2.5 py-1 text-xs font-medium text-green-700 hover:bg-green-50">Jetzt freigeben</button>
                        </form>
                    @endif

                    {{-- Zurückhalten nur, solange es noch keine Bestellungen für die Woche gibt:
                         eine freigegebene Woche wieder zuzusperren würde bereits getätigte
                         Bestellungen entwerten. Freigeben bleibt dagegen immer möglich. --}}
                    @if ($weekHasOrders)
                        <span class="text-xs text-gray-400">🔒 bereits bestellt – die Freigabe kann nicht mehr zurückgenommen werden</span>
                    @else
                        @if ($weekOverride !== 'held')
                            <form method="POST" action="{{ route('module.schulkantine.menus.release') }}">
                                @csrf
                                <input type="hidden" name="week" value="{{ $weekStart->toDateString() }}">
                                <input type="hidden" name="action" value="hold">
                                <button type="submit" class="rounded-md border border-gray-300 bg-white px-2.5 py-1 text-xs font-medium text-gray-600 hover:bg-gray-100">Zurückhalten</button>
                            </form>
                        @endif
                        @if ($weekOverride !== null)
                            <form method="POST" action="{{ route('module.schulkantine.menus.release') }}">
                                @csrf
                                <input type="hidden" name="week" value="{{ $weekStart->toDateString() }}">
                                <input type="hidden" name="action" value="auto">
                                <button type="submit" class="rounded-md px-2.5 py-1 text-xs font-medium text-gray-400 hover:text-gray-600">↺ Automatik</button>
                            </form>
                        @endif
                    @endif
                </div>
            </div>

            @if (empty($days))
                <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-6 text-center text-sm text-gray-500">
                    Für diese Saison sind keine Kantinen-Wochentage hinterlegt.
                </div>
            @else
                {{-- Tage als umbrechendes Raster: jede Karte will mindestens 15rem, teilt sich die
                     Breite gleichmäßig und rutscht in die nächste Zeile, wenn kein Platz mehr ist
                     (kein horizontaler Scroll). Das min() verhindert Überlauf, wenn der Platz
                     schmaler als die Mindestbreite ist – dann bleibt eine Spalte übrig. --}}
                <div class="grid grid-cols-[repeat(auto-fit,minmax(min(15rem,100%),1fr))] gap-3 pb-2">
                    @foreach ($days as $d)
                        <div class="flex w-full flex-col overflow-hidden rounded-xl border {{ $d['open'] ? 'border-gray-200 bg-white' : 'border-amber-200 bg-amber-50/40' }}">
                            {{-- Kopf --}}
                            <div class="border-b px-3 py-2 {{ $d['open'] ? 'border-gray-100 bg-gray-50' : 'border-amber-200 bg-amber-50' }}">
                                <div class="text-sm font-semibold {{ $d['open'] ? 'text-gray-800' : 'text-amber-800' }}">{{ $d['date']->isoFormat('dddd') }}</div>
                                <div class="text-xs {{ $d['open'] ? 'text-gray-400' : 'text-amber-500' }}">{{ $d['date']->format('d.m.Y') }}</div>
                                @unless ($d['open'])
                                    <div class="mt-0.5 text-xs text-amber-600" title="{{ $d['reason'] }}">🔒 {{ $d['reason'] }}</div>
                                @endunless
                            </div>

                            {{-- Angebot --}}
                            <div class="flex-1 space-y-2 p-3">
                                @if (! $d['open'])
                                    <p class="py-4 text-center text-xs text-amber-500">geschlossen</p>
                                @else
                                    @php $items = $plan[$d['date']->toDateString()] ?? []; @endphp

                                    @foreach (collect($items)->groupBy(fn ($m) => $m->dish->category?->name ?? 'Ohne Kategorie') as $catName => $catItems)
                                        @php $catColor = $catItems->first()->dish->category?->color; @endphp
                                        <fieldset class="rounded-lg border px-2 pb-2 {{ $catColor ? '' : 'border-gray-200' }}"
                                                  @if ($catColor) style="border-color: {{ $catColor }}; background-color: {{ $catColor }}1a;" @endif>
                                            <legend class="px-1 text-[11px] font-medium uppercase tracking-wide {{ $catColor ? '' : 'text-gray-400' }}"
                                                    @if ($catColor) style="color: {{ $catColor }};" @endif>{{ $catName }}</legend>
                                            <div class="space-y-1.5">
                                                @foreach ($catItems as $m)
                                                    <div class="flex items-center justify-between gap-2 rounded-md border border-gray-100 bg-white px-2 py-1 text-sm">
                                                        <span class="min-w-0 text-gray-800">
                                                            {{ $m->dish->name }}
                                                        </span>
                                                        @if ($m->orders_count > 0)
                                                            <span title="Bereits bestellt – nicht mehr entfernbar" class="text-gray-300">🔒</span>
                                                        @else
                                                            <form method="POST" action="{{ route('module.schulkantine.menus.destroy', $m) }}"
                                                                  onsubmit="return confirm('Gericht entfernen?')">
                                                                @csrf @method('DELETE')
                                                                <button type="submit" title="Entfernen"
                                                                        class="text-gray-400 hover:text-red-600"><x-module-icon name="trash" class="text-sm" /></button>
                                                            </form>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        </fieldset>
                                    @endforeach

                                    {{-- Ausgerollte Menüs dieses Tages: Slots mit Gerichten füllen. --}}
                                    @php $dayMenus = $menuDaysByDate[$d['date']->toDateString()] ?? collect(); @endphp
                                    @foreach ($dayMenus as $md)
                                        <form method="POST" action="{{ route('module.schulkantine.menus.fill-day', $md) }}"
                                              class="rounded-lg border border-emerald-200 bg-emerald-50/40 px-2 py-2">
                                            @csrf
                                            <div class="flex items-center justify-between gap-2">
                                                <span class="text-xs font-semibold text-emerald-800">🍽 {{ $md->name }}</span>
                                                <span class="text-xs font-bold text-emerald-700">{{ number_format((float) $md->price, 2, ',', '.') }} €</span>
                                            </div>
                                            <div class="mt-1.5 space-y-1.5">
                                                @foreach ($md->slots as $slot)
                                                    @php $slotDishes = ($dishesByCategory[$slot->category_id] ?? collect())->map(fn ($x) => ['id' => $x->id, 'name' => $x->name])->values(); @endphp
                                                    <div>
                                                        <label class="block text-[10px] font-medium uppercase tracking-wide text-gray-400">{{ $slot->category?->name ?? 'Kategorie' }}</label>
                                                        <div class="relative mt-0.5"
                                                             x-data="{
                                                                options: @js($slotDishes),
                                                                open: false,
                                                                query: @js(optional($slot->dish)->name ?? ''),
                                                                selectedId: @js($slot->dish_id ?? ''),
                                                                get filtered() { const t = this.query.trim().toLowerCase(); const o = t ? this.options.filter(d => d.name.toLowerCase().includes(t)) : this.options; return o.slice(0, 50); },
                                                                pick(d) { this.selectedId = d.id; this.query = d.name; this.open = false; },
                                                                clear() { this.selectedId = ''; this.query = ''; this.open = true; },
                                                             }" @click.outside="open = false">
                                                            <input type="hidden" name="slots[{{ $slot->id }}]" :value="selectedId">
                                                            <input type="text" x-model="query" @focus="open = true" @click="open = true"
                                                                   @keydown.escape="open = false" placeholder="Gericht suchen …" autocomplete="off"
                                                                   class="block w-full rounded-md border-gray-300 pr-6 text-xs shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                                                            <button type="button" x-show="query !== ''" x-cloak @click="clear()"
                                                                    class="absolute inset-y-0 right-0 flex items-center pr-1.5 text-gray-400 hover:text-gray-600">✕</button>
                                                            <ul x-show="open" x-cloak
                                                                class="absolute z-20 mt-1 max-h-40 w-full overflow-auto rounded-md border border-gray-200 bg-white py-1 text-xs shadow-lg">
                                                                <template x-for="d in filtered" :key="d.id">
                                                                    <li @click="pick(d)" x-text="d.name"
                                                                        class="cursor-pointer px-2 py-1 hover:bg-emerald-50"
                                                                        :class="d.id === selectedId ? 'bg-emerald-50 font-medium' : ''"></li>
                                                                </template>
                                                                <li x-show="! filtered.length" class="px-2 py-1 text-gray-400">kein Treffer</li>
                                                            </ul>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                            <button type="submit"
                                                    class="mt-2 inline-flex items-center gap-1 rounded-md bg-emerald-600 px-2 py-1 text-xs font-medium text-white hover:bg-emerald-700">
                                                <x-module-icon name="save" class="text-sm" /> Menü speichern
                                            </button>
                                        </form>
                                    @endforeach

                                    {{-- Gericht hinzufügen (inline aufklappend) – immer unter den Menüs. --}}
                                    <div x-data="{ open: false }">
                                        <button type="button" @click="open = ! open" x-show="!open"
                                                class="inline-flex items-center gap-1 text-xs font-medium text-indigo-600 hover:text-indigo-800">
                                            <x-module-icon name="plus" class="text-sm" /> Gericht
                                        </button>
                                        <form x-show="open" x-cloak method="POST" action="{{ route('module.schulkantine.menus.store') }}" class="space-y-1.5">
                                            @csrf
                                            <input type="hidden" name="date" value="{{ $d['date']->toDateString() }}">
                                            <select name="dish_id" required
                                                    class="block w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                <option value="">— Gericht wählen —</option>
                                                @foreach ($dishesByCat as $catName => $catDishes)
                                                    <optgroup label="{{ $catName }}">
                                                        @foreach ($catDishes as $dish)
                                                            <option value="{{ $dish->id }}">{{ $dish->name }}</option>
                                                        @endforeach
                                                    </optgroup>
                                                @endforeach
                                            </select>
                                            <div class="flex items-center gap-2">
                                                <button type="submit"
                                                        class="inline-flex items-center gap-1 rounded-md bg-indigo-600 px-2 py-1 text-xs font-medium text-white hover:bg-indigo-700">Hinzufügen</button>
                                                <button type="button" @click="open = false"
                                                        class="text-xs text-gray-500 hover:text-gray-700">Abbrechen</button>
                                            </div>
                                        </form>
                                    </div>

                                    {{-- Bestellungen dieses Tages (Admin): wer hat was bestellt, mit Löschen. --}}
                                    @php $dayOrders = $ordersByDate[$d['date']->toDateString()] ?? collect(); @endphp
                                    <div x-data="{ open: false }" class="mt-2 border-t border-gray-100 pt-2">
                                        <button type="button" @click="open = ! open"
                                                class="flex w-full items-center justify-between text-xs font-medium text-gray-500 hover:text-gray-700">
                                            <span class="inline-flex items-center gap-1">
                                                <x-module-icon name="users" class="text-sm" /> Bestellungen ({{ $dayOrders->count() }})
                                            </span>
                                            <span class="text-gray-400" x-text="open ? '▲' : '▼'"></span>
                                        </button>
                                        <div x-show="open" x-cloak class="mt-1 space-y-1">
                                            @forelse ($dayOrders as $o)
                                                <div class="flex items-center justify-between gap-2 rounded-md bg-gray-50 px-2 py-1 text-xs">
                                                    <span class="min-w-0 truncate">
                                                        <span class="font-medium text-gray-700">{{ $o->user?->name ?? 'Unbekannt' }}</span>
                                                        <span class="text-gray-400">·</span>
                                                        <span class="text-gray-600">{{ $o->dish?->name ?? 'OGS-Essen' }}</span>
                                                    </span>
                                                    <form method="POST" action="{{ route('module.schulkantine.menus.order-destroy', $o) }}"
                                                          onsubmit="return confirm('Bestellung von {{ $o->user?->name }} löschen?')">
                                                        @csrf @method('DELETE')
                                                        <button type="submit" title="Bestellung löschen"
                                                                class="shrink-0 text-gray-400 hover:text-red-600"><x-module-icon name="trash" class="text-sm" /></button>
                                                    </form>
                                                </div>
                                            @empty
                                                <p class="text-xs text-gray-400">Noch keine Bestellungen.</p>
                                            @endforelse
                                        </div>
                                    </div>

                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                <p class="mt-3 text-xs text-gray-400">
                    Ein Tagesangebot gilt für alle: Schüler &amp; Sonstige wählen daraus einzelne Gerichte, OGS isst (nur ja/nein) mit.
                    Gelb markierte Tage sind Schließtage (Ferien/Feiertage).
                </p>
            @endif
        @endif
    </div>
</x-app-layout>
