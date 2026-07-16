<!DOCTYPE html>
<html lang="de" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Ausgabe-Terminal · Schulkantine</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        [x-cloak] { display: none !important; }
        html, body { height: 100%; overscroll-behavior: none; }
        /* Grosse, touch-taugliche Stepper-Buttons. */
        .step-btn { user-select: none; -webkit-tap-highlight-color: transparent; }
    </style>
</head>
<body class="h-full bg-gray-100 font-sans antialiased text-gray-800">

@if (! $season)
    <div class="flex h-full items-center justify-center p-10 text-center">
        <div>
            <div class="text-2xl font-semibold text-gray-700">Keine aktive Saison</div>
            <p class="mt-2 text-gray-500">Es ist keine Saison aktiv – das Terminal kann nichts anzeigen.</p>
            <a href="{{ route('module.schulkantine.servings.index') }}" class="mt-6 inline-block rounded-lg bg-indigo-600 px-5 py-3 text-white">Zur normalen Ausgabe</a>
        </div>
    </div>
@else

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('terminal', () => ({
            // --- Serverdaten ---
            date: @js($date->toDateString()),
            csrf: document.querySelector('meta[name=csrf-token]').content,
            urls: {
                lookup: @js(route('module.schulkantine.servings.lookup')),
                lookupEater: @js(route('module.schulkantine.servings.lookup-eater')),
                search: @js(route('module.schulkantine.servings.terminal.search')),
                commit: @js(route('module.schulkantine.servings.terminal.commit')),
                base: @js(route('module.schulkantine.servings.terminal')),
            },
            open: @js($open),
            planGroups: @js($planGroups),
            walkinGroups: @js($walkinGroups),
            coins: @js(array_map('floatval', $coins)),
            simChips: @js($simChips),
            ogsPrice: @js((float) ($season->ogs_price ?? 0)),

            // --- Zustand ---
            person: null,          // eingelesener Esser (null = Leerlauf)
            choice: {},            // category_id -> dish_id | 'declined'
            orderMeta: {},         // category_id -> { order_id, orderedDishId }
            walkinQty: {},         // dish_id -> Anzahl
            coinQty: {},           // Betrag(String) -> Anzahl
            autoFinish: false,     // beim naechsten Chip automatisch buchen
            busy: false,
            banner: null,          // { ok, text }
            scanning: false,
            ctrl: null,
            servedOnLoad: false,   // war beim Stempeln schon etwas gebucht?
            searchOpen: false,
            searchQuery: '',
            searchResults: [],
            searching: false,

            init() {
                this.autoFinish = localStorage.getItem('kantineTerminalAutoFinish') === '1';
            },

            // ---- Preis-Helfer ----
            euro(v) { return (Math.round(v * 100) / 100).toFixed(2).replace('.', ',') + ' €'; },
            get walkinPrices() {
                const m = {};
                this.walkinGroups.forEach(g => g.dishes.forEach(d => { m[d.id] = d.price; }));
                return m;
            },
            get extrasCount() {
                let n = 0;
                Object.values(this.walkinQty).forEach(q => n += q);
                Object.values(this.coinQty).forEach(q => n += q);
                return n;
            },
            get extrasTotal() {
                let sum = 0;
                for (const [id, q] of Object.entries(this.walkinQty)) sum += (this.walkinPrices[id] || 0) * q;
                for (const [amt, q] of Object.entries(this.coinQty)) sum += parseFloat(amt) * q;
                return sum;
            },
            get isOgs() { return this.person && this.person.mode === 'ja_nein'; },
            // Ist alles im „frischen Stempel"-Zustand? (Menü = bestellt/genommen,
            // keine Extras.) Dann bringt „Zurück" nichts und wird ausgeblendet.
            get isDefaultState() {
                for (const q of Object.values(this.walkinQty)) if (q > 0) return false;
                for (const q of Object.values(this.coinQty)) if (q > 0) return false;
                for (const [catId, meta] of Object.entries(this.orderMeta)) {
                    if (this.choice[catId] !== meta.orderedDishId) return false;
                }
                return true;
            },
            get hasSomething() {
                if (this.extrasCount > 0) return true;
                // Menue-Auswahl zaehlt als Buchung, sobald der Esser eine Bestellung hat.
                if (this.person && this.person.hasOrder) return true;
                // War schon etwas gebucht, muss auch das Zuruecknehmen (auf 0) buchbar sein.
                return this.servedOnLoad;
            },

            // ---- Chip / Scan ----
            async startScan() {
                if (!('NDEFReader' in window)) { this.banner = { ok:false, text:'Dieses Geraet unterstuetzt kein Web-NFC. Bitte Simulation nutzen.' }; return; }
                try {
                    const reader = new NDEFReader();
                    this.ctrl = new AbortController();
                    await reader.scan({ signal: this.ctrl.signal });
                    this.scanning = true;
                    reader.onreading = (e) => { this.openFor(e.serialNumber || ''); };
                } catch (err) {
                    this.scanning = false;
                    this.banner = { ok:false, text:'Scan nicht moeglich: ' + (err && err.message ? err.message : err) };
                }
            },
            stopScan() { if (this.ctrl) this.ctrl.abort(); this.scanning = false; },

            // Offene Transaktion? Je nach Einstellung automatisch buchen oder nur warnen.
            // Gibt true zurück, wenn die nächste Person geladen werden darf.
            async guardPending() {
                if (!this.person || !this.hasSomething) return true;
                if (this.autoFinish) return await this.commit(true);
                this.banner = { ok:false, text:'Erst „Bestaetigen" oder „Abbrechen" – dann die naechste Person.' };
                return false;
            },

            async openFor(uid) {
                if (!uid) { this.banner = { ok:false, text:'Chip ohne Kennung.' }; return; }
                if (this.busy) return;
                if (! await this.guardPending()) return;
                this.busy = true;
                try {
                    const res = await fetch(this.urls.lookup, {
                        method: 'POST',
                        headers: { 'Content-Type':'application/json', 'Accept':'application/json', 'X-CSRF-TOKEN': this.csrf },
                        body: JSON.stringify({ uid, date: this.date }),
                    });
                    const data = await res.json();
                    if (!data.found) { this.banner = { ok:false, text:'Chip unbekannt oder zurueckgegeben.' }; this.busy = false; return; }
                    this.loadPerson(data);
                } catch (e) {
                    this.banner = { ok:false, text:'Fehler beim Lesen: ' + e };
                }
                this.busy = false;
            },

            // ---- Personen-Suche (Modal) ----
            openSearch() {
                this.searchOpen = true;
                this.searchQuery = '';
                this.searchResults = [];
                this.$nextTick(() => this.$refs.searchInput && this.$refs.searchInput.focus());
            },
            closeSearch() { this.searchOpen = false; },
            async doSearch() {
                const q = this.searchQuery.trim();
                if (!q) { this.searchResults = []; this.searching = false; return; }
                this.searching = true;
                try {
                    const res = await fetch(this.urls.search, {
                        method: 'POST',
                        headers: { 'Content-Type':'application/json', 'Accept':'application/json', 'X-CSRF-TOKEN': this.csrf },
                        body: JSON.stringify({ q }),
                    });
                    const data = await res.json();
                    this.searchResults = data.results || [];
                } catch (e) {
                    this.banner = { ok:false, text:'Suche fehlgeschlagen: ' + e };
                }
                this.searching = false;
            },
            async pickSearch(id) {
                if (this.busy) return;
                if (! await this.guardPending()) return;
                this.busy = true;
                try {
                    const res = await fetch(this.urls.lookupEater, {
                        method: 'POST',
                        headers: { 'Content-Type':'application/json', 'Accept':'application/json', 'X-CSRF-TOKEN': this.csrf },
                        body: JSON.stringify({ eater_id: id, date: this.date }),
                    });
                    const data = await res.json();
                    if (!data.found) { this.banner = { ok:false, text:'Person nicht gefunden.' }; this.busy = false; return; }
                    this.loadPerson(data);
                    this.closeSearch();
                } catch (e) {
                    this.banner = { ok:false, text:'Fehler beim Laden: ' + e };
                }
                this.busy = false;
            },

            loadPerson(data) {
                this.person = data;
                this.banner = null;
                this.resetSelection();
                // Wurde für diesen Chip heute schon etwas gebucht, den Stand wieder
                // herstellen – so kann man ihn ändern oder zurücknehmen.
                this.applyExisting(data);
            },

            // Setzt die Auswahl auf den „frischen Stempel"-Zustand: Menue vorausgewaehlt
            // (alles genommen), rechts alles auf null.
            resetSelection() {
                this.choice = {};
                this.orderMeta = {};
                this.walkinQty = {};
                this.coinQty = {};
                if (this.person && this.person.hasOrder) {
                    this.person.dishes.forEach(d => {
                        if (d.category_id == null) return;
                        this.orderMeta[d.category_id] = { order_id: d.order_id, orderedDishId: d.dish_id };
                        this.choice[d.category_id] = d.dish_id; // Vorauswahl = wie bestellt (genommen)
                    });
                }
            },

            // Legt den bereits gebuchten Stand über die Vorauswahl: erfasste Menü-
            // Ausgabe (genommen/Alternative/abgelehnt) sowie schon erfasste Extras
            // (Walk-in + Nachschlag) als Mengen.
            applyExisting(data) {
                this.servedOnLoad = false;
                if (data.hasOrder) {
                    data.dishes.forEach(d => {
                        if (d.category_id == null || !d.handled) return;
                        this.servedOnLoad = true;
                        this.choice[d.category_id] = d.declined ? 'declined' : (d.alternative ? 'alternative' : d.dish_id);
                    });
                }
                (data.walkin || []).forEach(w => {
                    this.servedOnLoad = true;
                    if (w.label === 'Nachschlag') {
                        const k = String(w.price);
                        this.coinQty[k] = (this.coinQty[k] || 0) + 1;
                    } else if (w.dish_id != null) {
                        this.walkinQty[w.dish_id] = (this.walkinQty[w.dish_id] || 0) + 1;
                    }
                });
            },

            // ---- Linke Spalte (Menue-Auswahl) ----
            catOrdered(catId) { return this.orderMeta[catId] !== undefined; },
            tileState(catId, dishId) {
                // Nur relevant, wenn eine Person mit Bestellung in dieser Kategorie da ist.
                if (!this.person || !this.person.hasOrder || !this.catOrdered(catId)) return 'idle';
                const ch = this.choice[catId];
                const ordered = this.orderMeta[catId].orderedDishId;
                if (ch === 'declined') return dishId === ordered ? 'declined' : 'idle';
                // Reload einer Alternative (welches Ersatzgericht ist nicht gespeichert):
                // am bestellten Gericht als „Alternative" anzeigen.
                if (ch === 'alternative') return dishId === ordered ? 'alt' : 'selectable';
                if (ch === dishId) return dishId === ordered ? 'taken' : 'alt';
                return 'selectable';
            },
            tileClickable(catId) {
                return this.person && this.person.hasOrder && this.catOrdered(catId) && !this.busy;
            },
            clickTile(catId, dishId) {
                if (!this.tileClickable(catId)) return;
                // Auf die aktuell gewaehlte Kachel tippen = abwaehlen (declined).
                this.choice[catId] = (this.choice[catId] === dishId) ? 'declined' : dishId;
            },

            // ---- Rechte Spalte (Extras) ----
            rightEnabled() { return this.person && !this.isOgs; },
            walkinPlus(id) { if (!this.rightEnabled()) return; this.walkinQty[id] = (this.walkinQty[id] || 0) + 1; },
            walkinMinus(id) { if (!this.walkinQty[id]) return; this.walkinQty[id] = Math.max(0, this.walkinQty[id] - 1); if (!this.walkinQty[id]) delete this.walkinQty[id]; },
            coinPlus(amt) { if (!this.rightEnabled()) return; const k = String(amt); this.coinQty[k] = (this.coinQty[k] || 0) + 1; },
            coinMinus(amt) { const k = String(amt); if (!this.coinQty[k]) return; this.coinQty[k] = Math.max(0, this.coinQty[k] - 1); if (!this.coinQty[k]) delete this.coinQty[k]; },

            // ---- Aktionen ----
            cancel() { this.person = null; this.servedOnLoad = false; this.resetSelection(); this.banner = null; },
            back() { this.resetSelection(); this.banner = null; }, // zurueck = frischer Stempel-Zustand

            toggleAutoFinish() {
                this.autoFinish = !this.autoFinish;
                localStorage.setItem('kantineTerminalAutoFinish', this.autoFinish ? '1' : '0');
            },

            buildPayload() {
                const menu = [];
                for (const [catId, meta] of Object.entries(this.orderMeta)) {
                    const ch = this.choice[catId];
                    let outcome = 'taken';
                    if (ch === 'declined') outcome = 'declined';
                    else if (ch === 'alternative' || ch !== meta.orderedDishId) outcome = 'alternative';
                    menu.push({ order_id: meta.order_id, outcome });
                }
                const walkin = Object.entries(this.walkinQty).map(([dish_id, qty]) => ({ dish_id: Number(dish_id), qty }));
                const nachschlag = Object.entries(this.coinQty).map(([amount, qty]) => ({ amount: parseFloat(amount), qty }));
                return { eater_id: this.person.user_id, date: this.date, menu, walkin, nachschlag };
            },

            async commit(silent = false) {
                if (!this.person) return false;
                if (this.busy) return false;
                this.busy = true;
                try {
                    const res = await fetch(this.urls.commit, {
                        method: 'POST',
                        headers: { 'Content-Type':'application/json', 'Accept':'application/json', 'X-CSRF-TOKEN': this.csrf },
                        body: JSON.stringify(this.buildPayload()),
                    });
                    const data = await res.json();
                    if (!data.ok) { this.banner = { ok:false, text: data.error || 'Buchung fehlgeschlagen.' }; this.busy = false; return false; }
                    if (data.plan) this.planGroups = data.plan; // Zaehler aktualisieren
                    if (!silent) this.banner = { ok:true, text: 'Gebucht: ' + data.name + (this.extrasTotal > 0 ? ' · Extras ' + this.euro(this.extrasTotal) : '') };
                    this.person = null;
                    this.servedOnLoad = false;
                    this.resetSelection();
                    this.busy = false;
                    return true;
                } catch (e) {
                    this.banner = { ok:false, text:'Fehler beim Buchen: ' + e };
                    this.busy = false;
                    return false;
                }
            },

            gotoDate(d) { if (d) window.location = this.urls.base + '?date=' + d; },
        }));
    });
