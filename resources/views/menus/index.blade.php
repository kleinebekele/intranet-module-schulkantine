<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2">
            <x-module-icon name="restaurant" class="text-2xl text-indigo-600" />
            <h1 class="text-xl font-semibold text-gray-800">Speiseplan</h1>
        </div>
    </x-slot>

    <div class="max-w-full">
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

            @if (empty($days))
                <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-6 text-center text-sm text-gray-500">
                    Für diese Saison sind keine Kantinen-Wochentage hinterlegt.
                </div>
            @else
                <div class="flex gap-3 overflow-x-auto pb-2">
                    @foreach ($days as $d)
                        <div class="flex min-w-[15rem] flex-1 flex-col overflow-hidden rounded-xl border {{ $d['open'] ? 'border-gray-200 bg-white' : 'border-amber-200 bg-amber-50/40' }}">
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
                                                        <span class="text-gray-800">{{ $m->dish->name }}</span>
                                                        <form method="POST" action="{{ route('module.schulkantine.menus.destroy', $m) }}"
                                                              onsubmit="return confirm('Gericht entfernen?')">
                                                            @csrf @method('DELETE')
                                                            <button type="submit" title="Entfernen"
                                                                    class="text-gray-400 hover:text-red-600"><x-module-icon name="trash" class="text-sm" /></button>
                                                        </form>
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
