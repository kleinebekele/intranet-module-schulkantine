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
                    <div class="text-[11px] uppercase tracking-wide text-indigo-400">Kosten im {{ $monthStart->isoFormat('MMMM YYYY') }}</div>
                    <div class="text-lg font-bold text-indigo-700" id="month-total">{{ $money($monthTotal) }}</div>
                </div>
            @endif
        </div>
    </x-slot>

    <div class="max-w-full" id="orders-content">
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
                    <div class="text-sm font-semibold text-gray-800">KW {{ $weekStart->isoWeek() }} · {{ $weekStart->format('d.m.') }} – {{ $weekEnd->format('d.m.Y') }}</div>
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
                    $dishWarn = function ($dish, array $allergenIds, array $dietIds) use ($season) {
                        // Warnung nur für das, was diese Saison überhaupt einblendet.
                        $hasAllergen = ($season->show_allergens ?? true)
                            && $dish->allergens->pluck('id')->intersect($allergenIds)->isNotEmpty();
                        $conflictsDiet = ($season->show_diets ?? true)
                            && $dish->unsuitableDiets->pluck('id')->intersect($dietIds)->isNotEmpty();
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
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-semibold text-gray-800">{{ $eater->name }}</span>
                                    @if ($eater->id === auth()->id())
                                        <span class="rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-600">ich</span>
                                    @endif
                                    <span class="text-xs text-gray-400">{{ $e['group']?->name ?? 'keine Gruppe' }}</span>
                                    @if ($hasSonderkost)
                                        <span class="rounded-full bg-red-50 px-2 py-0.5 text-[11px] font-medium text-red-600" title="Es sind Verträglichkeiten hinterlegt">⚠️ Verträglichkeiten</span>
                                    @endif
                                    @if ($isOgs)
                                        @php $wdKurz = [1 => 'Mo', 2 => 'Di', 3 => 'Mi', 4 => 'Do', 5 => 'Fr', 6 => 'Sa', 7 => 'So']; @endphp
                                        <div x-data="{ open: false }" class="inline-flex items-center gap-2">
                                            @if ($isSubscribed)
                                                @php $aboTage = collect($openingWeekdays)->sort()->filter(fn ($d) => in_array($d, $e['aboWeekdays']))->map(fn ($d) => $wdKurz[$d])->join(', '); @endphp
                                                <span class="text-xs text-gray-500">🔁 Abo aktiv – isst {{ $aboTage ?: 'nichts' }}</span>
                                                <button type="button" @click="open = true" class="rounded-md border border-gray-200 bg-white px-2 py-0.5 text-xs font-medium text-gray-700 hover:bg-gray-50">Tage ändern</button>
                                                <form method="POST" action="{{ route('module.schulkantine.orders.subscription') }}" class="inline">
                                                    @csrf
                                                    <input type="hidden" name="eater_id" value="{{ $eater->id }}">
                                                    <input type="hidden" name="active" value="0">
                                                    <button type="submit" class="rounded-md border border-red-200 bg-white px-2 py-0.5 text-xs font-medium text-red-600 hover:bg-red-50">Abo abbestellen</button>
                                                </form>
                                            @else
                                                <span class="text-xs text-amber-600">Abo aus – nur angehakte Tage</span>
                                                <button type="button" @click="open = true" class="rounded-md border border-green-200 bg-white px-2 py-0.5 text-xs font-medium text-green-700 hover:bg-green-50">Abo aktivieren</button>
                                            @endif

                                            {{-- Modal: Standardtage des Kindes wählen (Abo aktivieren/anpassen) --}}
                                            <div x-show="open" x-cloak @keydown.escape.window="open = false"
                                                 class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display:none;">
                                                <div class="absolute inset-0 bg-black/40" @click="open = false"></div>
                                                <form method="POST" action="{{ route('module.schulkantine.orders.subscription') }}"
                                                      class="relative z-10 w-full max-w-sm rounded-2xl bg-white p-5 text-left shadow-xl">
                                                    @csrf
                                                    <input type="hidden" name="eater_id" value="{{ $eater->id }}">
                                                    <input type="hidden" name="active" value="1">
                                                    <h3 class="text-base font-semibold text-gray-800">Standardtage – {{ $eater->name }}</h3>
                                                    <p class="mt-1 text-xs text-gray-500">An welchen Wochentagen isst {{ $eater->name }} standardmäßig? Einzelne Tage lassen sich weiter an- und abhaken; an Schließtagen wird ohnehin nichts bestellt.</p>
                                                    <div class="mt-3 flex flex-wrap gap-2">
                                                        @foreach (collect($openingWeekdays)->sort() as $wd)
                                                            <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">
                                                                <input type="checkbox" name="weekdays[]" value="{{ $wd }}" @checked(in_array($wd, $e['aboWeekdays'])) class="rounded border-gray-300 text-green-600 focus:ring-green-500">
                                                                {{ $wdKurz[$wd] }}
                                                            </label>
                                                        @endforeach
                                                    </div>
                                                    <p class="mt-2 text-[11px] text-gray-400">Nichts angehakt = isst an allen Öffnungstagen.</p>
                                                    <div class="mt-4 flex justify-end gap-2">
                                                        <button type="button" @click="open = false" class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50">Abbrechen</button>
                                                        <button type="submit" class="rounded-lg bg-green-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-green-700">Speichern</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                                {{-- Offener Betrag DIESER Person im angezeigten Monat --}}
                                <div class="flex items-center gap-1.5">
                                    <span class="text-[11px] uppercase tracking-wide text-gray-400">Kosten {{ $monthStart->isoFormat('MMM') }}</span>
                                    <span class="rounded-full bg-indigo-50 px-2 py-0.5 text-sm font-bold text-indigo-700">{{ $money($monthByUser[$eater->id] ?? 0) }}</span>
                                </div>
                            </div>

                            @if (! $e['group'])
                                <div class="px-4 py-4 text-sm text-gray-400">Für diese Person ist keine Kundengruppe hinterlegt.</div>
                            @else
                                {{-- Tage als umbrechendes Raster: jede Karte will mindestens 15.5rem, teilt sich
                                     die Breite gleichmäßig und rutscht in die nächste Zeile, wenn kein Platz mehr ist
                                     (kein horizontaler Scroll). Das min() verhindert Überlauf, wenn der Platz
                                     schmaler als die Mindestbreite ist – dann bleibt eine Spalte übrig.
                                     Grauer Canvas + Schatten je Karte, damit die Tage klar getrennt sind. --}}
                                <div class="grid grid-cols-[repeat(auto-fit,minmax(min(15.5rem,100%),1fr))] gap-3 bg-gray-50 p-3 sm:p-4 sm:gap-4">
                                    @foreach ($days as $day)
                                        @php
                                            $dateStr = $day['date']->toDateString();
                                            $items = collect($plan[$dateStr] ?? []);
                                            $eaterTotal = $dayTotals[$eater->id][$dateStr] ?? 0;
                                            if ($isOgs) {
                                                // Standardtag aus dem Abo-Muster; einzelne An-/Abmeldung schlägt es.
                                                $default = $isSubscribed && in_array($day['date']->dayOfWeekIso, $e['aboWeekdays']);
                                                $attends = isset($ogsOrdered[$eater->id][$dateStr])
                                                    ? true
                                                    : (isset($ogsCancelled[$eater->id][$dateStr]) ? false : $default);
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
                                        <div class="flex w-full flex-col overflow-hidden rounded-xl border shadow-sm {{ $col }}">
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
                                                        <span class="rounded-full bg-green-600 px-2 py-0.5 text-xs font-semibold text-white">{{ $ogsPrice > 0 ? '✓ '.$money($ogsPrice) : '✓ isst' }}</span>
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
                                                @elseif ($isOgs)
                                                    {{-- OGS: nur ja/nein – die konkreten Speisen sind für OGS irrelevant.
                                                         Steht bewusst VOR der „kein Angebot"-Prüfung, damit OGS auch an
                                                         Öffnungstagen ohne Speiseplan-Eintrag teilnehmen kann. --}}
                                                    @php $editable = ($attends && $day['canCancel']) || (! $attends && $day['canOrder']); @endphp
                                                    <form method="POST" action="{{ route('module.schulkantine.orders.store') }}">
                                                        @csrf
                                                        <input type="hidden" name="eater_id" value="{{ $eater->id }}">
                                                        <input type="hidden" name="date" value="{{ $dateStr }}">
                                                        <input type="hidden" name="attend" value="{{ $attends ? '1' : '0' }}">
                                                        <label class="flex items-center gap-2 rounded-lg border px-3 py-2 text-sm {{ $attends ? 'border-green-300 bg-green-50 text-green-800' : 'border-gray-200 text-gray-700' }} {{ $editable ? 'cursor-pointer' : 'opacity-60' }}">
                                                            <input type="checkbox" @checked($attends) @disabled(! $editable)
                                                                   onchange="this.form.attend.value = this.checked ? '1' : '0'; this.form.requestSubmit();"
                                                                   class="rounded border-gray-300 text-green-600 focus:ring-green-500">
                                                            <span class="font-medium">isst an diesem Tag</span>
                                                        </label>
                                                    </form>
                                                @elseif ($items->isEmpty())
                                                    <p class="py-6 text-center text-xs text-gray-400">kein Angebot</p>
                                                @else
                                                    {{-- Menü-Modus: pro Kategorie auswählbare Gericht-Karten --}}
                                                    @foreach ($items->groupBy(fn ($m) => $m->dish->category?->name ?? 'Ohne Kategorie') as $catName => $catItems)
                                                        @php
                                                            $catId = $catItems->first()->dish->category_id;
                                                            $category = $catItems->first()->dish->category;
                                                        @endphp
                                                        {{-- „Nur spontan"-Kategorien stehen auf dem Speiseplan (damit sie bei der
                                                             Ausgabe am Tresen erscheinen), sind hier aber nicht vorbestellbar.
                                                             ENTSCHEIDUNG OFFEN: vorerst komplett ausblenden. Sollen sie später
                                                             sichtbar-aber-gesperrt erscheinen („gibt es heute spontan"), genügt es,
                                                             dieses @continue durch eine Deaktivierung der Karten zu ersetzen –
                                                             der Controller lehnt die Bestellung ohnehin ab. --}}
                                                        @continue($category && ! $category->allows_preorder)
                                                        {{-- Kategorien, die Eltern für dieses Kind gesperrt haben, ausblenden. --}}
                                                        @continue(in_array($catId, $e['blockedCats'] ?? []))
                                                        @php
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
                                                                        // Gericht, dessen Kategorie für dieses Kind gesperrt ist,
                                                                        // gar nicht erst anbieten (der Controller lehnt es zusätzlich
                                                                        // serverseitig ab).
                                                                        $occupied = array_values(array_filter([$m->dish->category_id]));
                                                                    @endphp
                                                                    @continue(array_intersect($occupied, $e['blockedCats'] ?? []) !== [])
                                                                    @php
                                                                        $isSel = (string) $cur === (string) $m->dish_id;
                                                                        $warn = $dishWarn($m->dish, $e['allergenIds'], $e['dietIds']);
                                                                        // Klickbar? Auswählen braucht Bestellfrist; die aktuell
                                                                        // gewählte Karte darf man bis zur Abbestell-Frist wieder lösen.
                                                                        $clickable = $day['canOrder'] || ($isSel && $day['canCancel']);
                                                                        $postDish = $isSel ? '' : $m->dish_id; // Klick auf Gewähltes = abwählen

                                                                        // Alle Details fürs Info-Modal (Alpine liest dieses Objekt beim Klick).
                                                                        $dishData = [
                                                                            'name' => $m->dish->name,
                                                                            'category' => $m->dish->category?->name,
                                                                            'categoryColor' => $m->dish->category?->color,
                                                                            'price' => $money($m->dish->price),
                                                                            'description' => $m->dish->description,
                                                                            'photo' => $m->dish->photoUrl(),
                                                                            'allergens' => ($season->show_allergens ?? true) ? $m->dish->allergens->map(fn ($a) => trim($a->code.' '.$a->name))->values() : [],
                                                                            'additives' => ($season->show_additives ?? true) ? $m->dish->additives->map(fn ($a) => trim($a->code.' '.$a->name))->values() : [],
                                                                            'diets' => ($season->show_diets ?? true) ? $m->dish->unsuitableDiets->map(fn ($d) => $d->name)->values() : [],
                                                                            // Bestell-Kontext, damit man auch aus dem Modal bestellen/abbestellen kann.
                                                                            'eaterId' => $eater->id,
                                                                            'date' => $dateStr,
                                                                            'categoryId' => $catId,
                                                                            'isSel' => $isSel,
                                                                            'clickable' => $clickable,
                                                                            'postDish' => (string) $postDish,
                                                                            'orderable' => true,
                                                                        ];

                                                                        // Eine gewählte Kachel wird in ihrer KATEGORIEFARBE umrandet, nicht
                                                                        // grün: Wo nur eine Kategorie bestellbar ist, wäre sonst nach dem
                                                                        // Bestellen alles grün und man unterschiede nichts mehr. Dass etwas bestellt ist, sagen weiterhin
                                                                        // der grüne Haken, der grüne Preis und der Streifen an der linken
                                                                        // Kante (unten) – das Signal geht also nicht verloren.
                                                                        // Ohne Kategoriefarbe bleibt es beim bisherigen Grün.
                                                                        $selStyle = $isSel && $catColor
                                                                            ? 'border-color: '.$catColor.'; box-shadow: 0 0 0 2px '.$catColor.'59;'
                                                                            : '';
                                                                    @endphp
                                                                    <form method="POST" action="{{ route('module.schulkantine.orders.store') }}" class="relative">
                                                                        @csrf
                                                                        <input type="hidden" name="eater_id" value="{{ $eater->id }}">
                                                                        <input type="hidden" name="date" value="{{ $dateStr }}">
                                                                        <input type="hidden" name="category_id" value="{{ $catId }}">
                                                                        <input type="hidden" name="dish_id" value="{{ $postDish }}">
                                                                        <button type="submit" @disabled(! $clickable) style="{{ $selStyle }}"
                                                                                class="group relative w-full overflow-hidden rounded-lg border text-left transition
                                                                                       {{ $isSel ? ($catColor ? '' : 'border-green-500 ring-2 ring-green-300') : ($warn ? 'border-red-300' : 'border-gray-200') }}
                                                                                       {{ $clickable ? 'hover:border-indigo-400 cursor-pointer' : 'opacity-60 cursor-not-allowed' }}">
                                                                            @if ($isSel)
                                                                                {{-- „bestellt" an der linken Kante – bleibt sichtbar, auch wenn
                                                                                     der Rahmen jetzt die Kategoriefarbe trägt. --}}
                                                                                <span class="absolute inset-y-0 left-0 z-10 w-1 bg-green-500" aria-hidden="true"></span>
                                                                            @endif
                                                                            <div class="flex flex-col lg:flex-row lg:items-stretch">
                                                                                @if ($m->dish->photoUrl())
                                                                                    <img src="{{ $m->dish->photoUrl() }}" alt="" class="h-20 w-full flex-none object-cover lg:h-14 lg:w-14">
                                                                                @else
                                                                                    <div class="flex h-20 w-full flex-none items-center justify-center bg-gray-100 text-gray-300 lg:h-14 lg:w-14"><x-module-icon name="restaurant" class="text-lg" /></div>
                                                                                @endif
                                                                                <div class="min-w-0 flex-1 p-1.5 lg:py-1 lg:pl-2 lg:pr-1">
                                                                                    <div class="flex items-start justify-between gap-1">
                                                                                        <span class="text-xs font-semibold text-gray-800">{{ $m->dish->name }}<span x-data @click.stop.prevent="$dispatch('open-dish', @js($dishData))" role="button" tabindex="0" title="Details anzeigen" aria-label="Details anzeigen" class="ml-1 inline-flex translate-y-px cursor-pointer align-middle text-indigo-500 hover:text-indigo-700"><svg class="inline h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg></span></span>
                                                                                        <span class="flex flex-none items-center gap-1 text-xs font-bold {{ $isSel ? 'text-green-700' : 'text-gray-700' }}">
                                                                                            @if ($isSel)
                                                                                                <span class="flex h-4 w-4 items-center justify-center rounded-full bg-green-600 text-[10px] font-bold text-white">✓</span>
                                                                                            @endif
                                                                                            {{ $money($m->dish->price) }}
                                                                                        </span>
                                                                                    </div>
                                                                                    @php
                                                                                        $effAllergens = $m->dish->allergens;
                                                                                        $effAdditives = $m->dish->additives;
                                                                                    @endphp
                                                                                    @if (($season->show_allergens ?? true) && $effAllergens->isNotEmpty())
                                                                                        <div class="mt-0.5 truncate text-[10px] {{ $warn ? 'text-red-500 font-medium' : 'text-gray-400' }}"
                                                                                             title="Allergene: {{ $effAllergens->map(fn ($a) => $a->code.' '.$a->name)->join(', ') }}">
                                                                                            Allergene: {{ $effAllergens->pluck('code')->join(', ') }}
                                                                                        </div>
                                                                                    @endif
                                                                                    @if (($season->show_additives ?? true) && $effAdditives->isNotEmpty())
                                                                                        <div class="truncate text-[10px] text-gray-400"
                                                                                             title="Zusatzstoffe: {{ $effAdditives->map(fn ($a) => $a->code.' '.$a->name)->join(', ') }}">
                                                                                            Zusatzstoffe: {{ $effAdditives->pluck('code')->join(', ') }}
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

                                @if ($hasSonderkost && (($season->show_allergens ?? true) || ($season->show_diets ?? true)))
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

    {{-- Detail-Modal für die Info-Icons der Gerichte (ein Modal für die ganze Seite;
         die Info-Buttons schicken ihre Gericht-Daten per Alpine-Event hierher). --}}
    <div x-data="{ open: false, dish: {} }"
         x-on:open-dish.window="dish = $event.detail; open = true"
         @keydown.escape.window="open = false"
         x-show="open" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         style="display:none;">
        <div class="absolute inset-0 bg-black/40" @click="open = false"></div>
        <div class="relative z-10 max-h-[85vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white shadow-xl">
            <button type="button" @click="open = false" aria-label="Schließen"
                    class="absolute right-3 top-3 z-20 rounded-full bg-white/90 p-1.5 text-gray-500 shadow ring-1 ring-black/5 hover:bg-white hover:text-gray-700">
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
            </button>
            <template x-if="dish.photo">
                <img :src="dish.photo" alt="" class="h-44 w-full rounded-t-2xl object-cover">
            </template>
            <div class="p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800" x-text="dish.name"></h2>
                        <span x-show="dish.category" class="mt-1 inline-block rounded-full px-2 py-0.5 text-xs font-medium"
                              :style="dish.categoryColor ? ('background-color:'+dish.categoryColor+'22; color:'+dish.categoryColor) : 'background-color:#eef2ff; color:#4f46e5'"
                              x-text="dish.category"></span>
                    </div>
                    <span class="mr-8 whitespace-nowrap text-lg font-bold text-indigo-700" x-text="dish.price"></span>
                </div>

                <p x-show="dish.description" class="mt-3 whitespace-pre-line text-sm text-gray-600" x-text="dish.description"></p>

                {{-- Allergene/Zusatzstoffe/Diäten des Gerichts. --}}
                <template x-if="dish.allergens && dish.allergens.length">
                    <div class="mt-4">
                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-400">Allergene</div>
                        <div class="mt-1 flex flex-wrap gap-1">
                            <template x-for="a in dish.allergens" :key="a">
                                <span class="rounded bg-red-50 px-1.5 py-0.5 text-[11px] text-red-700" x-text="a"></span>
                            </template>
                        </div>
                    </div>
                </template>

                <template x-if="dish.additives && dish.additives.length">
                    <div class="mt-4">
                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-400">Zusatzstoffe</div>
                        <div class="mt-1 flex flex-wrap gap-1">
                            <template x-for="a in dish.additives" :key="a">
                                <span class="rounded bg-gray-100 px-1.5 py-0.5 text-[11px] text-gray-600" x-text="a"></span>
                            </template>
                        </div>
                    </div>
                </template>

                <template x-if="dish.diets && dish.diets.length">
                    <div class="mt-4">
                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-400">Nicht geeignet bei</div>
                        <div class="mt-1 flex flex-wrap gap-1">
                            <template x-for="d in dish.diets" :key="d">
                                <span class="rounded bg-amber-50 px-1.5 py-0.5 text-[11px] text-amber-700" x-text="d"></span>
                            </template>
                        </div>
                    </div>
                </template>

                {{-- Aktionen: Bestellen/Abbestellen (schließt danach) + Schließen --}}
                <div class="mt-5 flex items-center justify-end gap-2 border-t border-gray-100 pt-4">
                    <button type="button" @click="open = false" class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50">Schließen</button>
                    <form method="POST" action="{{ route('module.schulkantine.orders.store') }}" @submit="open = false">
                        @csrf
                        <input type="hidden" name="eater_id" :value="dish.eaterId">
                        <input type="hidden" name="date" :value="dish.date">
                        <input type="hidden" name="category_id" :value="dish.categoryId">
                        <input type="hidden" name="dish_id" :value="dish.postDish">
                        <template x-if="dish.clickable">
                            <button type="submit"
                                    class="rounded-lg px-4 py-1.5 text-sm font-medium text-white"
                                    :class="dish.isSel ? 'bg-red-600 hover:bg-red-700' : 'bg-green-600 hover:bg-green-700'"
                                    x-text="dish.isSel ? 'Abbestellen' : 'Bestellen'"></button>
                        </template>
                        <template x-if="! dish.clickable">
                            <span class="self-center text-xs text-amber-600">Bestellfrist abgelaufen</span>
                        </template>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Bestellungen ohne Seiten-Sprung: Formulare per fetch senden und nur den
         Bestell-Bereich austauschen. Die Scrollposition bleibt so erhalten.
         Fällt JS aus, senden die Formulare ganz normal (Server-Redirect). --}}
    @once
        <script>
        (function () {
            document.addEventListener('submit', async function (e) {
                const form = e.target;
                if (!(form instanceof HTMLFormElement)) return;
                const action = form.action || '';
                if (action.indexOf('/schulkantine/bestellen') === -1) return;

                e.preventDefault();
                const live = document.getElementById('orders-content');
                if (!live) { form.submit(); return; }

                try {
                    const res = await fetch(action, {
                        method: 'POST',
                        body: new FormData(form),
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
                        credentials: 'same-origin',
                    });
                    const doc = new DOMParser().parseFromString(await res.text(), 'text/html');
                    const fresh = doc.getElementById('orders-content');
                    if (fresh) {
                        live.innerHTML = fresh.innerHTML;
                        // Monats-Summe oben (außerhalb #orders-content) mitziehen.
                        const freshTotal = doc.getElementById('month-total');
                        const liveTotal = document.getElementById('month-total');
                        if (freshTotal && liveTotal) {
                            liveTotal.textContent = freshTotal.textContent;
                        }
                    } else {
                        window.location.reload();
                    }
                } catch (err) {
                    window.location.reload();
                }
            });
        })();
        </script>
    @endonce
</x-app-layout>
