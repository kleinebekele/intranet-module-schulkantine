<!DOCTYPE html>
<html lang="de" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Bestell-Terminal · Schulkantine</title>
    {{-- Favicon aus den Core-Einstellungen (eigenes Layout, deshalb von Hand). --}}
    @includeIf('layouts.favicon')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        [x-cloak] { display: none !important; }
        html, body { overscroll-behavior: none; }
        body { -webkit-tap-highlight-color: transparent; }
    </style>
</head>
<body class="min-h-full bg-gray-100 font-sans antialiased text-gray-800">

@php
    $money = fn ($v) => number_format((float) $v, 2, ',', '.').' €';
@endphp

<div x-data="bestellTerminal" class="flex min-h-screen flex-col">

    {{-- Kopfzeile --}}
    <header class="flex items-center justify-between gap-4 border-b border-gray-200 bg-white px-4 py-3 sm:px-6">
        <div class="flex items-center gap-3">
            <x-module-icon name="restaurant" class="text-3xl text-indigo-600" />
            <div>
                <div class="text-lg font-semibold text-gray-800">Essen bestellen</div>
                @if ($besteller)
                    <div class="text-sm text-gray-500">Angemeldet: <span class="font-semibold text-gray-800">{{ $besteller->name }}</span></div>
                @else
                    <div class="text-sm text-gray-500">Bestell-Terminal der Schulkantine</div>
                @endif
            </div>
        </div>

        @if ($besteller)
            <div class="flex items-center gap-3">
                @if ($season)
                    <div class="hidden rounded-lg border border-indigo-100 bg-indigo-50 px-4 py-1.5 text-right sm:block">
                        <div class="text-[11px] uppercase tracking-wide text-indigo-400">Kosten im {{ $monthStart->isoFormat('MMMM YYYY') }}</div>
                        <div class="text-lg font-bold text-indigo-700" id="month-total">{{ $money($monthTotal) }}</div>
                    </div>
                @endif
                <form method="POST" action="{{ route('kantine.bestellterminal.abmelden') }}" x-ref="abmelden">
                    @csrf
                    <button type="submit" class="rounded-xl bg-indigo-600 px-6 py-3 text-lg font-semibold text-white shadow hover:bg-indigo-700">
                        <svg class="-ml-1 mr-1 inline h-6 w-6 align-[-5px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75"/></svg>Abmelden
                        <span x-show="rest <= warnAb" x-cloak class="ml-1 text-sm font-normal text-indigo-200" x-text="'(' + rest + ' s)'"></span>
                    </button>
                </form>
            </div>
        @else
            <div x-show="chipUi" x-cloak class="flex items-center gap-3 rounded-xl bg-indigo-600 px-5 py-3 text-white shadow" :class="busy ? 'animate-pulse' : ''">
                <svg class="h-8 w-8 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8.288 15.038a5.25 5.25 0 017.424 0M5.106 11.856c3.807-3.808 9.98-3.808 13.788 0M1.924 8.674c5.565-5.565 14.587-5.565 20.152 0M12.53 18.22l-.53.53-.53-.53a.75.75 0 011.06 0z"/></svg>
                <div>
                    <div class="text-lg font-semibold leading-tight">Chip auflegen</div>
                    <div class="text-xs text-indigo-200">zum Bestellen</div>
                </div>
            </div>
        @endif
    </header>

    @if ($besteller)
        {{-- Untätigkeit: kurz vor der Abmeldung deutlich warnen. --}}
        <div x-show="rest <= warnAb" x-cloak class="bg-amber-100 px-4 py-2 text-center text-sm font-medium text-amber-800">
            Keine Eingabe – du wirst in <span x-text="rest"></span> Sekunden abgemeldet. Tippe irgendwo, um weiterzumachen.
        </div>

        <main class="flex-1 p-3 sm:p-6">
            @include('schulkantine::orders._woche', [
                'routen' => [
                    'woche' => 'kantine.bestellterminal.index',
                    'bestellen' => 'kantine.bestellterminal.bestellen',
                    'abo' => 'kantine.bestellterminal.abo',
                ],
                'ichId' => $besteller->id,
                'terminal' => true,
                'statusZeigen' => true,
            ])
        </main>
    @else
        {{-- Ohne Anmeldung: Speiseplan der Woche zum Ansehen; bestellen erst nach Chip. --}}
        <div x-show="meldung" x-cloak class="bg-red-100 px-4 py-3 text-center text-lg font-medium text-red-700" x-text="meldung"></div>

        <main class="flex-1 p-3 sm:p-6">
            @include('schulkantine::orders._woche', [
                'routen' => [
                    'woche' => 'kantine.bestellterminal.index',
                    'bestellen' => 'kantine.bestellterminal.bestellen',
                    'abo' => 'kantine.bestellterminal.abo',
                ],
                'ichId' => 0,
                'terminal' => true,
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
        </main>
    @endif

    {{-- Alter COM-Leser (Web Serial): einmalig je Gerät freigeben, danach verbindet er sich selbst. --}}
    <footer class="px-4 pb-3 text-right" x-show="chipUi && hasSerial() && ! serialOk" x-cloak>
        <button type="button" @click="serialConnect(true)" class="text-xs text-gray-400 underline hover:text-gray-600">COM-Leser verbinden (alter Leser)</button>
    </footer>
</div>

@include('schulkantine::partials.chip-bedienung')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('bestellTerminal', () => ({
            chipUi: false,   // „Chip auflegen" + COM-Knopf: nur mit ?chip=an in der Adresse
            angemeldet: @js((bool) $besteller),
            urlAnmelden: @js(route('kantine.bestellterminal.anmelden')),
            csrf: document.querySelector('meta[name=csrf-token]').content,
            busy: false,
            meldung: '',
            // Abmeldung nach Untätigkeit (Sekunden); die letzten warnAb Sekunden mit Hinweis.
            leerlauf: 30,
            warnAb: 10,
            rest: 30,
            // Ohne Chip: nach so vielen Sekunden ohne Eingabe die Seite neu laden.
            neuLadenNach: 300,
            wedgeBuf: '',
            wedgeAt: 0,
            serialOk: false,
            serialBuf: '',

            init() {
                this.chipUi = window.kantineChipBedienung();
                // USB-Leser in Tastatur-Emulation: immer global mithören.
                window.addEventListener('keydown', (e) => this.onWedgeKey(e));
                // Bereits freigegebener COM-Leser: ohne Klick wieder verbinden.
                if (this.hasSerial()) this.serialConnect(false);

                ['pointerdown', 'keydown', 'scroll', 'wheel'].forEach(ev =>
                    window.addEventListener(ev, () => { this.rest = this.leerlauf; }, { passive: true }));
                if (this.angemeldet) {
                    setInterval(() => {
                        this.rest--;
                        if (this.rest <= 0) this.$refs.abmelden.submit();
                    }, 1000);
                } else {
                    // Ohne Chip: nach Untätigkeit neu laden (Startwoche). Aus einer anderen Woche
                    // nach leerlauf Sekunden, sonst alle 5 Minuten – so kommen neuer Speiseplan und
                    // neue Versionen nach einem Deploy ohne F5 an.
                    const andereWoche = new URLSearchParams(location.search).has('week');
                    let ruhe = 0;
                    ['pointerdown', 'keydown', 'scroll', 'wheel'].forEach(ev =>
                        window.addEventListener(ev, () => { ruhe = 0; }, { passive: true }));
                    setInterval(() => {
                        ruhe++;
                        if (! this.busy && ((andereWoche && ruhe >= this.leerlauf) || ruhe >= this.neuLadenNach)) {
                            location.href = location.pathname;
                        }
                    }, 1000);
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
                    if (data.found) { location.href = location.pathname; return; }
                    // Unbekannter Chip: wer gerade angemeldet war, ist jetzt abgemeldet.
                    if (this.angemeldet) { location.href = location.pathname; return; }
                    this.meldung = 'Dieser Chip ist nicht bekannt. Bitte im Sekretariat melden.';
                } catch (e) {
                    this.meldung = 'Anmeldung nicht möglich – bitte noch einmal versuchen.';
                }
                this.busy = false;
            },

            // USB-Kartenleser „tippt" die Kennung sehr schnell und schließt mit Enter/Tab ab.
            onWedgeKey(e) {
                const t = e.target;
                if (t && (t.isContentEditable || ((t.tagName === 'INPUT' || t.tagName === 'TEXTAREA') && !t.readOnly))) return;
                if (e.ctrlKey || e.altKey || e.metaKey) return;

                const now = Date.now();
                if (e.key === 'Enter' || e.key === 'Tab') {
                    const uid = this.wedgeBuf;
                    this.wedgeBuf = '';
                    if (uid.length >= 4) { e.preventDefault(); this.anmelden(uid); }
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
                try {
                    let port = null;
                    if (ask) port = await navigator.serial.requestPort();
                    else port = (await navigator.serial.getPorts())[0] || null;
                    if (!port) return;
                    await port.open({ baudRate: 9600 });
                    this.serialOk = true;
                    this.serialRead(port);
                } catch (err) {
                    this.serialOk = false;
                    if (ask) this.meldung = 'COM-Leser: ' + (err && err.message ? err.message : err);
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
                        const uid = this.serialUid(this.serialBuf);
                        this.serialBuf = '';
                        if (uid) this.anmelden(uid);
                        continue;
                    }
                    this.serialBuf += ch;
                }
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
