<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2">
            <x-module-icon name="restaurant" class="text-2xl text-indigo-600" />
            <h1 class="text-xl font-semibold text-gray-800">Ausgabe</h1>
        </div>
    </x-slot>

    <div class="max-w-5xl">
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
            {{-- Tages-Navigation --}}
            <div class="mb-4 flex items-center justify-between gap-4">
                <div>
                    @if ($prevDate)
                        <a href="{{ route('module.schulkantine.servings.index', array_merge(['date' => $prevDate], $navExtra)) }}"
                           class="inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">‹ Vorheriger Tag</a>
                    @endif
                </div>
                <div class="text-center">
                    <div class="text-sm font-semibold text-gray-800">{{ $date->isoFormat('dddd') }}, {{ $date->format('d.m.Y') }}</div>
                    <div class="text-xs text-gray-400">Saison „{{ $season->name }}"</div>
                </div>
                <div>
                    @if ($nextDate)
                        <a href="{{ route('module.schulkantine.servings.index', array_merge(['date' => $nextDate], $navExtra)) }}"
                           class="inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Nächster Tag ›</a>
                    @endif
                </div>
            </div>

            {{-- Umschalter Tagesmenü ⇄ OGS + Link zu Mengen --}}
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div class="inline-flex rounded-lg border border-gray-200 bg-white p-0.5 text-sm">
                    <a href="{{ route('module.schulkantine.servings.index', ['date' => $date->toDateString(), 'group' => 'menu']) }}"
                       class="rounded-md px-3 py-1.5 font-medium {{ $group === 'menu' ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-50' }}">Tagesmenü</a>
                    <a href="{{ route('module.schulkantine.servings.index', ['date' => $date->toDateString(), 'group' => 'ogs']) }}"
                       class="rounded-md px-3 py-1.5 font-medium {{ $group === 'ogs' ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-50' }}">OGS</a>
                </div>
                <div class="flex items-center gap-4">
                    <a href="{{ route('module.schulkantine.servings.quantities', ['date' => $date->toDateString()]) }}"
                       class="inline-flex items-center gap-1 text-sm font-medium text-indigo-600 hover:text-indigo-800">
                        <x-module-icon name="bar-chart-alt-2" class="text-base" /> Mengen
                    </a>
                    <a href="{{ route('module.schulkantine.servings.noshows', ['date' => $date->toDateString()]) }}"
                       class="inline-flex items-center gap-1 text-sm font-medium text-indigo-600 hover:text-indigo-800">
                        <x-module-icon name="search" class="text-base" /> No-Shows
                    </a>
                </div>
            </div>

            @if (! $open)
                <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-6 text-center text-sm text-amber-700">
                    🔒 Die Kantine hat an diesem Tag nicht geöffnet ({{ $closedReason }}).
                </div>
            @else
                {{-- Ausgabe per NFC-Chip: Chip vorhalten → Modal mit Bestellung → abhaken.
                     Ohne NFC-Gerät stehen Simulations-Buttons bereit. --}}
                @if ($canServe)
                    <div class="mb-4 rounded-xl border border-indigo-200 bg-indigo-50/40 p-4"
                         @kantine-open-eater.window="openForEater($event.detail)"
                         x-data="{
                            date: @js($date->toDateString()),
                            simChips: @js($simChips),
                            lookupUrl: @js(route('module.schulkantine.servings.lookup')),
                            toggleUrl: @js(route('module.schulkantine.servings.toggle')),
                            confirmUrl: @js(route('module.schulkantine.servings.confirm')),
                            openEaterUrl: @js(route('module.schulkantine.servings.lookup-eater')),
                            csrf: @js(csrf_token()),
                            reasons: ['mag es nicht', 'kein Hunger', 'schon satt', 'verträgt es nicht', 'Sonstiges'],
                            euro(v) { return (Number(v) || 0).toFixed(2).replace('.', ',') + ' €'; },
                            supported: typeof NDEFReader !== 'undefined',
                            scanning: false,
                            ctrl: null,
                            banner: null,
                            modal: null,
                            busy: false,
                            async start() {
                                if (!this.supported) return;
                                try {
                                    this.ctrl = new AbortController();
                                    const reader = new NDEFReader();
                                    await reader.scan({ signal: this.ctrl.signal });
                                    this.scanning = true;
                                    this.banner = { ok:true, text:'Bereit – Chip an das Gerät halten.' };
                                    reader.onreading = (e) => this.openFor(e.serialNumber);
                                    reader.onreadingerror = () => { this.banner = { ok:false, text:'Chip konnte nicht gelesen werden – nochmal.' }; };
                                } catch (err) { this.scanning=false; this.banner = { ok:false, text:'Scan nicht möglich: ' + (err && err.message ? err.message : err) }; }
                            },
                            stop() { if (this.ctrl) this.ctrl.abort(); this.scanning=false; this.banner=null; },
                            simulateRandom() { if (!this.simChips.length) return; const c = this.simChips[Math.floor(Math.random()*this.simChips.length)]; this.openFor(c.uid); },
                            async openForEater(eaterId) {
                                if (!eaterId) return;
                                try {
                                    const res = await fetch(this.openEaterUrl, { method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':this.csrf}, body: JSON.stringify({ eater_id: eaterId, date: this.date }) });
                                    const m = await res.json();
                                    if (m.found && Array.isArray(m.dishes)) { m.dishes.forEach(d => { d.outcome = d.handled ? (d.declined ? 'declined' : (d.alternative ? 'alternative' : 'taken')) : 'taken'; d.reason = d.reason || ''; }); }
                                    this.modal = m;
                                } catch (e) { this.banner = { ok:false, text:'Fehler beim Öffnen.' }; }
                            },
                            async openFor(uid) {
                                if (!uid) { this.banner = { ok:false, text:'Chip ohne Kennung.' }; return; }
                                try {
                                    const res = await fetch(this.lookupUrl, { method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':this.csrf}, body: JSON.stringify({ uid, date: this.date }) });
                                    const m = await res.json();
                                    if (m.found && Array.isArray(m.dishes)) {
                                        m.dishes.forEach(d => { d.taken = d.handled ? !d.declined : true; d.reason = d.reason || ''; });
                                    }
                                    this.modal = m;
                                    if (navigator.vibrate) navigator.vibrate(m.found ? 80 : [60,40,60]);
                                } catch (e) { this.banner = { ok:false, text:'Fehler bei der Chip-Abfrage.' }; }
                            },
                            close() { this.modal = null; },
                            async confirmServe() {
                                if (!this.modal || !this.modal.found || !this.modal.hasOrder) return;
                                this.busy = true;
                                const name = this.modal.name;
                                const items = this.modal.dishes.map(d => ({ order_id: d.order_id, outcome: d.outcome, reason: d.outcome === 'declined' ? (d.reason || 'Sonstiges') : null }));
                                const parts = [];
                                const nd = items.filter(i => i.outcome === 'declined').length;
                                const na = items.filter(i => i.outcome === 'alternative').length;
                                if (nd) parts.push(nd + '× nicht genommen');
                                if (na) parts.push(na + '× Alternative');
                                try {
                                    await fetch(this.confirmUrl, { method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':this.csrf}, body: JSON.stringify({ eater_id: this.modal.user_id, date: this.date, items }) });
                                    await this.refresh();
                                    this.banner = { ok:true, text: name + ' ✓ erfasst' + (parts.length ? ' (' + parts.join(', ') + ')' : '') + '.' };
                                } catch (e) { this.banner = { ok:false, text:'Fehler beim Erfassen.' }; }
                                this.busy = false;
                                this.close();
                            },
                            async takeBack() {
                                if (!this.modal) return;
                                this.busy = true;
                                const name = this.modal.name;
                                try {
                                    await fetch(this.toggleUrl, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded','X-CSRF-TOKEN':this.csrf}, body: new URLSearchParams({ eater_id: this.modal.user_id, date: this.date }) });
                                    await this.refresh();
                                    this.banner = { ok:false, text: name + ' – Ausgabe zurückgenommen.' };
                                } catch (e) { this.banner = { ok:false, text:'Fehler beim Zurücknehmen.' }; }
                                this.busy = false;
                                this.close();
                            },
                            async refresh() {
                                try {
                                    const html = await (await fetch(window.location.href, { headers:{'X-Requested-With':'fetch'}, credentials:'same-origin' })).text();
                                    const fresh = new DOMParser().parseFromString(html, 'text/html').querySelector('#serving-list');
                                    const cur = document.querySelector('#serving-list');
                                    if (fresh && cur) cur.innerHTML = fresh.innerHTML;
                                } catch (e) {}
                            }
                         }">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-center gap-2 text-sm">
                                <span class="text-lg">📟</span>
                                <span class="font-semibold text-gray-800">Ausgabe per NFC-Chip</span>
                            </div>
                            <div class="flex items-center gap-2">
                                {{-- Echtes NFC (Gerät mit Web-NFC) --}}
                                <template x-if="supported">
                                    <div class="flex items-center gap-2">
                                        <button type="button" x-show="!scanning" @click="start()"
                                                class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                                            NFC-Scan starten
                                        </button>
                                        <span x-show="scanning" x-cloak class="inline-flex items-center gap-1.5 text-sm font-medium text-green-700">
                                            <span class="relative flex h-2.5 w-2.5">
                                                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-400 opacity-75"></span>
                                                <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-green-500"></span>
                                            </span>
                                            Scan aktiv
                                        </span>
                                        <button type="button" x-show="scanning" x-cloak @click="stop()"
                                                class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">
                                            Stopp
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <template x-if="banner">
                            <div class="mt-3 rounded-lg px-3 py-2 text-sm font-medium"
                                 :class="banner.ok ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800'"
                                 x-text="banner.text"></div>
                        </template>

                        {{-- Kein NFC-Gerät → Simulation --}}
                        <template x-if="!supported">
                            <div class="mt-2">
                                <p class="text-xs text-gray-500">
                                    Dieses Gerät hat kein NFC. <strong>Simulation</strong> – tippe einen Chip an, als würde ein Schüler ihn vorhalten:
                                </p>
                                <div class="mt-2 flex flex-wrap gap-2" x-show="simChips.length">
                                    <button type="button" @click="simulateRandom()"
                                            class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">
                                        🎲 Zufälliger Chip
                                    </button>
                                    <template x-for="c in simChips" :key="c.uid">
                                        <button type="button" @click="openFor(c.uid)"
                                                class="inline-flex items-center gap-1.5 rounded-lg border border-indigo-300 bg-white px-3 py-1.5 text-sm font-medium text-indigo-700 hover:bg-indigo-50">
                                            <span x-text="c.name"></span>
                                            <span x-show="c.served" class="text-green-600">✓</span>
                                        </button>
                                    </template>
                                </div>
                                <p class="mt-2 text-xs text-gray-400" x-show="!simChips.length">
                                    Für diesen Tag/diese Gruppe sind keine Chips registriert – nichts zu simulieren.
                                </p>
                            </div>
                        </template>

                        {{-- Modal: Treffer nach Chip-Scan --}}
                        <div x-show="modal" x-cloak class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/40 p-4 sm:items-center"
                             @click.self="close()" @keydown.escape.window="close()">
                            <div class="my-auto w-full max-w-2xl max-h-[92vh] overflow-y-auto rounded-2xl bg-white p-6 shadow-xl" x-show="modal">
                                {{-- Unbekannter Chip --}}
                                <template x-if="modal && !modal.found">
                                    <div class="text-center">
                                        <div class="text-4xl">❓</div>
                                        <h3 class="mt-2 text-lg font-semibold text-gray-800">Chip nicht zugeordnet</h3>
                                        <p class="mt-1 text-sm text-gray-500">Dieser Chip gehört zu keinem Esser. Bitte zuerst unter „Teilnehmer" registrieren.</p>
                                        <button type="button" @click="close()" class="mt-4 rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">Schließen</button>
                                    </div>
                                </template>

                                {{-- Treffer --}}
                                <template x-if="modal && modal.found">
                                    <div>
                                        {{-- Kopf: Abholer + Sonderkost des Abholers --}}
                                        <div class="flex items-start justify-between gap-2">
                                            <div>
                                                <h3 class="text-xl font-bold text-gray-800" x-text="modal.name"></h3>
                                                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500" x-text="modal.group"></span>
                                            </div>
                                            <button type="button" @click="close()" class="text-2xl leading-none text-gray-400 hover:text-gray-600">&times;</button>
                                        </div>

                                        <div class="mt-3">
                                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-400">Verträglichkeiten des Abholers</div>
                                            <div class="mt-1 flex flex-wrap gap-1">
                                                <template x-for="a in modal.allergens" :key="'ea'+a">
                                                    <span class="rounded-full bg-rose-100 px-2 py-0.5 text-xs font-medium text-rose-700" x-text="'Allergie: ' + a"></span>
                                                </template>
                                                <template x-for="di in modal.diets" :key="'ed'+di">
                                                    <span class="rounded-full bg-violet-100 px-2 py-0.5 text-xs font-medium text-violet-700" x-text="'Diät: ' + di"></span>
                                                </template>
                                                <span x-show="!modal.allergens.length && !modal.diets.length" class="text-xs text-gray-400">keine Verträglichkeiten hinterlegt</span>
                                            </div>
                                        </div>

                                        <div x-show="modal.warn" x-cloak class="mt-3 rounded-lg bg-red-50 px-3 py-2 text-sm font-semibold text-red-700">⚠️ Achtung: Ein Gericht passt nicht zu den Verträglichkeiten des Abholers – bitte prüfen!</div>

                                        {{-- Bestellung: je Gericht Kennzeichnung + genommen / nicht genommen --}}
                                        <div class="mt-4">
                                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-400">Bestellung</div>

                                            <template x-if="modal.dishes && modal.dishes.length">
                                                <div class="mt-2 space-y-2">
                                                    <template x-for="d in modal.dishes" :key="d.order_id">
                                                        <div class="rounded-xl border p-3"
                                                             :class="d.outcome === 'declined' ? 'border-amber-300 bg-amber-50' : (d.outcome === 'alternative' ? 'border-blue-300 bg-blue-50' : ((d.allergenHits.length || d.dietHits.length) ? 'border-red-300 bg-red-50/50' : 'border-gray-200'))">
                                                            <div class="flex flex-wrap items-start justify-between gap-2">
                                                                <div>
                                                                    <div class="font-semibold text-gray-800" x-text="d.name"></div>
                                                                    <div class="text-xs text-gray-400"><span x-text="d.category"></span> · <span x-text="euro(d.price)"></span></div>
                                                                </div>
                                                                <div class="flex shrink-0 flex-wrap gap-1">
                                                                    <button type="button" @click="d.outcome = 'taken'"
                                                                            class="rounded-md px-2.5 py-1 text-xs font-medium"
                                                                            :class="d.outcome === 'taken' ? 'bg-green-600 text-white' : 'bg-gray-100 text-gray-500 hover:bg-gray-200'">genommen</button>
                                                                    <button type="button" @click="d.outcome = 'alternative'"
                                                                            class="rounded-md px-2.5 py-1 text-xs font-medium"
                                                                            :class="d.outcome === 'alternative' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-500 hover:bg-gray-200'">Alternative</button>
                                                                    <button type="button" @click="d.outcome = 'declined'"
                                                                            class="rounded-md px-2.5 py-1 text-xs font-medium"
                                                                            :class="d.outcome === 'declined' ? 'bg-amber-500 text-white' : 'bg-gray-100 text-gray-500 hover:bg-gray-200'">nicht genommen</button>
                                                                </div>
                                                            </div>

                                                            {{-- Kennzeichnung des Gerichts --}}
                                                            <div class="mt-2 flex flex-wrap gap-1">
                                                                <template x-for="a in d.allergens" :key="'al'+a">
                                                                    <span class="rounded px-1.5 py-0.5 text-xs"
                                                                          :class="d.allergenHits.includes(a) ? 'bg-red-100 font-semibold text-red-800 ring-1 ring-red-400' : 'bg-rose-50 text-rose-700'"
                                                                          x-text="(d.allergenHits.includes(a) ? '⚠️ ' : '') + a"></span>
                                                                </template>
                                                                <template x-for="z in d.additives" :key="'zu'+z">
                                                                    <span class="rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600" x-text="z"></span>
                                                                </template>
                                                                <template x-for="u in d.unsuitable" :key="'un'+u">
                                                                    <span class="rounded px-1.5 py-0.5 text-xs"
                                                                          :class="d.dietHits.includes(u) ? 'bg-red-100 font-semibold text-red-800 ring-1 ring-red-400' : 'bg-violet-50 text-violet-700'"
                                                                          x-text="(d.dietHits.includes(u) ? '⚠️ ' : '') + 'nicht für ' + u"></span>
                                                                </template>
                                                                <span x-show="!d.allergens.length && !d.additives.length && !d.unsuitable.length" class="text-xs text-gray-300">keine Kennzeichnung</span>
                                                            </div>

                                                            {{-- Grund, wenn nicht genommen --}}
                                                            <div x-show="d.outcome === 'declined'" x-cloak class="mt-2 flex flex-wrap items-center gap-1">
                                                                <span class="text-xs font-medium text-amber-700">Grund:</span>
                                                                <template x-for="r in reasons" :key="r">
                                                                    <button type="button" @click="d.reason = r"
                                                                            class="rounded-full px-2 py-0.5 text-xs"
                                                                            :class="d.reason === r ? 'bg-amber-600 text-white' : 'border border-amber-300 text-amber-700 hover:bg-amber-50'"
                                                                            x-text="r"></button>
                                                                </template>
                                                            </div>

                                                            {{-- Hinweis bei Alternative --}}
                                                            <div x-show="d.outcome === 'alternative'" x-cloak class="mt-2 text-xs text-blue-700">
                                                                Wird wie bestellt berechnet · Abrechnungs-Tag: „alternatives Gericht bevorzugt".
                                                            </div>
                                                        </div>
                                                    </template>
                                                </div>
                                            </template>
                                            <template x-if="(!modal.dishes || !modal.dishes.length)">
                                                <p class="mt-1 text-sm text-gray-500"
                                                   x-text="modal.hasOrder ? 'OGS-Essen (ja/nein)' : 'Keine Bestellung für heute.'"></p>
                                            </template>
                                        </div>

                                        {{-- Aktion --}}
                                        <div class="mt-5 flex items-center justify-end gap-2">
                                            <button type="button" @click="close()" class="rounded-lg px-3 py-2 text-sm font-medium text-gray-500 hover:text-gray-700">Schließen</button>
                                            <template x-if="modal.served">
                                                <button type="button" @click="takeBack()" :disabled="busy"
                                                        class="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm font-medium text-amber-800 hover:bg-amber-100 disabled:opacity-50">zurücknehmen</button>
                                            </template>
                                            <template x-if="modal.hasOrder">
                                                <button type="button" @click="confirmServe()" :disabled="busy"
                                                        class="rounded-lg bg-green-600 px-5 py-2 text-sm font-semibold text-white hover:bg-green-700 disabled:opacity-50">Ausgabe bestätigen</button>
                                            </template>
                                            <template x-if="!modal.hasOrder">
                                                <span class="text-sm text-gray-400">Keine Bestellung</span>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                @endif

                <div id="serving-list">
                {{-- Ausgabeliste --}}
                @if (empty($rows))
                    <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-6 text-center text-sm text-gray-500">
                        @if ($group === 'ogs')
                            Für diesen Tag isst kein OGS-Kind (kein aktives Abo, keine Bestellung).
                        @else
                            Für diesen Tag liegen keine Menü-Bestellungen vor.
                        @endif
                    </div>
                @else
                    @php
                        $openRows = collect($rows)->reject(fn ($r) => $r['served'])->values();
                        $doneRows = collect($rows)->filter(fn ($r) => $r['served'])->values();
                    @endphp

                    {{-- Noch offen: die eigentliche Arbeitsliste, oben. Namensfilter, damit
                         das Personal in einer langen Liste schnell den Esser findet. --}}
                    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white"
                         x-data="{
                            q: '',
                            names: @js($openRows->map(fn ($r) => \Illuminate\Support\Str::lower($r['user']->name))->values()),
                            match(n) { const t = this.q.trim().toLowerCase(); return t === '' || n.includes(t); },
                            get hits() { return this.names.filter(n => this.match(n)).length; },
                         }">
                        <div class="flex items-center justify-between border-b border-gray-100 bg-gray-50 px-4 py-2">
                            <span class="text-sm font-semibold text-gray-700">Noch auszugeben</span>
                            <span class="rounded-full bg-gray-200 px-2.5 py-0.5 text-sm font-semibold text-gray-700">{{ $openRows->count() }}</span>
                        </div>
                        @if ($openRows->isEmpty())
                            <p class="px-4 py-6 text-center text-sm text-green-700">Alles ausgegeben – nichts mehr offen. 🎉</p>
                        @else
                            {{-- Namensfilter --}}
                            <div class="border-b border-gray-100 px-4 py-2">
                                <div class="relative">
                                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2 text-gray-400">
                                        <x-module-icon name="search" class="text-base" />
                                    </span>
                                    <input type="search" x-model="q" placeholder="Name filtern …"
                                           class="block w-full rounded-lg border-gray-300 pl-8 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <button type="button" x-show="q !== ''" x-cloak @click="q = ''"
                                            class="absolute inset-y-0 right-0 flex items-center pr-2 text-gray-400 hover:text-gray-600">✕</button>
                                </div>
                            </div>
                            <div class="divide-y divide-gray-100">
                                @foreach ($openRows as $row)
                                    @include('schulkantine::servings._row', ['row' => $row, 'search' => true])
                                @endforeach
                            </div>
                            {{-- kein Treffer --}}
                            <p x-show="q !== '' && hits === 0" x-cloak class="px-4 py-6 text-center text-sm text-gray-500">
                                Kein offener Esser passt zu „<span x-text="q"></span>".
                            </p>
                        @endif
                    </div>

                    {{-- Bereits ausgegeben: nach unten, eingeklappt --}}
                    @if ($doneRows->isNotEmpty())
                        <div x-data="{ open: false }" class="mt-4 overflow-hidden rounded-xl border border-green-200 bg-white">
                            <button type="button" @click="open = ! open"
                                    class="flex w-full items-center justify-between px-4 py-2.5 text-left hover:bg-green-50">
                                <span class="flex items-center gap-2 text-sm font-semibold text-green-800">
                                    ✓ Bereits ausgegeben
                                    <span class="rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-semibold text-green-800">{{ $doneRows->count() }}</span>
                                </span>
                                <span class="text-green-600" x-text="open ? '▲ einklappen' : '▼ anzeigen'"></span>
                            </button>
                            <div x-show="open" x-cloak class="divide-y divide-gray-100 border-t border-green-100">
                                @foreach ($doneRows as $row)
                                    @include('schulkantine::servings._row', ['row' => $row])
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endif
                </div>{{-- /#serving-list --}}

                {{-- Spontane Abholung --}}
                @if ($walkinDishes->isNotEmpty())
                    <div class="mt-6 rounded-xl border border-gray-200 bg-white p-4">
                        <h2 class="text-sm font-semibold text-gray-800">Spontane Abholung</h2>
                        <p class="mt-0.5 text-xs text-gray-500">
                            Kind ohne (rechtzeitige) Vorbestellung nimmt ein Gericht. Nur Kategorien mit erlaubter spontaner Abholung, ohne Mengen-Limit.
                        </p>

                        @if ($spontaneous->isNotEmpty())
                            <div class="mt-3 flex flex-wrap gap-2">
                                @foreach ($spontaneous as $s)
                                    <span class="inline-flex items-center gap-2 rounded-lg border border-indigo-200 bg-indigo-50 px-2.5 py-1 text-sm text-indigo-800">
                                        <span>{{ $s->user?->name ?? 'Unbekannt' }} · {{ $s->dish?->name ?? '—' }}</span>
                                        @if ($canServe)
                                            <form method="POST" action="{{ route('module.schulkantine.servings.destroy', $s) }}"
                                                  onsubmit="return confirm('Diese spontane Abholung entfernen?')">
                                                @csrf @method('DELETE')
                                                <button type="submit" title="Entfernen" class="text-indigo-400 hover:text-red-600">✕</button>
                                            </form>
                                        @endif
                                    </span>
                                @endforeach
                            </div>
                        @endif

                        @if ($canServe)
                            <form method="POST" action="{{ route('module.schulkantine.servings.spontaneous') }}"
                                  class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-end">
                                @csrf
                                <input type="hidden" name="date" value="{{ $date->toDateString() }}">
                                <div class="flex-1">
                                    <label class="block text-xs font-medium text-gray-500">Esser</label>
                                    <select name="eater_id" required
                                            class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="">— Person wählen —</option>
                                        @foreach ($walkinUsers as $u)
                                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="flex-1">
                                    <label class="block text-xs font-medium text-gray-500">Gericht</label>
                                    <select name="dish_id" required
                                            class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="">— Gericht wählen —</option>
                                        @foreach ($walkinDishes->groupBy(fn ($d) => $d->category?->name ?? 'Ohne Kategorie') as $catName => $catDishes)
                                            <optgroup label="{{ $catName }}">
                                                @foreach ($catDishes as $dish)
                                                    <option value="{{ $dish->id }}">{{ $dish->name }} ({{ number_format((float) $dish->price, 2, ',', '.') }} €)</option>
                                                @endforeach
                                            </optgroup>
                                        @endforeach
                                    </select>
                                </div>
                                <button type="submit"
                                        class="inline-flex items-center justify-center gap-1 rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                                    <x-module-icon name="plus" class="text-base" /> Erfassen
                                </button>
                            </form>
                        @endif
                    </div>
                @endif
            @endif
        @endif
    </div>
</x-app-layout>
