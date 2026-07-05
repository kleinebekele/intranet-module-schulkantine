<x-app-layout>
    @php
        $money = fn ($v) => number_format((float) $v, 2, ',', '.').' €';
    @endphp

    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <x-module-icon name="restaurant" class="text-2xl text-indigo-600" />
                <h1 class="text-xl font-semibold text-gray-800">Essen bestellen</h1>
            </div>
            @if ($season)
                <div class="rounded-lg border border-indigo-100 bg-indigo-50 px-4 py-1.5 text-right">
                    <div class="text-[11px] uppercase tracking-wide text-indigo-400">offen im {{ $monthStart->isoFormat('MMMM YYYY') }}</div>
                    <div class="text-lg font-bold text-indigo-700">{{ $money($monthTotal) }}</div>
                </div>
            @endif
        </div>
    </x-slot>

    <div class="max-w-full">
        {{-- Erfolgsmeldungen zeigt das App-Layout bereits global; hier nur Fehler. --}}
        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
        @endif

        @if (! $season)
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Es ist keine Saison aktiv – aktuell kann nicht bestellt werden.
            </div>
        @else
            {{-- Wochen-Navigation --}}
            <div class="mb-4 flex items-center justify-between gap-4">
                <div>
                    @if ($canPrev)
                        <a href="{{ route('module.schulkantine.orders.index', ['week' => $prevWeek]) }}"
                           class="inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">‹<span class="hidden sm:inline"> Vorige Woche</span></a>
                    @endif
                </div>
                <div class="text-center">
                    <div class="text-sm font-semibold text-gray-800">{{ $weekStart->format('d.m.') }} – {{ $weekEnd->format('d.m.Y') }}</div>
                    <div class="text-xs text-gray-400">Saison „{{ $season->name }}"</div>
                </div>
                <div>
                    @if ($canNext)
                        <a href="{{ route('module.schulkantine.orders.index', ['week' => $nextWeek]) }}"
                           class="inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"><span class="hidden sm:inline">Nächste Woche </span>›</a>
                    @endif
                </div>
            </div>

            @if (! $weekReleased)
                <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-6 text-center text-sm text-gray-500">
                    🔒 Diese Woche ist noch nicht zum Bestellen freigegeben.
                </div>
            @elseif (empty($days))
                <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-6 text-center text-sm text-gray-500">
                    Für diese Saison sind keine Kantinen-Wochentage hinterlegt.
                </div>
            @else
                @php
                    // Warnt ein Gericht gegen die Sonderkost eines Essers?
                    //  – Allergen: Gericht enthält ein gemiedenes Allergen.
                    //  – Diät: Gericht ist als „nicht geeignet" für eine vom Esser
                    //          geforderte Diät markiert.
                    $dishWarn = function ($dish, array $allergenIds, array $dietIds) {
                        $hasAllergen = $dish->allergens->pluck('id')->intersect($allergenIds)->isNotEmpty();
                        $conflictsDiet = $dish->unsuitableDiets->pluck('id')->intersect($dietIds)->isNotEmpty();
                        return $hasAllergen || $conflictsDiet;
                    };
                @endphp

                <div class="space-y-6">
                    @foreach ($eaters as $e)
                        @php
                            $eater = $e['user'];
                            $mode = $e['mode'];
                            $isOgs = $mode === \Intranet\Modules\Schulkantine\Models\CustomerGroup::MODE_JA_NEIN;
                            $isSubscribed = $subscribed->has($eater->id);
                            $hasSonderkost = ! empty($e['allergenIds']) || ! empty($e['dietIds']);
                        @endphp

                        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
                            {{-- Kopf des Essers --}}
                            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 bg-gray-50/60 px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold text-gray-800">{{ $eater->name }}</span>
                                    @if ($eater->id === auth()->id())
                                        <span class="rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-600">ich</span>
                                    @endif
                                    <span class="text-xs text-gray-400">{{ $e['group']?->name ?? 'keine Gruppe' }}</span>
                                    @if ($hasSonderkost)
                                        <span class="rounded-full bg-red-50 px-2 py-0.5 text-[11px] font-medium text-red-600" title="Es ist Sonderkost hinterlegt">⚠️ Sonderkost</span>
                                    @endif
                                </div>
                                @if ($isOgs && $isSubscribed)
                                    <span class="text-xs text-gray-500">🔁 Abo aktiv – isst automatisch mit; hier nur Abbestellungen.</span>
                                @endif
                            </div>

                            @if (! $e['group'])
                                <div class="px-4 py-4 text-sm text-gray-400">Für diese Person ist keine Kundengruppe hinterlegt.</div>
                            @else
                                {{-- Handy: Tage gestapelt (volle Breite) · ab lg: Spalten mit horizontalem Scroll.
                                     Grauer Canvas + Schatten je Karte, damit die Tage klar getrennt sind. --}}
                                <div class="flex flex-col gap-4 bg-gray-50 p-3 sm:p-4 lg:flex-row lg:gap-3 lg:overflow-x-auto">
                                    @foreach ($days as $day)
                                        @php
                                            $dateStr = $day['date']->toDateString();
                                            $items = collect($plan[$dateStr] ?? []);
                                            $eaterTotal = $dayTotals[$eater->id][$dateStr] ?? 0;
                                            if ($isOgs) {
                                                $attends = $isSubscribed
                                                    ? ! isset($ogsCancelled[$eater->id][$dateStr])
                                                    : isset($ogsOrdered[$eater->id][$dateStr]);
                                                $hasOrder = $day['open'] && $attends;
                                            } else {
                                                $attends = false;
                                                $hasOrder = $eaterTotal > 0;
                                            }
                                            // Kartenfarbe: geschlossen=amber, bestellt=grün (kräftiger Rand +
                                            // grüner linker Balken), offen ohne Bestellung=neutral-weiß.
                                            $col = ! $day['open']
                                                ? 'border-amber-200 bg-amber-50'
                                                : ($hasOrder ? 'border-green-400 border-l-4 border-l-green-500 bg-white' : 'border-gray-200 bg-white');
                                            $head = ! $day['open']
                                                ? 'border-amber-200 bg-amber-50'
                                                : ($hasOrder ? 'border-green-100 bg-green-50' : 'border-gray-100 bg-white');
                                        @endphp
                                        <div class="flex w-full flex-col overflow-hidden rounded-xl border shadow-sm lg:min-w-[15.5rem] lg:max-w-[15.5rem] {{ $col }}">
                                            {{-- Tages-Kopf --}}
                                            <div class="border-b px-3 py-2 {{ $head }}">
                                                <div class="flex items-center justify-between">
                                                    <div>
                                                        <div class="text-sm font-semibold {{ $day['open'] ? 'text-gray-800' : 'text-amber-800' }}">{{ $day['date']->isoFormat('dddd') }}</div>
                                                        <div class="text-xs {{ $day['open'] ? 'text-gray-400' : 'text-amber-500' }}">{{ $day['date']->format('d.m.Y') }}</div>
                                                    </div>
                                                    @if ($day['open'] && $hasOrder && ! $isOgs)
                                                        <span class="rounded-full bg-green-600 px-2 py-0.5 text-xs font-semibold text-white">{{ $money($eaterTotal) }}</span>
                                                    @elseif ($day['open'] && $hasOrder && $isOgs)
                                                        <span class="rounded-full bg-green-600 px-2 py-0.5 text-xs font-semibold text-white">✓ isst</span>
                                                    @endif
                                                </div>
                                                @unless ($day['open'])
                                                    <div class="mt-0.5 text-xs text-amber-600" title="{{ $day['reason'] }}">🔒 {{ $day['reason'] }}</div>
                                                @else
                                                    <div class="mt-0.5 text-[11px] {{ $day['canOrder'] ? 'text-gray-400' : 'text-amber-600' }}">
                                                        @if ($day['canOrder'] && $day['orderDeadline'])
                                                            Bestellschluss {{ $day['orderDeadline']->isoFormat('dd HH:mm') }}
                                                        @else
                                                            Bestellfrist abgelaufen
                                                        @endif
                                                    </div>
                                                @endunless
                                            </div>

                                            {{-- Tages-Inhalt --}}
                                            <div class="flex-1 space-y-2 p-2.5">
                                                @if (! $day['open'])
                                                    <p class="py-6 text-center text-xs text-amber-500">geschlossen</p>
                                                @elseif ($items->isEmpty())
                                                    <p class="py-6 text-center text-xs text-gray-400">kein Angebot</p>
                                                @elseif ($isOgs)
                                                    {{-- OGS: ja/nein --}}
                                                    @php $editable = ($attends && $day['canCancel']) || (! $attends && $day['canOrder']); @endphp
                                                    <form method="POST" action="{{ route('module.schulkantine.orders.store') }}">
                                                        @csrf
                                                        <input type="hidden" name="eater_id" value="{{ $eater->id }}">
                                                        <input type="hidden" name="date" value="{{ $dateStr }}">
                                                        <input type="hidden" name="attend" value="{{ $attends ? '1' : '0' }}">
                                                        <label class="flex items-center gap-2 rounded-lg border px-3 py-2 text-sm {{ $attends ? 'border-green-300 bg-green-50 text-green-800' : 'border-gray-200 text-gray-700' }} {{ $editable ? 'cursor-pointer' : 'opacity-60' }}">
                                                            <input type="checkbox" @checked($attends) @disabled(! $editable)
                                                                   onchange="this.form.attend.value = this.checked ? '1' : '0'; this.form.submit();"
                                                                   class="rounded border-gray-300 text-green-600 focus:ring-green-500">
                                                            <span class="font-medium">isst an diesem Tag</span>
                                                        </label>
                                                    </form>
                                                    {{-- Angebot des Tages (mit Allergen-/Warnhinweis) --}}
                                                    <div class="space-y-1.5">
                                                        @foreach ($items as $m)
                                                            @php $warn = $dishWarn($m->dish, $e['allergenIds'], $e['dietIds']); @endphp
                                                            <div class="flex items-center gap-2 rounded-md border px-2 py-1 {{ $warn ? 'border-red-300 bg-red-50' : 'border-gray-100' }}">
                                                                @if ($m->dish->photoUrl())
                                                                    <img src="{{ $m->dish->photoUrl() }}" alt="" class="h-8 w-8 flex-none rounded object-cover">
                                                                @else
                                                                    <div class="flex h-8 w-8 flex-none items-center justify-center rounded bg-gray-100 text-gray-300"><x-module-icon name="restaurant" class="text-sm" /></div>
                                                                @endif
                                                                <div class="min-w-0 flex-1">
                                                                    <div class="truncate text-xs font-medium text-gray-700">{{ $m->dish->name }}</div>
                                                                    @if ($m->dish->allergens->isNotEmpty())
                                                                        <div class="truncate text-[10px] {{ $warn ? 'text-red-500' : 'text-gray-400' }}"
                                                                             title="Allergene: {{ $m->dish->allergens->map(fn ($a) => $a->code.' '.$a->name)->join(', ') }}">Allergene: {{ $m->dish->allergens->pluck('code')->join(', ') }}</div>
                                                                    @endif
                                                                    @if ($m->dish->additives->isNotEmpty())
                                                                        <div class="truncate text-[10px] text-gray-400"
                                                                             title="Zusatzstoffe: {{ $m->dish->additives->map(fn ($a) => $a->code.' '.$a->name)->join(', ') }}">Zusatzstoffe: {{ $m->dish->additives->pluck('code')->join(', ') }}</div>
                                                                    @endif
                                                                </div>
                                                                @if ($warn)
                                                                    <span class="flex-none rounded-full bg-red-600 px-1.5 py-0.5 text-[10px] font-bold text-white" title="Passt nicht zur Sonderkost">⚠️</span>
                                                                @endif
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @else
                                                    {{-- Menü-Modus: pro Kategorie auswählbare Gericht-Karten --}}
                                                    @foreach ($items->groupBy(fn ($m) => $m->dish->category?->name ?? 'Ohne Kategorie') as $catName => $catItems)
                                                        @php
                                                            $catId = $catItems->first()->dish->category_id;
                                                            $catColor = $catItems->first()->dish->category?->color;
                                                            $cur = $selected[$eater->id][$dateStr][$catId] ?? '';
                                                        @endphp
                                                        <fieldset class="rounded-lg border px-2 pb-2 pt-1 {{ $catColor ? '' : 'border-gray-200' }}"
                                                                  @if ($catColor) style="border-color: {{ $catColor }}; background-color: {{ $catColor }}14;" @endif>
                                                            <legend class="px-1 text-[11px] font-medium uppercase tracking-wide {{ $catColor ? '' : 'text-gray-400' }}"
                                                                    @if ($catColor) style="color: {{ $catColor }};" @endif>{{ $catName }}</legend>

                                                            {{-- Handy: 2 Gerichte nebeneinander (vertikale Karten) · ab lg: 1-spaltig (schmale Tagesspalte) --}}
                                                            <div class="grid grid-cols-2 gap-2 lg:grid-cols-1 lg:gap-1.5">
                                                                @foreach ($catItems as $m)
                                                                    @php
                                                                        $isSel = (string) $cur === (string) $m->dish_id;
                                                                        $warn = $dishWarn($m->dish, $e['allergenIds'], $e['dietIds']);
                                                                        // Klickbar? Auswählen braucht Bestellfrist; die aktuell
                                                                        // gewählte Karte darf man bis zur Abbestell-Frist wieder lösen.
                                                                        $clickable = $day['canOrder'] || ($isSel && $day['canCancel']);
                                                                        $postDish = $isSel ? '' : $m->dish_id; // Klick auf Gewähltes = abwählen
                                                                    @endphp
                                                                    <form method="POST" action="{{ route('module.schulkantine.orders.store') }}">
                                                                        @csrf
                                                                        <input type="hidden" name="eater_id" value="{{ $eater->id }}">
                                                                        <input type="hidden" name="date" value="{{ $dateStr }}">
                                                                        <input type="hidden" name="category_id" value="{{ $catId }}">
                                                                        <input type="hidden" name="dish_id" value="{{ $postDish }}">
                                                                        <button type="submit" @disabled(! $clickable)
                                                                                class="group relative w-full overflow-hidden rounded-lg border text-left transition
                                                                                       {{ $isSel ? 'border-green-500 ring-2 ring-green-300' : ($warn ? 'border-red-300' : 'border-gray-200') }}
                                                                                       {{ $clickable ? 'hover:border-indigo-400 cursor-pointer' : 'opacity-60 cursor-not-allowed' }}">
                                                                            <div class="flex flex-col lg:flex-row lg:items-stretch">
                                                                                @if ($m->dish->photoUrl())
                                                                                    <img src="{{ $m->dish->photoUrl() }}" alt="" class="h-20 w-full flex-none object-cover lg:h-14 lg:w-14">
                                                                                @else
                                                                                    <div class="flex h-20 w-full flex-none items-center justify-center bg-gray-100 text-gray-300 lg:h-14 lg:w-14"><x-module-icon name="restaurant" class="text-lg" /></div>
                                                                                @endif
                                                                                <div class="min-w-0 flex-1 p-1.5 lg:py-1 lg:pl-2 lg:pr-1">
                                                                                    <div class="flex items-start justify-between gap-1">
                                                                                        <span class="text-xs font-semibold text-gray-800">{{ $m->dish->name }}</span>
                                                                                        <span class="flex flex-none items-center gap-1 text-xs font-bold {{ $isSel ? 'text-green-700' : 'text-gray-700' }}">
                                                                                            @if ($isSel)
                                                                                                <span class="flex h-4 w-4 items-center justify-center rounded-full bg-green-600 text-[10px] font-bold text-white">✓</span>
                                                                                            @endif
                                                                                            {{ $money($m->dish->price) }}
                                                                                        </span>
                                                                                    </div>
                                                                                    @if ($m->dish->allergens->isNotEmpty())
                                                                                        <div class="mt-0.5 truncate text-[10px] {{ $warn ? 'text-red-500 font-medium' : 'text-gray-400' }}"
                                                                                             title="Allergene: {{ $m->dish->allergens->map(fn ($a) => $a->code.' '.$a->name)->join(', ') }}">
                                                                                            Allergene: {{ $m->dish->allergens->pluck('code')->join(', ') }}
                                                                                        </div>
                                                                                    @endif
                                                                                    @if ($m->dish->additives->isNotEmpty())
                                                                                        <div class="truncate text-[10px] text-gray-400"
                                                                                             title="Zusatzstoffe: {{ $m->dish->additives->map(fn ($a) => $a->code.' '.$a->name)->join(', ') }}">
                                                                                            Zusatzstoffe: {{ $m->dish->additives->pluck('code')->join(', ') }}
                                                                                        </div>
                                                                                    @endif
                                                                                    @if ($warn)
                                                                                        <div class="mt-1 inline-flex items-center gap-1 rounded bg-red-600 px-1.5 py-0.5 text-[10px] font-bold text-white">⚠️ Nicht geeignet</div>
                                                                                    @endif
                                                                                </div>
                                                                            </div>
                                                                        </button>
                                                                    </form>
                                                                @endforeach
                                                            </div>
                                                        </fieldset>
                                                    @endforeach
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                @if ($hasSonderkost)
                                    <div class="border-t border-gray-100 px-4 py-2 text-[11px] text-gray-500">
                                        <span class="font-medium text-red-600">⚠️ Nicht geeignet</span> = enthält ein gemiedenes Allergen oder erfüllt eine geforderte Diät nicht ({{ $eater->name }}). Bestellen bleibt trotzdem möglich.
                                    </div>
                                @endif
                            @endif
                        </div>
                    @endforeach
                </div>

                <p class="mt-4 text-xs text-gray-400">
                    Auf eine Gericht-Karte tippen = auswählen (erneut tippen = abwählen). Grün umrandete Tage sind bereits bestellt.
                    Bestellschluss ist der vorige Öffnungstag; Abbestellen ist am Tag selbst bis zur eingestellten Uhrzeit möglich.
                </p>
            @endif
        @endif
    </div>
</x-app-layout>
