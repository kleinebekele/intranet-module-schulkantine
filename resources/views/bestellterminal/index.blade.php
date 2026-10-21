<!DOCTYPE html>
<html lang="de" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Bestell-Terminal · Mensa</title>
    {{-- Favicon aus den Core-Einstellungen (eigenes Layout, deshalb von Hand). --}}
    @includeIf('layouts.favicon')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        [x-cloak] { display: none !important; }
        html, body { overscroll-behavior: none; }
        body { -webkit-tap-highlight-color: transparent; }
        /* Schwebende Meldungen (Bestellt/Abbestellt …) blenden sich selbst aus. */
        @keyframes kantine-toast-weg { 0%, 80% { opacity: 1; } 100% { opacity: 0; visibility: hidden; } }
        /* Rückfrage-Dialog des Intranets in Terminal-Farben und für Finger: groß, Abbestellen rot. */
        [role="dialog"] h3 { font-size: 1.5rem; }
        [role="dialog"] p { font-size: 1.125rem; }
        [role="dialog"] button { font-size: 1.125rem; padding: 0.75rem 1.5rem; border-radius: 0.75rem; }
        [role="dialog"] button[x-ref="ok"] { background-color: #dc2626 !important; font-weight: 700; }
        [role="dialog"] button[x-ref="ok"]:hover { background-color: #b91c1c !important; }
        [role="dialog"] button:not([x-ref="ok"]) { background-color: #f3f4f6; color: #374151; }
    </style>
</head>
<body class="min-h-full select-none bg-gray-100 font-sans antialiased text-gray-800" oncontextmenu="return false">
    {{-- Rechter Rand in Prozent für Terminals mit Sichtblende (z. B. ?rand=20). Gemerkt für dieses
         Browserfenster wie ?chip=an; ?rand=0 hebt ihn wieder auf. --}}
    <script>
        (function () {
            const p = new URLSearchParams(location.search);
            let rand = p.get('rand');
            if (rand !== null) {
                try { sessionStorage.setItem('kantine.rand', rand); } catch (e) {}
                p.delete('rand');
                history.replaceState(null, '', location.pathname + (p.toString() ? '?' + p : '') + location.hash);
            } else {
                try { rand = sessionStorage.getItem('kantine.rand'); } catch (e) {}
            }
            const n = Math.min(60, Math.max(0, parseFloat(rand) || 0));
            if (n > 0) {
                document.body.style.paddingRight = n + '%';
                // Dialoge (fixed inset-0) ebenfalls vor der Blende enden lassen.
                const st = document.createElement('style');
                st.textContent = '.fixed.inset-0, .fixed.inset-x-0 { right: ' + n + '% !important; }';
                document.head.appendChild(st);
            }
        })();
    </script>
    {{-- Schwache Kiosk-Rechner (XP + Supermium) zeichnen die Seite beim ersten Start oft nicht –
         erst ein Neuladen (F5) hilft. Darum einmal je Browserfenster 8 s nach dem ersten Öffnen neu laden. --}}
    <script>
        (function () {
            try {
                if (sessionStorage.getItem('kantine.erstladen')) return;
                sessionStorage.setItem('kantine.erstladen', '1');
                setTimeout(function () { location.reload(); }, 8000);
            } catch (e) {}
        })();
    </script>
    {{-- Mauszeiger ausblenden für Touch-Terminals (?zeiger=aus, zurück mit ?zeiger=an). Gemerkt wie ?rand. --}}
    <script>
        (function () {
            const p = new URLSearchParams(location.search);
            let zeiger = p.get('zeiger');
            if (zeiger !== null) {
                try { sessionStorage.setItem('kantine.zeiger', zeiger); } catch (e) {}
                p.delete('zeiger');
                history.replaceState(null, '', location.pathname + (p.toString() ? '?' + p : '') + location.hash);
            } else {
                try { zeiger = sessionStorage.getItem('kantine.zeiger'); } catch (e) {}
            }
            if (zeiger === 'aus') {
                const st = document.createElement('style');
                st.textContent = '*, *::before, *::after { cursor: none !important; }';
                document.head.appendChild(st);
            }
        })();
    </script>

@php
    $money = fn ($v) => number_format((float) $v, 2, ',', '.').' €';
@endphp

<div x-data="bestellTerminal" class="flex min-h-screen flex-col">
    {{-- Diagnose (F10): Stand des Chip-Lesers und was zuletzt ankam. Schließt nach 60 s oder
         sobald ein echter Chip gelesen wird. --}}
    <div x-show="diag" x-cloak class="fixed inset-0 z-[90] flex items-center justify-center bg-black/40 p-6">
        <div class="w-full max-w-xl rounded-2xl bg-white p-6 text-base shadow-2xl">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-xl font-bold text-gray-800">Diagnose Chip-Leser</h2>
                <span class="text-sm text-gray-400" x-text="'schließt in ' + diagRest + ' s'"></span>
            </div>
            <dl class="grid grid-cols-[11rem_1fr] gap-x-3 gap-y-2">
                <dt class="text-gray-500">Browser (Web Serial)</dt>
                <dd class="font-semibold" x-text="hasSerial() ? 'kann COM' : 'kann kein COM'"></dd>
                <dt class="text-gray-500">COM-Leser</dt>
                <dd class="font-semibold" :class="serialOk ? 'text-green-700' : 'text-red-700'" x-text="serialOk ? 'verbunden' : 'nicht verbunden'"></dd>
                <dt class="text-gray-500">Letzter Fehler</dt>
                <dd class="break-all text-sm" x-text="serialFehler || '–'"></dd>
                <dt class="text-gray-500">Zuletzt empfangen</dt>
                <dd class="text-sm">
                    <template x-if="diagLetzte">
                        <div>
                            <div><span x-text="diagLetzte.zeit"></span> · über <span class="font-semibold" x-text="diagLetzte.quelle"></span></div>
                            <div class="break-all font-mono" x-text="'roh: ' + diagLetzte.roh"></div>
                            <div class="font-mono font-semibold" x-text="'Chip: ' + (diagLetzte.uid || '(nicht erkannt)')"></div>
                        </div>
                    </template>
                    <span x-show="! diagLetzte">noch nichts</span>
                </dd>
            </dl>
            <form class="mt-5 flex gap-2 border-t border-gray-100 pt-4" @submit.prevent="diagTestanmeldung()">
                <input type="text" x-model="diagUid" placeholder="Chipnummer für Testanmeldung"
                       class="min-w-0 flex-1 rounded-lg border-gray-300 font-mono text-sm">
                <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Testanmeldung</button>
                <button type="button" @click="diagSchliessen()" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Schließen</button>
            </form>
            <p class="mt-2 text-xs text-gray-400">Die Testanmeldung prüft den Weg zur Anmeldung, nicht den Leser. Den Leser prüft nur ein echter Chip.</p>
        </div>
    </div>
    {{-- Abmeldung: sofort im Browser abdecken, nicht erst auf die (langsame) Antwort des Servers warten. --}}
    <div :style="abgemeldet ? '' : 'display:none'" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center bg-gray-100">
        <div class="rounded-2xl border-4 border-amber-500 bg-amber-300 px-8 py-4 text-3xl font-extrabold text-gray-900 shadow-lg">Abgemeldet – bitte Chip auflegen</div>
    </div>

    {{-- Schwebende Hinweise: liegen über der Seite und schieben nichts weg (langsame Rechner
         zeichnen sonst die ganze Seite neu). Ein-/Ausblenden per :style statt x-show – x-show
         blendet erst beim nächsten Bildaufbau ein und kann auf langsamen Geräten ein späteres
         Ausblenden überholen. --}}
    <div :style="angemeldet && rest <= warnAb ? '' : 'display:none'" x-cloak
         class="fixed inset-x-0 bottom-0 z-50 bg-amber-300 px-4 py-3 text-center text-lg font-semibold text-amber-900 shadow-lg">
        Keine Eingabe – du wirst in <span x-text="rest"></span> Sekunden abgemeldet. Tippe irgendwo, um weiterzumachen.
    </div>
    <div :style="meldung ? '' : 'display:none'" x-cloak
         class="fixed inset-x-0 bottom-0 z-50 bg-red-600 px-4 py-3 text-center text-lg font-semibold text-white shadow-lg" x-text="meldung"></div>

    {{-- Kopfzeile: feste Höhe; ausgetauscht werden nur Name und Kosten-Kachel. --}}
    <header class="relative flex items-center justify-between gap-4 border-b border-gray-200 bg-white px-4 py-3 sm:px-6" style="height: 88px">
        <div class="flex items-center gap-3">
            <x-module-icon name="restaurant" class="text-3xl text-indigo-600" />
            <div>
                <div class="text-lg font-semibold text-gray-800">Essen bestellen</div>
                <div class="text-sm text-gray-500" id="kopf-name">
                    @if ($besteller)
                        Angemeldet: <span class="font-semibold text-gray-800">{{ $besteller->name }}</span>
                    @else
                        Bestell-Terminal der Mensa
                    @endif
                </div>
            </div>
        </div>

        {{-- Mitte der Kopfzeile, unabhängig von links/rechts genau zentriert: „Bitte Chip auflegen"
             bzw. „Abmelden". Steht oben statt unter dem Speiseplan, damit auf kleinen Monitoren
             nichts scrollt. Wird beim An-/Abmelden mit ausgetauscht. --}}
        <div id="kopf-mitte" class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2">
            @if ($besteller)
                <button type="button" @click="abmelden()"
                        class="flex items-center gap-2 whitespace-nowrap rounded-2xl border-4 border-red-600 bg-red-500 px-5 py-1.5 text-xl font-extrabold text-white shadow-lg hover:bg-red-600">
                    <svg class="h-8 w-8 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75"/></svg>
                    Abmelden
                    <span :style="rest <= warnAb ? '' : 'display:none'" class="text-lg font-semibold text-red-100" x-text="'(' + rest + ' s)'"></span>
                </button>
            @else
                <div class="flex items-center gap-2 whitespace-nowrap rounded-2xl border-4 border-amber-500 bg-amber-300 px-5 py-1.5 text-xl font-extrabold text-gray-900 shadow-lg">
                    <svg class="h-8 w-8 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.288 15.038a5.25 5.25 0 017.424 0M5.106 11.856c3.807-3.808 9.98-3.808 13.788 0M1.924 8.674c5.565-5.565 14.587-5.565 20.152 0M12.53 18.22l-.53.53-.53-.53a.75.75 0 011.06 0z"/></svg>
                    Bitte Chip auflegen
                </div>
            @endif
        </div>

        <div class="flex items-center gap-3">
            {{-- Nur bei einem Problem mit dem alten COM-Leser (mit ?chip=an) – verbunden = nichts. --}}
            <div x-show="chipUi && ! serialOk" x-cloak class="text-sm font-semibold">
                <button type="button" x-show="hasSerial()" @click="serialConnect(true)"
                        class="rounded-lg bg-red-600 px-3 py-2 text-white shadow hover:bg-red-700">⚠ Chip-Leser nicht verbunden – antippen</button>
                <span x-show="! hasSerial()" class="inline-block rounded-lg bg-red-100 px-3 py-2 text-red-700">Browser kann keinen COM-Leser ansprechen</span>
            </div>
            <div id="kopf-kosten">
                @if ($besteller && $season && $kostenZeigen)
                    <div class="hidden rounded-lg border border-indigo-100 bg-indigo-50 px-4 py-1.5 text-right sm:block">
                        <div class="text-[11px] uppercase tracking-wide text-indigo-400">Kosten im {{ $monthStart->isoFormat('MMMM YYYY') }}</div>
                        <div class="text-lg font-bold text-indigo-700" id="month-total">{{ $money($monthTotal) }}</div>
                    </div>
                @endif
            </div>
        </div>
    </header>

    {{-- Inhalt: wird beim An- und Abmelden per JS ausgetauscht (kein Neuladen der Seite). --}}
    <main class="flex-1 p-3 sm:p-6" id="terminal-inhalt" data-angemeldet="{{ $besteller ? '1' : '0' }}">
        @if ($besteller)
            @include('schulkantine::orders._woche', [
                'routen' => [
                    'woche' => 'kantine.bestellterminal.index',
                    'bestellen' => 'kantine.bestellterminal.bestellen',
                    'abo' => 'kantine.bestellterminal.abo',
                ],
                'ichId' => $besteller->id,
                'terminal' => true,
                'preiseAus' => ! $kostenZeigen,
                'statusZeigen' => true,
            ])
        @else
            {{-- Ohne Anmeldung: Speiseplan der Woche zum Ansehen; bestellen erst nach Chip. --}}
            @include('schulkantine::orders._woche', [
                'routen' => [
                    'woche' => 'kantine.bestellterminal.index',
                    'bestellen' => 'kantine.bestellterminal.bestellen',
                    'abo' => 'kantine.bestellterminal.abo',
                ],
                'ichId' => 0,
                'terminal' => true,
                'preiseAus' => ! $kostenZeigen,
                'gast' => true,
            ])

            @if ($simChips->isNotEmpty())
                {{-- Nur lokal (APP_ENV=local): Chip-Auswahl zum Testen ohne Leser. --}}
                <div class="mt-6 max-w-md rounded-xl border border-dashed border-gray-300 bg-white p-4">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-400">Test (nur lokal): Chip simulieren</div>
                    <select class="mt-2 w-full rounded-lg border-gray-300 text-sm" @change="if ($event.target.value) anmelden($event.target.value)">
                        <option value="">– Chip wählen –</option>
                        @foreach ($simChips as $c)
                            <option value="{{ $c['uid'] }}">{{ $c['name'] }} ({{ $c['uid'] }})</option>
                        @endforeach
                    </select>
                </div>
            @endif
        @endif
    </main>
</div>

{{-- Rückfragen (data-bestaetigen, z. B. vor dem Abbestellen) – der Dialog des Cores. --}}
@includeIf('layouts.dialog')
@include('schulkantine::partials.chip-bedienung')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('bestellTerminal', () => ({
            chipUi: false,   // „Chip auflegen" + COM-Knopf: nur mit ?chip=an in der Adresse
            angemeldet: @js((bool) $besteller),
            urlAnmelden: @js(route('kantine.bestellterminal.anmelden')),
            urlAbmelden: @js(route('kantine.bestellterminal.abmelden')),
            csrf: document.querySelector('meta[name=csrf-token]').content,
            busy: false,
            meldung: '',
            // Abmeldung nach Untätigkeit (Sekunden); die letzten warnAb Sekunden mit Hinweis.
            leerlauf: 30,
            warnAb: 10,
            rest: 30,
            abgemeldet: false,   // deckt die Seite ab, sobald abgemeldet wird
            // Ohne Chip: nach so vielen Sekunden ohne Eingabe die Seite neu laden.
            neuLadenNach: 300,
            wedgeBuf: '',
            wedgeAt: 0,
            serialOk: false,
            serialVerbindet: false,
            serialFehler: '',
            // Diagnose (F10)
            diag: false,
            diagRest: 60,
            diagUhr: null,
            diagLetzte: null,
            diagUid: '',
            serialBuf: '',

            init() {
                this.chipUi = window.kantineChipBedienung();
                // USB-Leser in Tastatur-Emulation: immer global mithören.
                window.addEventListener('keydown', (e) => this.onWedgeKey(e));
                // Bereits freigegebener COM-Leser: ohne Klick wieder verbinden. Beim Neuladen hält die
                // alte Seite den Anschluss oft noch kurz fest – darum alle 3 s erneut versuchen.
                if (this.hasSerial()) {
                    this.serialConnect(false);
                    setInterval(() => { if (! this.serialOk) this.serialConnect(false); }, 3000);
                }

                // Ein Takt für beide Zustände – „angemeldet" wechselt ohne Neuladen.
                //  angemeldet: nach leerlauf Sekunden ohne Eingabe abmelden (genau einmal).
                //  ohne Chip:  nach Untätigkeit neu laden (Startwoche) – aus einer anderen Woche nach
                //              leerlauf Sekunden, sonst alle 5 Minuten (neuer Speiseplan, neue Version).
                let ruhe = 0;
                ['pointerdown', 'keydown', 'scroll', 'wheel'].forEach(ev =>
                    window.addEventListener(ev, () => { this.rest = this.leerlauf; ruhe = 0; }, { passive: true }));
                setInterval(() => {
                    if (this.busy || this.abgemeldet) return;
                    if (this.angemeldet) {
                        this.rest = Math.max(0, this.rest - 1);
                        if (this.rest === 0) this.abmelden();
                        return;
                    }
                    ruhe++;
                    const andereWoche = new URLSearchParams(location.search).has('week');
                    if ((andereWoche && ruhe >= this.leerlauf) || ruhe >= this.neuLadenNach) location.href = location.pathname;
                }, 1000);
            },

            // Seite für den neuen Zustand holen und nur Name, Kosten-Kachel und Inhalt austauschen.
            async seiteLaden(html) {
                try {
                    if (html === undefined) {
                        const res = await fetch(location.pathname, { credentials: 'same-origin', headers: { 'Accept': 'text/html' } });
                        html = await res.text();
                    }
                    const doc = new DOMParser().parseFromString(html, 'text/html');
                    const inhalt = doc.getElementById('terminal-inhalt');
                    if (! inhalt) throw new Error('kein Inhalt');
                    ['kopf-name', 'kopf-mitte', 'kopf-kosten'].forEach(id => {
                        const neu = doc.getElementById(id), alt = document.getElementById(id);
                        if (neu && alt) alt.innerHTML = neu.innerHTML;
                    });
                    const main = document.getElementById('terminal-inhalt');
                    main.innerHTML = inhalt.innerHTML;
                    main.dataset.angemeldet = inhalt.dataset.angemeldet;
                    const token = doc.querySelector('meta[name=csrf-token]');
                    if (token) {
                        this.csrf = token.content;
                        document.querySelector('meta[name=csrf-token]').content = token.content;
                    }
                    this.angemeldet = inhalt.dataset.angemeldet === '1';
                    this.rest = this.leerlauf;
                    if (location.search) history.replaceState(null, '', location.pathname);
                    window.scrollTo(0, 0);
                } catch (e) {
                    location.href = location.pathname; // Notfall: klassisch neu laden
                    return;
                }
                this.abgemeldet = false;
                this.busy = false;
            },

            // Abmelden ohne Seitenwechsel: sofort abdecken, dann im Hintergrund abmelden und
            // die Gast-Ansicht einsetzen (die Antwort auf die Abmeldung IST schon diese Seite).
            async abmelden() {
                if (this.abgemeldet) return;
                this.abgemeldet = true;
                this.busy = true;
                try {
                    const res = await fetch(this.urlAbmelden, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'text/html' },
                        credentials: 'same-origin',
                    });
                    if (res.status === 419) { location.href = location.pathname; return; }
                    await this.seiteLaden(await res.text());
                } catch (e) {
                    location.href = location.pathname;
                }
            },

            async anmelden(uid) {
                if (!uid || this.busy) return;
                this.busy = true;
                this.meldung = '';
                try {
                    const res = await fetch(this.urlAnmelden, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf },
                        credentials: 'same-origin',
                        body: JSON.stringify({ uid }),
                    });
                    if (res.status === 419) { location.reload(); return; }
                    const data = await res.json();
                    if (data.found) { this.meldung = ''; await this.seiteLaden(); return; }
                    // Unbekannter Chip: wer gerade angemeldet war, ist jetzt abgemeldet.
                    if (this.angemeldet) { await this.seiteLaden(); return; }
                    this.meldung = 'Dieser Chip ist nicht bekannt. Bitte im Sekretariat melden.';
                    setTimeout(() => { this.meldung = ''; }, 5000);
                } catch (e) {
                    this.meldung = 'Anmeldung nicht möglich – bitte noch einmal versuchen.';
                }
                this.busy = false;
            },

            // USB-Kartenleser „tippt" die Kennung sehr schnell und schließt mit Enter/Tab ab.
            onWedgeKey(e) {
                if (e.key === 'F10') { e.preventDefault(); this.diag ? this.diagSchliessen() : this.diagOeffnen(); return; }
                const t = e.target;
                if (t && (t.isContentEditable || ((t.tagName === 'INPUT' || t.tagName === 'TEXTAREA') && !t.readOnly))) return;
                if (e.ctrlKey || e.altKey || e.metaKey) return;

                const now = Date.now();
                if (e.key === 'Enter' || e.key === 'Tab') {
                    const uid = this.wedgeBuf;
                    this.wedgeBuf = '';
                    if (uid.length >= 4) { e.preventDefault(); this.chipGelesen(uid, 'Tastatur', uid); }
                    return;
                }
                if (e.key.length !== 1) return;
                if (now - this.wedgeAt > 300) this.wedgeBuf = '';
                this.wedgeAt = now;
                this.wedgeBuf += e.key;
            },

            // ---- Alter serieller Leser (Web Serial), wie im Ausgabe-Terminal ----
            hasSerial() { return 'serial' in navigator; },
            async serialConnect(ask) {
                if (this.serialVerbindet) return;
                this.serialVerbindet = true;
                try {
                    let port = null;
                    if (ask) port = await navigator.serial.requestPort();
                    else port = (await navigator.serial.getPorts())[0] || null;
                    if (!port) return;
                    // Schon offen (in dieser Seite)? Dann nur wieder mitlesen statt erneut öffnen.
                    if (!port.readable) await port.open({ baudRate: 9600 });
                    this.serialOk = true;
                    this.serialRead(port);
                } catch (err) {
                    this.serialOk = false;
                    this.serialFehler = new Date().toLocaleTimeString('de-DE') + ': ' + (err && err.message ? err.message : err);
                    if (ask) this.meldung = 'COM-Leser: ' + (err && err.message ? err.message : err);
                } finally {
                    this.serialVerbindet = false;
                }
            },
            async serialRead(port) {
                const dec = new TextDecoder();
                try {
                    while (port.readable) {
                        const reader = port.readable.getReader();
                        try {
                            while (true) {
                                const { value, done } = await reader.read();
                                if (done) break;
                                this.serialChunk(dec.decode(value));
                            }
                        } finally { reader.releaseLock(); }
                    }
                } catch (e) { /* Leser abgezogen */ }
                this.serialOk = false;
            },
            serialChunk(text) {
                for (const ch of text) {
                    const c = ch.charCodeAt(0);
                    if (c === 2) { this.serialBuf = ''; continue; }
                    if (c === 3 || c === 13 || c === 10) {
                        const roh = this.serialBuf;
                        const uid = this.serialUid(roh);
                        this.serialBuf = '';
                        if (roh.trim() !== '') this.chipGelesen(uid, 'COM', roh);
                        continue;
                    }
                    this.serialBuf += ch;
                }
            },
            // Ein echter Chip kam an (COM-Leser oder Tastatur-Leser): für die Diagnose merken,
            // Diagnose schließen und anmelden.
            chipGelesen(uid, quelle, roh) {
                this.diagLetzte = { zeit: new Date().toLocaleTimeString('de-DE'), quelle, roh: JSON.stringify(roh), uid };
                if (this.diag) this.diagSchliessen();
                if (uid) this.anmelden(uid);
            },
            diagOeffnen() {
                this.diag = true;
                this.diagRest = 60;
                clearInterval(this.diagUhr);
                this.diagUhr = setInterval(() => { if (--this.diagRest <= 0) this.diagSchliessen(); }, 1000);
            },
            diagSchliessen() {
                this.diag = false;
                clearInterval(this.diagUhr);
            },
            diagTestanmeldung() {
                const uid = this.diagUid.trim();
                if (uid.length < 4) return;
                this.diagSchliessen();
                this.anmelden(uid);
            },

            // Alter Leser: führende „0" + 10 Hex Kennung + Prüfzeichen.
            serialUid(raw) {
                const s = raw.trim();
                if (/^0[0-9A-Fa-f]{10}.$/.test(s)) return s.slice(1, 11);
                if (/^[0-9A-Fa-f]{10}.$/.test(s)) return s.slice(0, 10);
                return s.length >= 4 ? s : '';
            },
        }));
    });
</script>
</body>
</html>