</script>

<div x-data="terminal()" x-cloak class="flex h-full flex-col select-none">

    {{-- ================= INFOZEILE (10%) ================= --}}
    <header class="flex h-[10%] min-h-[72px] w-full items-stretch border-b border-gray-300 bg-white">

        {{-- Links: KW (+ Datum), anklickbar zum Wechseln --}}
        <div class="flex w-[12%] min-w-[120px] flex-col items-center justify-center border-r border-gray-200 bg-gray-50 px-2">
            <label class="cursor-pointer text-center leading-tight">
                <div class="text-2xl font-bold text-indigo-700">KW {{ $week['kw'] }}</div>
                <input type="date" value="{{ $date->toDateString() }}" @change="gotoDate($event.target.value)"
                       class="mt-1 w-full cursor-pointer rounded border-gray-300 text-[11px]">
            </label>
        </div>

        {{-- Rechts: Wochenüberblick ODER Person --}}
        <div class="relative flex-1 overflow-hidden">
            {{-- Wochenüberblick (Leerlauf) --}}
            <div x-show="!person" class="flex h-full items-stretch divide-x divide-gray-100 overflow-x-auto">
                @foreach ($week['days'] as $wd)
                    <div class="flex min-w-[9rem] flex-1 flex-col px-3 py-1 {{ $wd['isWorking'] ? 'bg-indigo-50/60' : '' }}">
                        <div class="flex items-baseline justify-between">
                            <span class="text-sm font-semibold {{ $wd['isWorking'] ? 'text-indigo-700' : 'text-gray-700' }}">{{ $wd['weekday'] }} {{ $wd['dayLabel'] }}</span>
                        </div>
                        <div class="mt-0.5 space-y-0.5 overflow-hidden text-[11px] leading-tight text-gray-500">
                            @forelse ($wd['dishes'] as $d)
                                <div class="flex justify-between gap-2">
                                    <span class="truncate">{{ $d['name'] }}</span>
                                    <span class="shrink-0 font-semibold text-gray-700">{{ $d['ordered'] }}×</span>
                                </div>
                            @empty
                                <div class="text-gray-300">–</div>
                            @endforelse
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Person (nach Stempeln). Kein „Keine Vorbestellung"-Hinweis mehr – das
                 sieht man bereits an der gesperrten linken Spalte; so wirkt die Zeile
                 nicht gequetscht. Aktionsknöpfe liegen jetzt im rechten Footer. --}}
            <div x-show="person" x-cloak class="flex h-full items-center gap-3 px-5">
                <span class="truncate text-3xl font-bold text-gray-800" x-text="person?.name"></span>
                <span class="shrink-0 rounded-full bg-indigo-100 px-3 py-1 text-lg font-semibold text-indigo-700" x-text="'Klasse: ' + (person?.group || '–')"></span>
                <span x-show="person?.warn" x-cloak class="shrink-0 rounded-full bg-red-600 px-3 py-1 text-base font-bold text-white">⚠️ Verträglichkeiten prüfen</span>
                <span x-show="servedOnLoad" x-cloak class="shrink-0 rounded-full bg-amber-100 px-3 py-1 text-base font-semibold text-amber-800">↺ bereits gebucht – änderbar</span>
                <span class="min-w-0 truncate text-sm text-gray-500" x-show="person && (person.allergens.length || person.diets.length)">
                    <span x-show="person?.allergens.length">Allergien: <span x-text="person?.allergens.join(', ')"></span>. </span>
                    <span x-show="person?.diets.length">Diäten: <span x-text="person?.diets.join(', ')"></span>.</span>
                </span>
            </div>
        </div>
    </header>

    {{-- Banner (Fehler/Erfolg) --}}
    <div x-show="banner" x-cloak
         class="absolute left-1/2 top-[11%] z-40 -translate-x-1/2 rounded-xl px-6 py-3 text-lg font-semibold shadow-lg"
         :class="banner?.ok ? 'bg-green-600 text-white' : 'bg-red-600 text-white'"
         @click="banner=null" x-text="banner?.text"></div>

    {{-- ================= ARBEITSZEILE (90%) ================= --}}
    <main class="flex min-h-0 flex-1">

        @if (! $open)
            <div class="flex flex-1 items-center justify-center text-center">
                <div>
                    <div class="text-2xl font-semibold text-amber-700">🔒 Heute geschlossen</div>
                    <p class="mt-2 text-gray-500">{{ $closedReason }}</p>
                </div>
            </div>
        @else

        {{-- ---------- LINKS: Menüs (1/3) ---------- --}}
        <section class="flex w-1/3 min-w-0 flex-col border-r border-gray-300 bg-white">
            <div class="flex items-center justify-between border-b border-gray-200 px-4 py-2">
                <h2 class="text-lg font-bold text-gray-700">Menüs</h2>
                <div class="flex items-center gap-3 text-[11px] font-semibold uppercase tracking-wide">
                    <span class="text-gray-500">bestellt</span>
                    <span class="text-amber-600">offen</span>
                    <span class="text-green-600">ausgeg.</span>
                </div>
            </div>

            <div class="relative flex-1 overflow-y-auto p-3">
                {{-- Sperr-Overlay, wenn Person ohne Vorbestellung --}}
                <div x-show="person && !person.hasOrder" x-cloak
                     class="absolute inset-0 z-20 flex items-center justify-center bg-gray-100/80 backdrop-blur-[1px]">
                    <div class="rounded-xl bg-white px-6 py-4 text-center shadow">
                        <div class="text-lg font-bold text-gray-700">Keine Vorbestellung</div>
                        <div class="mt-1 text-sm text-gray-500">Ausgabe nur über die rechte Seite.</div>
                    </div>
                </div>

                <div class="space-y-5">
                    <template x-for="group in planGroups" :key="group.category_id">
                        <fieldset class="rounded-2xl border-2 px-3 pb-3 pt-2"
                                  :style="group.color ? ('border-color:' + group.color + ';background-color:' + group.color + '10') : ''"
                                  :class="group.color ? '' : 'border-gray-200'">
                            <legend class="px-1.5 text-sm font-bold uppercase tracking-wide" :style="group.color ? ('color:' + group.color) : ''" x-text="group.category"></legend>
                            <div class="space-y-3">
                                <template x-for="dish in group.dishes" :key="dish.id">
                                    <button type="button"
                                            @click="clickTile(group.category_id, dish.id)"
                                            :disabled="!tileClickable(group.category_id)"
                                            class="flex w-full items-stretch gap-4 rounded-2xl border-2 p-3 text-left transition"
                                            :class="{
                                                'border-green-500 bg-green-50 ring-2 ring-green-300': tileState(group.category_id, dish.id)==='taken',
                                                'border-amber-500 bg-amber-50 ring-2 ring-amber-300': tileState(group.category_id, dish.id)==='alt',
                                                'border-red-300 bg-red-50 opacity-70': tileState(group.category_id, dish.id)==='declined',
                                                'border-indigo-300 bg-white hover:border-indigo-500': tileState(group.category_id, dish.id)==='selectable',
                                                'border-gray-200 bg-white': tileState(group.category_id, dish.id)==='idle',
                                                'cursor-default': !tileClickable(group.category_id)
                                            }">
                                        {{-- Bild oder Platzhalter-Illustration --}}
                                        <div class="h-24 w-24 shrink-0 overflow-hidden rounded-xl border border-black/5 bg-gray-50">
                                            <template x-if="dish.photo"><img :src="dish.photo" alt="" class="h-24 w-24 object-cover"></template>
                                            <template x-if="!dish.photo"><x-schulkantine::dish-placeholder /></template>
                                        </div>
                                        {{-- Inhalt --}}
                                        <div class="flex min-w-0 flex-1 flex-col">
                                            {{-- Zähler-Streifen --}}
                                            <div class="mb-1.5 flex items-center gap-2 font-bold">
                                                <span class="inline-flex h-8 min-w-8 items-center justify-center rounded-lg bg-gray-100 px-2 text-base text-gray-700" x-text="dish.ordered"></span>
                                                <span class="inline-flex h-8 min-w-8 items-center justify-center rounded-lg bg-amber-100 px-2 text-base text-amber-700" x-text="dish.open"></span>
                                                <span class="inline-flex h-8 min-w-8 items-center justify-center rounded-lg bg-green-100 px-2 text-base text-green-700" x-text="dish.served"></span>
                                                <span class="ml-auto text-base font-semibold text-gray-500" x-text="euro(dish.price)"></span>
                                            </div>
                                            <span class="text-xl font-bold leading-tight text-gray-800" x-text="dish.name"></span>
                                            <div x-show="dish.is_bundle" class="text-sm text-teal-700" x-text="dish.components.join(' + ')"></div>
                                            <div class="mt-auto pt-1 text-base font-semibold">
                                                <span x-show="tileState(group.category_id, dish.id)==='taken'" class="text-green-600">✓ wird ausgegeben</span>
                                                <span x-show="tileState(group.category_id, dish.id)==='alt'" class="text-amber-600">✓ Alternative</span>
                                                <span x-show="tileState(group.category_id, dish.id)==='declined'" class="text-red-500">✗ nicht genommen</span>
                                            </div>
                                        </div>
                                    </button>
                                </template>
                            </div>
                        </fieldset>
                    </template>
                    <div x-show="!planGroups.length" class="py-10 text-center text-gray-400">Kein vorbestellbares Menü heute.</div>
                </div>
            </div>
        </section>

        {{-- ---------- RECHTS: Besonderheiten (2/3) ---------- --}}
        <section class="flex w-2/3 min-w-0 flex-col bg-gray-50">
            <div class="flex items-center justify-between border-b border-gray-200 bg-white px-4 py-2">
                <h2 class="text-lg font-bold text-gray-700">Besonderheiten</h2>
                <label class="flex items-center gap-2 text-sm text-gray-500">
                    <input type="checkbox" :checked="autoFinish" @change="toggleAutoFinish()" class="rounded border-gray-300 text-indigo-600">
                    beim nächsten Chip automatisch buchen
                </label>
            </div>

            {{-- Inhalt: gesperrt, solange keine Person / OGS --}}
            <div class="relative flex min-h-0 flex-1 flex-col">
                <div x-show="!rightEnabled()" x-cloak
                     class="absolute inset-0 z-20 flex items-center justify-center bg-gray-100/70 backdrop-blur-[1px]">
                    <div class="rounded-xl bg-white px-6 py-4 text-center shadow">
                        <template x-if="!person"><div class="text-lg font-bold text-gray-600">Chip vorhalten oder simulieren</div></template>
                        <template x-if="isOgs"><div class="text-lg font-bold text-gray-600">OGS – keine Extras</div></template>
                    </div>
                </div>

                {{-- Oben: Walk-in-Artikel – drei getrennte Kacheln (Minus · Artikel · Plus),
                     danach die Anzahl × Summe. Kein gemeinsamer Rahmen. --}}
                <div class="min-h-0 flex-1 overflow-y-auto p-3">
                    <div class="mb-1 text-xs font-bold uppercase tracking-wide text-gray-400">Spontan mitnehmen</div>
                    <template x-for="group in walkinGroups" :key="group.category">
                        <div class="mb-3">
                            <div class="mb-1 text-xs font-semibold text-gray-500" x-text="group.category"></div>
                            <div class="space-y-2">
                                <template x-for="dish in group.dishes" :key="dish.id">
                                    <div class="flex items-center justify-center gap-8">
                                        <button type="button" @click="walkinMinus(dish.id)" :disabled="!walkinQty[dish.id]"
                                                class="step-btn flex h-16 w-16 shrink-0 items-center justify-center rounded-xl bg-gray-200 text-4xl font-bold text-gray-700 shadow-sm disabled:opacity-30">−</button>
                                        {{-- Artikel-Kachel wie links: Bild (oder Platzhalter) + Name + Preis. --}}
                                        <div class="flex w-[19rem] items-stretch gap-3 rounded-2xl border-2 bg-white p-2 shadow-sm"
                                             :class="walkinQty[dish.id] ? 'border-indigo-400 ring-2 ring-indigo-200' : 'border-gray-200'">
                                            <div class="h-20 w-20 shrink-0 overflow-hidden rounded-xl border border-black/5 bg-gray-50">
                                                <template x-if="dish.photo"><img :src="dish.photo" alt="" class="h-20 w-20 object-cover"></template>
                                                <template x-if="!dish.photo"><x-schulkantine::dish-placeholder /></template>
                                            </div>
                                            <div class="flex min-w-0 flex-1 flex-col justify-center pr-1">
                                                <span class="text-lg font-semibold leading-tight text-gray-800" x-text="dish.name"></span>
                                                <span class="mt-0.5 text-base font-medium text-gray-500" x-text="euro(dish.price)"></span>
                                            </div>
                                        </div>
                                        <button type="button" @click="walkinPlus(dish.id)"
                                                class="step-btn flex h-16 w-16 shrink-0 items-center justify-center rounded-xl bg-indigo-600 text-4xl font-bold text-white shadow-sm">+</button>
                                        <div class="min-w-[6.5rem] whitespace-nowrap text-lg font-bold"
                                             :class="walkinQty[dish.id] ? 'text-gray-900' : 'text-gray-300'">
                                            <span x-text="walkinQty[dish.id] || 0"></span>×
                                            <span class="text-sm font-medium" x-text="euro((walkinQty[dish.id] || 0) * dish.price)"></span>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                    <div x-show="!walkinGroups.length" class="text-sm text-gray-400">Heute keine Artikel zur spontanen Mitnahme.</div>
                </div>

                {{-- Unten: Nachschlag – drei getrennte Kacheln (Minus · Münze · Plus),
                     danach die Anzahl × Summe. Kein gemeinsamer Rahmen. --}}
                <div class="border-t border-gray-200 bg-white p-3">
                    <div class="mb-2 text-xs font-bold uppercase tracking-wide text-gray-400">Nachschlag</div>
                    <div class="space-y-2">
                        <template x-for="amt in coins" :key="amt">
                            <div class="flex items-center justify-center gap-8">
                                <button type="button" @click="coinMinus(amt)" :disabled="!coinQty[String(amt)]"
                                        class="step-btn flex h-16 w-16 shrink-0 items-center justify-center rounded-xl bg-gray-200 text-4xl font-bold text-gray-700 shadow-sm disabled:opacity-30">−</button>
                                {{-- Münz-Kachel --}}
                                <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-xl border bg-amber-50 p-1.5 shadow-sm"
                                     :class="coinQty[String(amt)] ? 'border-amber-400 ring-2 ring-amber-200' : 'border-amber-200'">
                                    <div class="h-12 w-12">
                                        <template x-if="amt === 0.5"><x-schulkantine::coin value="50c" /></template>
                                        <template x-if="amt === 1"><x-schulkantine::coin value="1e" /></template>
                                        <template x-if="amt === 2"><x-schulkantine::coin value="2e" /></template>
                                    </div>
                                </div>
                                <button type="button" @click="coinPlus(amt)"
                                        class="step-btn flex h-16 w-16 shrink-0 items-center justify-center rounded-xl bg-amber-500 text-4xl font-bold text-white shadow-sm">+</button>
                                <div class="min-w-[6.5rem] whitespace-nowrap text-lg font-bold"
                                     :class="coinQty[String(amt)] ? 'text-amber-900' : 'text-gray-300'">
                                    <span x-text="coinQty[String(amt)] || 0"></span>×
                                    <span class="text-sm font-medium" x-text="euro((coinQty[String(amt)] || 0) * amt)"></span>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Footer: links Reset (gelb) + Abbrechen (rot), rechts Gesamt + Buchen --}}
                <div class="flex items-center justify-between gap-4 border-t-2 border-gray-300 bg-white px-4 py-3">
                    <div class="flex items-center gap-2">
                        <button @click="back()" x-show="!isDefaultState" x-cloak
                                class="rounded-xl bg-amber-400 px-5 py-3 text-base font-bold text-amber-950 shadow hover:bg-amber-500">↺ Reset</button>
                        <button @click="cancel()"
                                class="rounded-xl bg-red-600 px-5 py-3 text-base font-bold text-white shadow hover:bg-red-700">Abbrechen</button>
                    </div>
                    <div class="flex items-center gap-5">
                        <div class="text-right">
                            <div class="text-[11px] uppercase tracking-wide text-gray-400">Gesamt (nur Extras)</div>
                            <div class="text-3xl font-extrabold text-gray-900" x-text="euro(extrasTotal)"></div>
                        </div>
                        <button @click="commit()" :disabled="busy || !hasSomething"
                                class="rounded-2xl bg-green-600 px-8 py-4 text-xl font-bold text-white shadow hover:bg-green-700 disabled:cursor-not-allowed disabled:opacity-40">✓ Bestätigen</button>
                    </div>
                </div>
            </div>
        </section>
        @endif
    </main>

    {{-- Steuerleiste unten links: Chip(-Simulation) + Terminal verlassen nebeneinander --}}
    <div class="fixed bottom-3 left-3 z-30 flex items-center gap-2">
        <div class="relative" x-data="{ openSim: false }">
            <button @click="openSim = !openSim" class="rounded-full bg-gray-800/80 px-4 py-2 text-sm font-medium text-white shadow-lg">
                <span x-show="!scanning">🔌 Chip</span>
                <span x-show="scanning" x-cloak>📡 Scan aktiv</span>
            </button>
            <div x-show="openSim" x-cloak @click.outside="openSim=false"
                 class="absolute bottom-12 left-0 max-h-[60vh] w-72 overflow-y-auto rounded-xl border border-gray-200 bg-white p-3 shadow-2xl">
                <div class="mb-2 flex items-center gap-2">
                    <button x-show="!scanning" @click="startScan()" class="flex-1 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white">NFC-Scan starten</button>
                    <button x-show="scanning" x-cloak @click="stopScan()" class="flex-1 rounded-lg bg-red-600 px-3 py-2 text-sm font-medium text-white">Scan stoppen</button>
                </div>
                <div class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-400">Chip simulieren</div>
                <div class="space-y-1">
                    <template x-for="c in simChips" :key="c.uid">
                        <button @click="openFor(c.uid); openSim=false" class="block w-full truncate rounded-lg px-2 py-1.5 text-left text-sm text-gray-700 hover:bg-indigo-50" x-text="c.name"></button>
                    </template>
                    <div x-show="!simChips.length" class="px-2 py-1 text-xs text-gray-400">Keine aktiven Chips.</div>
                </div>
            </div>
        </div>

        <button @click="openSearch()" class="rounded-full bg-gray-800/80 px-4 py-2 text-sm font-medium text-white shadow-lg hover:bg-gray-700">🔍 Suchen</button>

        <a href="{{ route('module.schulkantine.servings.index') }}"
           class="rounded-full bg-gray-800/70 px-4 py-2 text-sm font-medium text-white shadow-lg hover:bg-gray-800">✕ Terminal verlassen</a>
    </div>

    {{-- Such-Modal: Live-Suche nach Person (max. 3 Treffer, Name + Klasse) --}}
    <div x-show="searchOpen" x-cloak
         class="fixed inset-0 z-50 flex items-start justify-center bg-black/40 p-6 pt-24"
         @click.self="closeSearch()" @keydown.escape.window="closeSearch()">
        <div class="w-full max-w-lg rounded-2xl bg-white p-5 shadow-2xl">
            <div class="mb-3 flex items-center justify-between">
                <h3 class="text-lg font-bold text-gray-800">Person suchen</h3>
                <button @click="closeSearch()" class="rounded-lg px-2 py-1 text-gray-400 hover:bg-gray-100">✕</button>
            </div>
            <input type="search" x-model="searchQuery" @input.debounce.200ms="doSearch()" x-ref="searchInput"
                   placeholder="Namen eingeben …"
                   class="w-full rounded-xl border-gray-300 px-4 py-3 text-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <div class="mt-3 space-y-2">
                <template x-for="r in searchResults" :key="r.id">
                    <button @click="pickSearch(r.id)"
                            class="flex w-full items-center justify-between gap-3 rounded-xl border border-gray-200 p-4 text-left hover:border-indigo-400 hover:bg-indigo-50">
                        <span class="truncate text-lg font-semibold text-gray-800" x-text="r.name"></span>
                        <span class="shrink-0 rounded-full bg-indigo-100 px-3 py-1 text-sm font-semibold text-indigo-700" x-text="'Klasse: ' + (r.group || '–')"></span>
                    </button>
                </template>
                <div x-show="searchQuery.trim() && !searchResults.length && !searching" x-cloak class="py-3 text-center text-sm text-gray-400">Keine Treffer.</div>
                <div x-show="!searchQuery.trim()" x-cloak class="py-3 text-center text-sm text-gray-400">Tippe einen Namen – es werden bis zu drei Treffer angezeigt.</div>
            </div>
        </div>
    </div>

</div>
@endif
</body>
</html>
