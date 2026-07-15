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
                                                            @if ($m->dish->isBundle())
                                                                @php $compLine = $m->dish->components->pluck('name')->join(' + '); @endphp
                                                                {{-- Nicht doppeln, wenn der Name ohnehin die Bestandteile sind. --}}
                                                                @if ($compLine !== $m->dish->name)
                                                                    <span class="block truncate text-[11px] text-teal-700">{{ $compLine }}</span>
                                                                @endif
                                                            @endif
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

                                    {{-- Gericht hinzufügen (inline aufklappend) --}}
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

                                    {{-- Sparmenü aus den Gerichten DIESES Tages bündeln. Nur Nicht-Sparmenüs
                                         sind wählbar (keine Verschachtelung). --}}
                                    @php
                                        $bundleParts = collect($items)->map->dish->filter(fn ($dish) => $dish && ! $dish->isBundle())->values();
                                        $partPrices = $bundleParts->mapWithKeys(fn ($dish) => [$dish->id => (float) $dish->price]);
                                    @endphp
                                    @if ($bundleParts->count() >= 2)
                                        <div x-data="{
                                                open: false,
                                                parts: [],
                                                prices: {{ Illuminate\Support\Js::from($partPrices) }},
                                                price: '',
                                                priceTouched: false,
                                                get single() { return this.parts.reduce((s, id) => s + (this.prices[id] ?? 0), 0) },
                                                euro(v) { return v.toFixed(2).replace('.', ',') + ' €' },
                                                /* Preis-Vorschlag = Einzelsumme, bis der Admin ihn selbst anfasst.
                                                   Bewusst ein Event-Handler und KEIN x-effect: Ein x-effect, das
                                                   `price` liest UND schreibt, lief im selben Alpine-Durchlauf wie
                                                   die Ersparnis-Anzeige – die rechnete dann mit dem alten Wert
                                                   weiter und blieb auf „x € gespart“ stehen. */
                                                syncPrice() {
                                                    this.$nextTick(() => {
                                                        if (! this.priceTouched) this.price = this.single.toFixed(2)
                                                    })
                                                },
                                            }">
                                            <button type="button" @click="open = ! open" x-show="!open"
                                                    class="inline-flex items-center gap-1 text-xs font-medium text-teal-700 hover:text-teal-900">
                                                <x-module-icon name="plus" class="text-sm" /> Sparmenü
                                            </button>
                                            <form x-show="open" x-cloak method="POST" action="{{ route('module.schulkantine.menus.bundle') }}"
                                                  class="space-y-1.5 rounded-lg border border-teal-200 bg-teal-50/60 p-2">
                                                @csrf
                                                <input type="hidden" name="date" value="{{ $d['date']->toDateString() }}">
                                                <p class="text-[11px] text-gray-500">Gerichte dieses Tages bündeln:</p>
                                                @foreach ($bundleParts as $part)
                                                    <label class="flex items-center justify-between gap-1 text-xs text-gray-700">
                                                        <span class="inline-flex min-w-0 items-center gap-1.5">
                                                            <input type="checkbox" name="parts[]" value="{{ $part->id }}" x-model.number="parts"
                                                                   @change="syncPrice()"
                                                                   class="flex-none rounded border-gray-300 text-teal-600 focus:ring-teal-500">
                                                            <span class="truncate">{{ $part->name }}</span>
                                                        </span>
                                                        <span class="flex-none text-gray-400">{{ number_format((float) $part->price, 2, ',', '.') }} €</span>
                                                    </label>
                                                @endforeach
                                                <div x-show="parts.length >= 2" x-cloak class="space-y-1 border-t border-teal-200 pt-1.5">
                                                    <div class="flex items-center justify-between text-[11px] text-gray-500">
                                                        <span>einzeln</span><span x-text="euro(single)" class="font-medium"></span>
                                                    </div>
                                                    <label class="flex items-center gap-1.5 text-xs text-gray-700">
                                                        <span class="flex-none">Preis</span>
                                                        <input type="number" name="price" step="0.01" min="0" required x-model="price"
                                                               @input="priceTouched = true"
                                                               class="w-20 rounded-md border-gray-300 py-0.5 text-xs shadow-sm focus:border-teal-500 focus:ring-teal-500">
                                                        <span class="text-[11px] font-medium"
                                                              :class="(single - (parseFloat(price) || 0)) > 0 ? 'text-green-700' : 'text-red-700'"
                                                              x-text="(single - (parseFloat(price) || 0)) > 0
                                                                        ? euro(single - (parseFloat(price) || 0)) + ' gespart'
                                                                        : 'kein Sparpreis'"></span>
                                                    </label>
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <button type="submit" :disabled="parts.length < 2"
                                                            class="inline-flex items-center gap-1 rounded-md bg-teal-600 px-2 py-1 text-xs font-medium text-white hover:bg-teal-700 disabled:cursor-not-allowed disabled:opacity-50">Anlegen</button>
                                                    <button type="button" @click="open = false"
                                                            class="text-xs text-gray-500 hover:text-gray-700">Abbrechen</button>
                                                </div>
                                            </form>
                                        </div>
                                    @endif
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
