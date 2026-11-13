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
        {{-- Kalenderleiste wie im Ausgabe-Terminal: Woche zurück · KW/Datum (Kalender) ·
             Öffnungstage der Woche mit Zahl essender OGS-Kinder · Woche vor. --}}
        <div class="mb-4 flex items-stretch overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            @if ($woche['prev'])
                <a href="{{ route('module.schulkantine.ogs-ausgabe.index', ['date' => $woche['prev']]) }}" title="Woche zurück"
                   class="flex w-9 shrink-0 items-center justify-center border-r border-gray-200 text-2xl font-bold text-gray-500 hover:bg-gray-100">‹</a>
            @else
                <span class="flex w-9 shrink-0 items-center justify-center border-r border-gray-200 text-2xl font-bold text-gray-200">‹</span>
            @endif

            <div class="flex w-44 shrink-0 flex-col items-center justify-center gap-0.5 border-r border-gray-200 px-2 py-1.5 {{ $date->isToday() ? 'bg-gray-50' : 'bg-amber-100' }}">
                <div class="text-lg font-bold leading-tight text-indigo-700">KW {{ $woche['kw'] }}</div>
                <label class="relative cursor-pointer text-sm font-semibold leading-tight {{ $date->isToday() ? 'text-gray-800' : 'text-amber-800' }} hover:underline" title="Tag im Kalender wählen">
                    📅 {{ $date->isoFormat('dd, D.M.YYYY') }}
                    <input type="date" value="{{ $date->toDateString() }}"
                           min="{{ $season->start_date->toDateString() }}" max="{{ $season->end_date->toDateString() }}"
                           onchange="if (this.value) location.href = @js(route('module.schulkantine.ogs-ausgabe.index')) + '?date=' + this.value"
                           onclick="try { this.showPicker(); } catch (e) {}"
                           class="absolute inset-0 h-full w-full cursor-pointer opacity-0">
                </label>
                @if ($date->isToday())
                    <div class="text-[10px] font-medium text-gray-400">heute</div>
                @else
                    <a href="{{ route('module.schulkantine.ogs-ausgabe.index') }}"
                       class="rounded bg-amber-500 px-2 py-px text-xs font-bold text-white hover:bg-amber-600">Heute</a>
                @endif
            </div>

            <div class="flex min-w-0 flex-1 divide-x divide-gray-100 overflow-x-auto">
                @forelse ($woche['tage'] as $t)
                    <a href="{{ route('module.schulkantine.ogs-ausgabe.index', ['date' => $t['date']]) }}"
                       class="flex min-w-[7rem] flex-1 flex-col justify-center px-3 py-1.5 hover:bg-indigo-50 {{ $t['aktiv'] ? 'ring-4 ring-inset ring-indigo-500' : '' }}">
                        <span class="text-sm font-semibold {{ $t['aktiv'] ? 'text-indigo-700' : 'text-gray-700' }}">
                            {{ $t['wochentag'] }} {{ $t['tag'] }}
                            @if ($t['heute'])<span class="ml-1 text-[10px] font-medium text-gray-400">heute</span>@endif
                        </span>
                        <span class="flex items-baseline gap-1 leading-tight">
                            <span class="text-2xl font-bold {{ $t['ogs'] ? 'text-indigo-700' : 'text-gray-300' }}">{{ $t['ogs'] }}</span>
                            <span class="text-[11px] font-medium text-gray-400">essen</span>
                        </span>
                    </a>
                @empty
                    <div class="flex flex-1 items-center px-4 text-sm text-gray-400">In dieser Woche hat die Mensa geschlossen.</div>
                @endforelse
            </div>

            @if ($woche['next'])
                <a href="{{ route('module.schulkantine.ogs-ausgabe.index', ['date' => $woche['next']]) }}" title="Woche vor"
                   class="flex w-9 shrink-0 items-center justify-center border-l border-gray-200 text-2xl font-bold text-gray-500 hover:bg-gray-100">›</a>
            @else
                <span class="flex w-9 shrink-0 items-center justify-center border-l border-gray-200 text-2xl font-bold text-gray-200">›</span>
            @endif
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

                {{-- Meldung der letzten Buchung: schwebt unten rechts, damit die Liste nicht
                     verrutscht. Erfolg verschwindet nach ein paar Sekunden, Fehler bleiben. --}}
                <div x-show="banner" x-cloak x-transition.opacity
                     class="fixed bottom-6 right-6 z-40 flex max-w-sm items-center justify-between gap-3 rounded-lg px-4 py-2 text-sm font-medium shadow-lg"
                     :class="banner && banner.hinweis ? 'border border-amber-300 bg-amber-100 text-amber-800' : (banner && banner.ok ? 'border border-green-200 bg-green-50 text-green-800' : 'border border-red-200 bg-red-50 text-red-800')">
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
                        <p class="mt-1 text-xs text-gray-400">Enter bucht den markierten Treffer als „da“ – auch Kinder, die heute nicht angemeldet sind.</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 text-sm font-semibold">
                        <span class="rounded-full bg-green-100 px-3 py-1 text-green-700"><span x-text="servedCount"></span> da</span>
                        <span x-show="declinedCount" x-cloak class="rounded-full bg-red-100 px-3 py-1 text-red-700"><span x-text="declinedCount"></span> nicht da</span>
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

                {{-- Kinderliste: offene zuerst, erledigte ans Ende. Je Kind zwei Knöpfe:
                     „Da" (abgeholt) und „Nicht da" (abgelehnt); erneuter Klick auf den
                     gewählten Knopf nimmt zurück. Der Abo-Text öffnet für Verwalter den Abo-Dialog. --}}
                <div x-show="eaters.length" x-cloak class="grid gap-2 md:grid-cols-2 xl:grid-cols-3">
                    <template x-for="e in sortiert" :key="e.user_id">
                        <div class="flex items-center gap-3 rounded-lg border px-3 py-2 shadow-sm"
                             :class="!e.served ? 'border-gray-200 bg-white' : (e.declined ? 'border-red-300 bg-red-50' : 'border-green-300 bg-green-50')">
                            <div class="min-w-0 flex-1">
                                <div class="truncate text-sm font-semibold"
                                     :class="!e.served ? 'text-gray-800' : (e.declined ? 'text-red-900' : 'text-green-900')" x-text="e.sortName || e.name"></div>
                                <div class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs text-gray-500">
                                    <span x-show="e.klasse" x-text="'Klasse ' + e.klasse"></span>
                                    <template x-if="e.abo && darfAbo">
                                        <button type="button" @click="aboOeffnen(e)" title="Abo ändern"
                                                class="rounded px-1.5 py-px font-semibold underline decoration-dotted underline-offset-2 hover:decoration-solid"
                                                :class="aboFarbe(e)" x-text="e.abo.text"></button>
                                    </template>
                                    <template x-if="e.abo && !darfAbo">
                                        <span class="rounded px-1.5 py-px font-semibold" :class="aboFarbe(e)" x-text="e.abo.text"></span>
                                    </template>
                                    <span x-show="sonderkost(e).length" class="font-semibold text-amber-700">⚠ <span x-text="sonderkost(e).join(', ')"></span></span>
                                </div>
                            </div>
                            <div class="flex shrink-0 gap-1.5">
                                <button type="button" :disabled="busy" @pointerenter="zeigerAuf($event)" @click="absichtlich($event) && buchen(e.user_id, e.served && !e.declined ? 'open' : 'served', e.name)"
                                        :title="e.served && !e.declined ? 'Zurücknehmen' : 'Da – Essen abgeholt'"
                                        class="w-20 rounded-md py-1.5 text-center text-sm font-semibold disabled:opacity-60"
                                        :class="e.served && !e.declined ? 'bg-green-600 text-white hover:bg-green-700' : 'border border-green-600 bg-white text-green-700 hover:bg-green-50'">Da</button>
                                <button type="button" :disabled="busy" @pointerenter="zeigerAuf($event)" @click="absichtlich($event) && buchen(e.user_id, e.served && e.declined ? 'open' : 'declined', e.name)"
                                        :title="e.served && e.declined ? 'Zurücknehmen' : 'Nicht da'"
                                        class="w-20 rounded-md py-1.5 text-center text-sm font-semibold disabled:opacity-60"
                                        :class="e.served && e.declined ? 'bg-red-600 text-white hover:bg-red-700' : 'border border-red-600 bg-white text-red-700 hover:bg-red-50'">Nicht da</button>
                            </div>
                        </div>
                    </template>
                </div>

                @if ($darfAbo)
                    {{-- Abo-Dialog (nur mit Verwaltungsrecht): Wochentage + an/aus. Normales
                         Formular – nach dem Speichern lädt die Seite mit der Meldung neu. --}}
                    <div x-show="abo" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
                         @click.self="abo = null" @keydown.escape.window="abo = null">
                        <template x-if="abo">
                            <form method="POST" action="{{ route('module.schulkantine.ogs-ausgabe.abo') }}" class="flex max-h-[90vh] w-full max-w-2xl flex-col overflow-y-auto rounded-xl bg-white p-5 shadow-2xl">
                                @csrf
                                <input type="hidden" name="eater_id" :value="abo.user_id">
                                <input type="hidden" name="active" :value="abo.active ? 1 : 0">
                                <div class="mb-3 flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <h3 class="truncate text-lg font-bold text-gray-800" x-text="'OGS-Abo: ' + abo.name"></h3>
                                        <div class="text-sm text-gray-500" x-text="'Bisher: ' + abo.text"></div>
                                    </div>
                                    <button type="button" @click="abo = null" class="rounded-lg px-2 py-1 text-gray-400 hover:bg-gray-100">✕</button>
                                </div>
                                <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                                    <input type="checkbox" x-model="abo.active" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    Abo aktiv
                                </label>
                                <div class="mt-3" :class="abo.active ? '' : 'opacity-40'">
                                    <div class="mb-1 text-xs font-bold uppercase tracking-wide text-gray-400">Isst an</div>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach ($wochentage as $nr => $kurz)
                                            <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-1.5 text-sm">
                                                <input type="checkbox" name="weekdays[]" value="{{ $nr }}" x-model.number="abo.weekdays" :disabled="!abo.active"
                                                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                                {{ $kurz }}
                                            </label>
                                        @endforeach
                                    </div>
                                    <p class="mt-1 text-xs text-gray-400">Alle angehakt = an allen Öffnungstagen. Die Änderung gilt ab dem nächsten noch bestellbaren Tag.</p>
                                </div>
                                {{-- Eltern + Verlauf: Was haben die Eltern wann eingestellt? --}}
                                <div class="mt-4 rounded-lg bg-gray-50 p-3 text-sm">
                                    <div class="mb-1 text-xs font-bold uppercase tracking-wide text-gray-400">Eltern im Intranet</div>
                                    <div x-show="aboInfo === null" class="text-gray-400">lädt …</div>
                                    <template x-if="aboInfo">
                                        <div>
                                            <div x-show="!aboInfo.eltern.length" class="font-semibold text-red-700">Kein Elternkonto hinterlegt.</div>
                                            <template x-for="p in aboInfo.eltern" :key="p.name">
                                                <div class="flex justify-between gap-3">
                                                    <span class="text-gray-800" x-text="p.name"></span>
                                                    <span class="shrink-0" :class="p.zuletzt ? 'text-gray-500' : 'font-semibold text-red-700'"
                                                          x-text="p.zuletzt ? 'zuletzt angemeldet ' + p.zuletzt : 'noch nie angemeldet'"></span>
                                                </div>
                                            </template>
                                        </div>
                                    </template>

                                    <div class="mb-1 mt-3 text-xs font-bold uppercase tracking-wide text-gray-400">Verlauf (Abo und OGS-Tage)</div>
                                    <template x-if="aboInfo">
                                        <div class="max-h-60 divide-y divide-gray-200 overflow-y-auto">
                                            <div x-show="!aboInfo.verlauf.length" class="text-gray-400">Noch nichts eingestellt.</div>
                                            <template x-for="(v, i) in aboInfo.verlauf" :key="i">
                                                <div class="py-1.5">
                                                    <div class="flex flex-wrap items-baseline justify-between gap-x-3 text-xs text-gray-500">
                                                        <span x-text="v.zeit"></span>
                                                        <span>
                                                            <span x-text="v.wer"></span>
                                                            <span x-show="v.eltern" class="ml-1 rounded bg-indigo-50 px-1 font-semibold text-indigo-700">Eltern</span>
                                                            <span x-show="v.quelle" x-text="'· ' + v.quelle"></span>
                                                        </span>
                                                    </div>
                                                    <div :class="v.abo ? 'font-semibold text-gray-800' : 'text-gray-700'" x-text="v.text"></div>
                                                </div>
                                            </template>
                                        </div>
                                    </template>
                                </div>

                                <div class="mt-4 flex justify-end gap-2">
                                    <button type="button" @click="abo = null" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Abbrechen</button>
                                    <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Abo speichern</button>
                                </div>
                            </form>
                        </template>
                    </div>
                @endif
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
                        abo: null,
                        aboInfo: null,
                        darfAbo: @js($darfAbo),
                        oeffnungstage: @js(array_keys($wochentage)),
                        q: '', treffer: [], gesamt: 0, aktiv: 0, laedt: false, suchTimer: null,
                        klasse: (() => { try { return localStorage.getItem('kantine_ogs_ausgabe_klasse') || '' } catch (e) { return '' } })(),

                        bannerTimer: null,
                        zeigerSeit: 0,
                        init() {
                            this.$nextTick(() => this.$refs.suche?.focus());
                            this.$watch('banner', (b) => {
                                clearTimeout(this.bannerTimer);
                                if (b && (b.ok || b.hinweis)) this.bannerTimer = setTimeout(() => { this.banner = null; }, 3000);
                            });
                        },

                        // „/" springt in die Suche (wie in vielen Web-Anwendungen).
                        globaleTaste(ev) {
                            if (ev.key !== '/' || this.abo) return;
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
                            if (e && e.served) { this.banner = { ok: true, text: e.name + ' ist bereits als ' + (e.declined ? '„nicht da“' : '„da“') + ' gebucht.' }; return; }
                            await this.buchen(r.id, 'served', r.name);
                        },

                        // ---- Abo (nur Verwalter) ----
                        aboFarbe(e) {
                            if (e.abo.spontan) return 'bg-orange-100 text-orange-800';
                            return e.abo.aktiv ? 'bg-indigo-50 text-indigo-700' : 'bg-gray-100 text-gray-600';
                        },
                        aboOeffnen(e) {
                            const tage = (e.abo.tage && e.abo.tage.length) ? e.abo.tage : this.oeffnungstage;
                            this.abo = { user_id: e.user_id, name: e.name, text: e.abo.text, active: e.abo.aktiv || !e.abo.hatAbo, weekdays: [...tage] };
                            this.aboInfo = null;
                            const id = e.user_id;
                            fetch(@js(route('module.schulkantine.ogs-ausgabe.abo-info')) + '?eater_id=' + id, { headers: { 'Accept': 'application/json' } })
                                .then(r => r.json())
                                .then(d => { if (this.abo && this.abo.user_id === id) this.aboInfo = d; })
                                .catch(() => { if (this.abo && this.abo.user_id === id) this.aboInfo = { eltern: [], verlauf: [], fehler: true }; });
                        },

                        // ---- Buchen ----
                        // Klickschutz gegen das empfindliche Touchpad: Ein Mausklick zählt erst,
                        // wenn der Zeiger kurz (250 ms) auf dem Knopf geruht hat. Ein Antippen im
                        // Vorbeiziehen wird ignoriert. Tastatur (Enter/Leertaste) und Touchscreen
                        // sind davon ausgenommen.
                        zeigerAuf(ev) {
                            this.zeigerSeit = ev.pointerType === 'mouse' ? Date.now() : 0;
                        },
                        absichtlich(ev) {
                            if (ev.detail === 0 || !this.zeigerSeit || Date.now() - this.zeigerSeit >= 250) return true;
                            this.banner = { hinweis: true, text: 'Klick nicht gezählt – der Mauszeiger war noch unterwegs. Zum Buchen kurz auf dem Knopf anhalten.' };
                            return false;
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
                                this.banner = { ok: true, text: name + ': ' + (!data.served ? 'zurückgenommen' : (data.declined ? 'nicht da' : 'da')) + '.' };
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
