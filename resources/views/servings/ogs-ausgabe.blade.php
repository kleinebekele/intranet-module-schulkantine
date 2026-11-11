<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2">
            <x-module-icon name="restaurant" class="text-2xl text-indigo-600" />
            <h1 class="text-xl font-semibold text-gray-800">Ausgabe OGS</h1>
        </div>
    </x-slot>

    @if (! $season)
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Es ist keine Saison als „aktiv" markiert.
        </div>
    @else
        {{-- Tages-Navigation --}}
        <div class="mb-4 flex items-center justify-between gap-4">
            <div class="w-40">
                @if ($prevDate)
                    <a href="{{ route('module.schulkantine.ogs-ausgabe.index', ['date' => $prevDate]) }}"
                       class="inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">‹ Vorheriger Tag</a>
                @endif
            </div>
            <div class="text-center">
                <div class="text-sm font-semibold text-gray-800">{{ $date->isoFormat('dddd') }}, {{ $date->format('d.m.Y') }}</div>
                @if (! $date->isToday())
                    <a href="{{ route('module.schulkantine.ogs-ausgabe.index') }}" class="text-xs font-medium text-indigo-600 hover:text-indigo-800">zu heute</a>
                @else
                    <div class="text-xs text-gray-400">heute</div>
                @endif
            </div>
            <div class="flex w-40 justify-end">
                @if ($nextDate)
                    <a href="{{ route('module.schulkantine.ogs-ausgabe.index', ['date' => $nextDate]) }}"
                       class="inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Nächster Tag ›</a>
                @endif
            </div>
        </div>

        @if (! $open)
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-6 text-center">
                <div class="text-lg font-semibold text-amber-700">🔒 Geschlossen</div>
                <p class="mt-1 text-sm text-amber-800">{{ $closedReason }}</p>
            </div>
        @else
            <div x-data="ogsAusgabe()" x-init="init()" @keydown.window="globaleTaste($event)">

                @if ($istZukunft)
                    <div class="mb-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-800">
                        Vorschau: Abgehakt wird erst am Ausgabetag.
                    </div>
                @endif

                {{-- Meldung der letzten Buchung --}}
                <div x-show="banner" x-cloak class="mb-3 flex items-center justify-between gap-3 rounded-lg px-4 py-2 text-sm font-medium"
                     :class="banner && banner.ok ? 'border border-green-200 bg-green-50 text-green-800' : 'border border-red-200 bg-red-50 text-red-800'">
                    <span x-text="banner && banner.text"></span>
                    <button type="button" @click="banner = null" class="text-lg leading-none opacity-60 hover:opacity-100">✕</button>
                </div>

                {{-- Suche + Zähler --}}
                <div class="mb-3 flex flex-wrap items-start justify-between gap-3">
                    <div class="relative w-full max-w-md">
                        <input type="search" x-ref="suche" x-model="q" @input="eingabe()"
                               @keydown.arrow-down.prevent="markieren(1)" @keydown.arrow-up.prevent="markieren(-1)"
                               @keydown.enter.prevent="waehlen(treffer[aktiv])" @keydown.escape="sucheLeeren()"
                               autocomplete="off" placeholder="OGS-Kind suchen … (Taste /)"
                               class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <div x-show="q.trim().length >= 2 && (treffer.length || !laedt)" x-cloak
                             class="absolute z-20 mt-1 w-full overflow-hidden rounded-lg border border-gray-200 bg-white shadow-lg">
                            <template x-for="(r, i) in treffer" :key="r.id">
                                <button type="button" @click="waehlen(r)" @mouseenter="aktiv = i"
                                        class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm"
                                        :class="i === aktiv ? 'bg-indigo-50' : ''">
                                    <span>
                                        <span class="font-medium text-gray-800" x-text="r.name"></span>
                                        <span class="ml-1 text-xs text-gray-500" x-text="r.label"></span>
                                    </span>
                                    <span class="shrink-0 text-xs font-semibold" :class="trefferStatus(r).klasse" x-text="trefferStatus(r).text"></span>
                                </button>
                            </template>
                            <div x-show="!treffer.length && !laedt" class="px-3 py-2 text-sm text-gray-500">Kein OGS-Kind gefunden.</div>
                            <div x-show="gesamt > treffer.length" class="border-t border-gray-100 px-3 py-1.5 text-xs text-gray-400">
                                + <span x-text="gesamt - treffer.length"></span> weitere – genauer tippen
                            </div>
                        </div>
                        <p class="mt-1 text-xs text-gray-400">Enter bucht den markierten Treffer als abgeholt – auch Kinder, die heute nicht angemeldet sind.</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 text-sm font-semibold">
                        <span class="rounded-full bg-green-100 px-3 py-1 text-green-700"><span x-text="servedCount"></span> abgeholt</span>
                        <span x-show="declinedCount" x-cloak class="rounded-full bg-red-100 px-3 py-1 text-red-700"><span x-text="declinedCount"></span> abgelehnt</span>
                        <span class="rounded-full bg-gray-100 px-3 py-1 text-gray-600"><span x-text="offene.length"></span> offen</span>
                        <span class="text-gray-400">von <span x-text="gefiltert.length"></span></span>
                    </div>
                </div>

                {{-- Klassenfilter (Zahl = noch offen) --}}
                <div x-show="eaters.length" x-cloak class="mb-3 flex flex-wrap items-center gap-2">
                    <button type="button" @click="klasseSetzen('')"
                            class="rounded-lg px-4 py-1.5 text-sm font-semibold shadow-sm"
                            :class="klasse === '' ? 'bg-indigo-600 text-white' : 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50'">Alle</button>
                    <template x-for="k in ['1', '2', '3', '4']" :key="k">
                        <button type="button" @click="klasseSetzen(k)"
                                class="inline-flex items-center gap-2 rounded-lg px-4 py-1.5 text-sm font-semibold shadow-sm"
                                :class="klasse === k ? 'bg-indigo-600 text-white' : 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50'">
                            <span x-text="k + '. Klasse'"></span>
                            <span class="rounded-full px-1.5 text-xs"
                                  :class="klasse === k ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-500'" x-text="offenIn(k)"></span>
                        </button>
                    </template>
                </div>

                {{-- Leerfall --}}
                <div x-show="!eaters.length" x-cloak class="rounded-xl border border-gray-200 bg-white px-4 py-10 text-center text-gray-400">
                    An diesem Tag isst kein OGS-Kind.
                </div>

                {{-- Kinderliste: offene zuerst, abgeholte grün ans Ende. Klick = abgeholt ⇄ offen,
                     ⋯ bzw. Rechtsklick = Details (Unverträglichkeiten, abgelehnt). --}}
                <div x-show="eaters.length" x-cloak class="columns-2 gap-2 md:columns-3 xl:columns-4">
                    <template x-for="e in sortiert" :key="e.user_id">
                        <div class="mb-2 flex break-inside-avoid items-stretch overflow-hidden rounded-lg border shadow-sm"
                             :class="!e.served ? 'border-gray-200 bg-white' : (e.declined ? 'border-red-300 bg-red-50' : 'border-green-300 bg-green-50')">
                            <button type="button" :disabled="busy" @click="umschalten(e.user_id)" @contextmenu.prevent="details(e.user_id)"
                                    :title="!e.served ? 'Als abgeholt buchen' : 'Zurücknehmen'"
                                    class="flex min-w-0 flex-1 items-center gap-3 px-3 py-2 text-left hover:bg-black/5 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-indigo-500 disabled:opacity-60">
                                <span class="shrink-0 text-xl"
                                      :class="!e.served ? 'text-gray-300' : (e.declined ? 'text-red-600' : 'text-green-600')"
                                      x-text="!e.served ? '○' : (e.declined ? '✗' : '✓')"></span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-semibold"
                                          :class="!e.served ? 'text-gray-800' : (e.declined ? 'text-red-900' : 'text-green-900')" x-text="e.sortName || e.name"></span>
                                    <span class="block truncate text-xs text-gray-500">
                                        <span x-show="e.klasse" x-text="'Klasse ' + e.klasse"></span>
                                        <span x-show="e.abo" class="ml-1 rounded px-1.5 py-px font-semibold"
                                              :class="e.abo && e.abo.spontan ? 'bg-orange-100 text-orange-800' : 'bg-indigo-50 text-indigo-700'"
                                              x-text="e.abo && e.abo.text"></span>
                                        <span x-show="sonderkost(e).length" class="font-semibold text-amber-700">⚠ <span x-text="sonderkost(e).join(', ')"></span></span>
                                    </span>
                                </span>
                            </button>
                            <button type="button" @click="details(e.user_id)" title="Details"
                                    class="shrink-0 border-l border-black/5 px-3 text-gray-400 hover:bg-black/5 hover:text-gray-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-indigo-500">⋯</button>
                        </div>
                    </template>
                </div>

                {{-- Detail-Dialog: Unverträglichkeiten + abgeholt / abgelehnt / zurücknehmen --}}
                <div x-show="modal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
                     @click.self="modal = null" @keydown.escape.window="modal = null">
                    <template x-if="modal">
                        <div class="w-full max-w-md rounded-xl bg-white p-5 shadow-2xl">
                            <div class="mb-3 flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <h3 class="truncate text-lg font-bold text-gray-800" x-text="modal.name"></h3>
                                    <div class="text-sm font-medium"
                                         :class="!modal.served ? 'text-gray-400' : (modal.declined ? 'text-red-600' : 'text-green-600')"
                                         x-text="!modal.served ? 'Status: offen' : (modal.declined ? 'Status: abgelehnt' : 'Status: abgeholt')"></div>
                                    <div x-show="modal.abo" class="text-sm text-gray-500" x-text="modal.abo && modal.abo.text"></div>
                                </div>
                                <button type="button" @click="modal = null" class="rounded-lg px-2 py-1 text-gray-400 hover:bg-gray-100">✕</button>
                            </div>
                            <div class="rounded-lg bg-gray-50 p-3">
                                <div class="text-xs font-bold uppercase tracking-wide text-gray-400">Unverträglichkeiten</div>
                                <div class="mt-1.5 flex flex-wrap gap-1.5">
                                    <template x-for="a in (modal.allergens || [])" :key="'a'+a">
                                        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800" x-text="a"></span>
                                    </template>
                                    <template x-for="d in (modal.diets || [])" :key="'d'+d">
                                        <span class="rounded-full bg-purple-100 px-2 py-0.5 text-xs font-semibold text-purple-800" x-text="d"></span>
                                    </template>
                                    <span x-show="!sonderkost(modal).length" class="text-sm text-gray-400">keine hinterlegt</span>
                                </div>
                            </div>
                            <div class="mt-4 grid grid-cols-2 gap-2">
                                <button type="button" @click="setzen('served')" :disabled="busy"
                                        class="rounded-lg bg-green-600 py-2.5 text-sm font-semibold text-white hover:bg-green-700 disabled:opacity-60">✓ Abgeholt</button>
                                <button type="button" @click="setzen('declined')" :disabled="busy"
                                        class="rounded-lg bg-red-600 py-2.5 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-60">✗ Abgelehnt</button>
                            </div>
                            <button type="button" x-show="modal.served" @click="setzen('open')" :disabled="busy"
                                    class="mt-2 w-full rounded-lg border border-gray-300 bg-white py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 disabled:opacity-60">↺ Zurücknehmen (offen)</button>
                        </div>
                    </template>
                </div>
            </div>

            <script>
                function ogsAusgabe() {
                    return {
                        eaters: @js($ogsEaters),
                        date: @js($date->toDateString()),
                        urls: {
                            toggle: @js(route('module.schulkantine.ogs-ausgabe.toggle')),
                            suche: @js(route('module.schulkantine.ogs-ausgabe.search')),
                        },
                        csrf: @js(csrf_token()),
                        busy: false,
                        banner: null,
                        modal: null,
                        q: '', treffer: [], gesamt: 0, aktiv: 0, laedt: false, suchTimer: null,
                        klasse: (() => { try { return localStorage.getItem('kantine_ogs_ausgabe_klasse') || '' } catch (e) { return '' } })(),

                        init() { this.$nextTick(() => this.$refs.suche?.focus()); },

                        // „/" springt in die Suche (wie in vielen Web-Anwendungen).
                        globaleTaste(ev) {
                            if (ev.key !== '/' || this.modal) return;
                            const t = ev.target.tagName;
                            if (t === 'INPUT' || t === 'TEXTAREA' || t === 'SELECT' || ev.target.isContentEditable) return;
                            ev.preventDefault();
                            this.$refs.suche.focus();
                        },

                        klasseSetzen(k) {
                            this.klasse = this.klasse === k ? '' : k;
                            try { localStorage.setItem('kantine_ogs_ausgabe_klasse', this.klasse); } catch (e) {}
                        },
                        klasseVon(e) { const m = String(e.klasse || '').match(/\d+/); return m ? String(parseInt(m[0], 10)) : ''; },
                        offenIn(k) { return this.eaters.filter(e => !e.served && this.klasseVon(e) === k).length; },
                        get gefiltert() { return this.klasse ? this.eaters.filter(e => this.klasseVon(e) === this.klasse) : this.eaters; },
                        get sortiert() {
                            const n = e => (e.sortName || e.name || '').toLocaleLowerCase('de');
                            const alpha = [...this.gefiltert].sort((a, b) => n(a).localeCompare(n(b), 'de'));
                            return [...alpha.filter(e => !e.served), ...alpha.filter(e => e.served)];
                        },
                        get offene() { return this.gefiltert.filter(e => !e.served); },
                        get servedCount() { return this.gefiltert.filter(e => e.served && !e.declined).length; },
                        get declinedCount() { return this.gefiltert.filter(e => e.served && e.declined).length; },
                        sonderkost(e) { return [...(e.allergens || []), ...(e.diets || [])]; },

                        // ---- Suche ----
                        eingabe() {
                            this.laedt = this.q.trim().length >= 2;
                            clearTimeout(this.suchTimer);
                            this.suchTimer = setTimeout(() => this.suchen(), 200);
                        },
                        async suchen() {
                            const q = this.q.trim();
                            if (q.length < 2) { this.treffer = []; this.gesamt = 0; this.laedt = false; return; }
                            this.laedt = true;
                            try {
                                const res = await fetch(this.urls.suche, {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf },
                                    body: JSON.stringify({ q, mode: 'ogs', date: this.date }),
                                });
                                const data = await res.json();
                                if (q !== this.q.trim()) return; // inzwischen weitergetippt
                                this.treffer = data.results || [];
                                this.gesamt = data.total || this.treffer.length;
                                this.aktiv = 0;
                            } catch (err) {
                                this.banner = { ok: false, text: 'Suche fehlgeschlagen: ' + err };
                            }
                            this.laedt = false;
                        },
                        markieren(d) {
                            if (!this.treffer.length) return;
                            this.aktiv = (this.aktiv + d + this.treffer.length) % this.treffer.length;
                        },
                        sucheLeeren() { this.q = ''; this.treffer = []; this.gesamt = 0; },
                        trefferStatus(r) {
                            const e = this.eaters.find(x => x.user_id === r.id);
                            if (!e) return { text: 'nicht angemeldet', klasse: 'text-gray-400' };
                            if (!e.served) return { text: 'offen', klasse: 'text-indigo-600' };
                            return e.declined ? { text: 'abgelehnt', klasse: 'text-red-600' } : { text: '✓ abgeholt', klasse: 'text-green-600' };
                        },
                        async waehlen(r) {
                            if (!r) return;
                            const e = this.eaters.find(x => x.user_id === r.id);
                            this.sucheLeeren();
                            this.$refs.suche.focus();
                            if (e && e.served) { this.banner = { ok: true, text: e.name + ' ist bereits ' + (e.declined ? 'als abgelehnt' : 'als abgeholt') + ' gebucht.' }; return; }
                            await this.buchen(r.id, 'served', r.name);
                        },

                        // ---- Buchen ----
                        umschalten(id) {
                            const e = this.eaters.find(x => x.user_id === id);
                            if (e) this.buchen(id, e.served ? 'open' : 'served', e.name);
                        },
                        details(id) { this.modal = this.eaters.find(x => x.user_id === id) || null; },
                        async setzen(outcome) {
                            const e = this.modal;
                            this.modal = null;
                            if (e) await this.buchen(e.user_id, outcome, e.name);
                        },
                        async buchen(id, outcome, name) {
                            if (this.busy) return;
                            this.busy = true;
                            try {
                                const res = await fetch(this.urls.toggle, {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf },
                                    body: JSON.stringify({ eater_id: id, date: this.date, outcome }),
                                });
                                const data = await res.json();
                                if (!data.ok) { this.banner = { ok: false, text: data.error || 'Buchung fehlgeschlagen.' }; return; }
                                const e = this.eaters.find(x => x.user_id === id);
                                if (data.removed) {
                                    this.eaters = this.eaters.filter(x => x.user_id !== id);
                                } else if (e) {
                                    e.served = data.served;
                                    e.declined = data.declined;
                                } else if (data.eater) {
                                    this.eaters.push(data.eater); // spontan dazugekommenes Kind
                                }
                                this.banner = { ok: true, text: name + ': ' + (!data.served ? 'zurückgenommen' : (data.declined ? 'abgelehnt' : 'abgeholt')) + '.' };
                            } catch (err) {
                                this.banner = { ok: false, text: 'Fehler beim Buchen: ' + err };
                            } finally {
                                this.busy = false;
                            }
                        },
                    };
                }
            </script>
        @endif
    @endif
</x-app-layout>
