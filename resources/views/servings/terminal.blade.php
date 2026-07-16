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
                ogsToggle: @js(route('module.schulkantine.servings.terminal.ogs-toggle')),
                overview: @js(route('module.schulkantine.servings.terminal.overview')),
                reportPerson: @js(url('modules/schulkantine/auswertung/person')),
                base: @js(route('module.schulkantine.servings.terminal')),
            },
            open: @js($open),
            planGroups: @js($planGroups),
            walkinGroups: @js($walkinGroups),
            ogsEaters: @js($ogsEaters),   // OGS-Ansicht: heute essende OGS-Kinder (+ served/declined)
            ogsBusy: false,
            ogsPressTimer: null,   // Long-Press-Erkennung (öffnet Detail-Modal)
            ogsLongFired: false,
            ogsModalOpen: false,   // OGS-Detail-Modal (Unverträglichkeiten + abgeholt/abgelehnt)
            ogsModalEater: null,
            simChips: @js($simChips),
            openDates: @js($openDates),   // alle Öffnungstage der Saison (Touch-Kalender)
            today: @js($today),
            ogsPrice: @js((float) ($season->ogs_price ?? 0)),

            // --- Zustand ---
            person: null,          // eingelesener Esser (null = Leerlauf)
            choice: {},            // category_id -> dish_id | 'declined'
            orderMeta: {},         // category_id -> { order_id, orderedDishId }
            walkinQty: {},         // dish_id -> Anzahl
            nachschlagTotal: 0,    // Nachschlag-Betrag (50-ct-Schritte oder manuell)
            padOpen: false,        // Zahlen-Tastatur (manueller Betrag)
            padValue: '',
            autoFinish: false,     // beim naechsten Chip automatisch buchen
            busy: false,
            banner: null,          // { ok, text }
            scanning: false,
            ctrl: null,
            servedOnLoad: false,   // war beim Stempeln schon etwas gebucht?
            mode: 'vorbesteller',  // 'vorbesteller' | 'ogs' | 'overview'
            hasOgs: @js($hasOgs ?? false),   // gibt/gab es OGS in der Saison? (steuert den Umschalter)
            // „Übersicht"-Ansicht (Männchen-Button): Wochen-Matrix aller Esser.
            ovLoaded: false, ovLoading: false, ovPeople: [], ovDays: [],
            ovSearchOpen: false, ovQuery: '',
            ovReturnMode: 'vorbesteller',  // Ansicht, aus der die Übersicht geöffnet wurde
            sbw: 0,   // Scrollleisten-Breite (für die bündige Übersicht-Matrix)
            rcw: 80,  // Breite der rechten Header-Steuerung (› + Übersicht-Button)
            modalOpen: false,      // Gericht-Detail-Modal
            modalDish: null,
            dateModalOpen: false,  // Touch-Kalender
            calYear: 0,
            calMonth: 0,
            searchOpen: false,
            searchQuery: '',
            searchResults: [],
            searching: false,
            searchDebounce: null,
            keyboardRows: [
                ['Q','W','E','R','T','Z','U','I','O','P','Ü'],
                ['A','S','D','F','G','H','J','K','L','Ö','Ä'],
                ['Y','X','C','V','B','N','M','ß'],
            ],

            // Mindestgröße fürs Terminal-Layout.
            tooSmall: false,
            vw: 0,
            vh: 0,

            init() {
                this.autoFinish = localStorage.getItem('kantineTerminalAutoFinish') === '1';
                this.sbw = this.measureScrollbar();
                this.checkSize();
                this.$nextTick(() => this.measureRight());
                window.addEventListener('resize', () => { this.checkSize(); this.measureRight(); });

                // Aktuelle Ansicht im URL-Anker halten, damit F5 sie wiederherstellt.
                this.$watch('mode', (m) => {
                    history.replaceState(null, '', location.pathname + location.search + '#' + this.hashForMode(m));
                });
                const h = (location.hash || '').replace('#', '');
                if (h === 'ogs' && this.hasOgs) this.mode = 'ogs';
                else if (h === 'uebersicht' || h === 'overview') { this.mode = 'overview'; this.loadOverview(); }
            },
            hashForMode(m) { return ({ vorbesteller: 'vorbesteller', ogs: 'ogs', overview: 'uebersicht' })[m] || 'vorbesteller'; },
            // Breite der vertikalen Scrollleiste – damit die Übersicht-Matrix (scrollt)
            // und der Header (scrollt nicht) rechtsbündig bleiben. 0 bei Overlay-Leisten.
            measureScrollbar() {
                const d = document.createElement('div');
                d.style.cssText = 'overflow:scroll;width:100px;height:100px;position:absolute;top:-9999px';
                document.body.appendChild(d);
                const w = d.offsetWidth - d.clientWidth;
                document.body.removeChild(d);
                return w;
            },
            // Breite der rechten Header-Steuerung (› + Übersicht-Button) – als rechter
            // Abstand der Übersicht-Matrix, damit die Tages-Spalten bündig darunter enden.
            measureRight() { if (this.$refs.ovRight) this.rcw = this.$refs.ovRight.offsetWidth; },
            checkSize() {
                this.vw = window.innerWidth;
                this.vh = window.innerHeight;
                this.tooSmall = this.vw < 1000 || this.vh < 700;
            },

            // ---- Preis-Helfer ----
            euro(v) { return (Math.round(v * 100) / 100).toFixed(2).replace('.', ',') + ' €'; },
            get walkinPrices() {
                const m = {};
                this.walkinGroups.forEach(g => g.dishes.forEach(d => { m[d.id] = d.price; }));
                return m;
            },
            get hasWalkin() { return Object.values(this.walkinQty).some(q => q > 0); },
            get extrasTotal() {
                let sum = this.nachschlagTotal;
                for (const [id, q] of Object.entries(this.walkinQty)) sum += (this.walkinPrices[id] || 0) * q;
                return sum;
            },
            get isOgs() { return this.person && this.person.mode === 'ja_nein'; },
            // Ist alles im „frischen Stempel"-Zustand? (Menü = bestellt/genommen,
            // keine Extras.) Dann bringt „Zurück" nichts und wird ausgeblendet.
            get isDefaultState() {
                if (this.hasWalkin || this.nachschlagTotal > 0) return false;
                for (const [catId, meta] of Object.entries(this.orderMeta)) {
                    if (this.choice[catId] !== meta.orderedDishId) return false;
                }
                return true;
            },
            get hasSomething() {
                if (this.hasWalkin || this.nachschlagTotal > 0) return true;
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
            // Entprellte Suche – auch für die Bildschirmtastatur, die searchQuery
            // programmatisch ändert (dabei feuert kein input-Event).
            queueSearch() {
                clearTimeout(this.searchDebounce);
                this.searchDebounce = setTimeout(() => this.doSearch(), 180);
            },
            keyPress(ch) { this.searchQuery += (ch === ' ' ? ' ' : ch.toLowerCase()); this.queueSearch(); },
            keyBackspace() { this.searchQuery = this.searchQuery.slice(0, -1); this.queueSearch(); },
            keyClear() { this.searchQuery = ''; this.searchResults = []; clearTimeout(this.searchDebounce); },
            async doSearch() {
                const q = this.searchQuery.trim();
                // Erst ab dem 3. Buchstaben suchen (spart Anfragen, gezieltere Treffer).
                if (q.length < 3) { this.searchResults = []; this.searching = false; return; }
                this.searching = true;
                try {
                    const res = await fetch(this.urls.search, {
                        method: 'POST',
                        headers: { 'Content-Type':'application/json', 'Accept':'application/json', 'X-CSRF-TOKEN': this.csrf },
                        // mode/date: im OGS-Modus die heute essenden OGS-Kinder finden.
                        body: JSON.stringify({ q, mode: this.mode, date: this.date }),
                    });
                    const data = await res.json();
                    this.searchResults = data.results || [];
                } catch (e) {
                    this.banner = { ok:false, text:'Suche fehlgeschlagen: ' + e };
                }
                this.searching = false;
            },
            async pickSearch(id) {
                // OGS-Modus: Treffer direkt als abgeholt buchen (kein Menü-/Chip-Fluss).
                // Auch ein heute nicht angemeldetes OGS-Kind kann so spontan mitessen –
                // der Server legt die Anwesenheit an und liefert den Board-Eintrag zurück.
                if (this.mode === 'ogs') {
                    this.closeSearch();
                    const existing = this.ogsEaters.find(x => x.user_id === id);
                    if (existing && existing.served) { this.banner = { ok:true, text: existing.name + ' ist bereits abgeholt.' }; return; }
                    if (this.ogsBusy) return;
                    this.ogsBusy = true;
                    try {
                        const res = await fetch(this.urls.ogsToggle, {
                            method: 'POST',
                            headers: { 'Content-Type':'application/json', 'Accept':'application/json', 'X-CSRF-TOKEN': this.csrf },
                            body: JSON.stringify({ eater_id: id, date: this.date }),
                        });
                        const data = await res.json();
                        if (!data.ok) { this.banner = { ok:false, text: data.error || 'Buchung fehlgeschlagen.' }; this.ogsBusy = false; return; }
                        if (existing) {
                            existing.served = data.served;
                        } else if (data.eater) {
                            this.ogsEaters.push(data.eater); // spontan dazugekommenes Kind aufs Board
                        }
                        this.banner = { ok:true, text: (existing?.name || data.eater?.name || 'Kind') + ' als abgeholt gebucht.' };
                    } catch (err) {
                        this.banner = { ok:false, text:'Fehler beim Buchen: ' + err };
                    }
                    this.ogsBusy = false;
                    return;
                }
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
                this.nachschlagTotal = 0;
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
                        this.nachschlagTotal = Math.round((this.nachschlagTotal + w.price) * 100) / 100;
                    } else if (w.dish_id != null) {
                        this.walkinQty[w.dish_id] = (this.walkinQty[w.dish_id] || 0) + 1;
                    }
                });
            },

            // ---- Verträglichkeiten / Detail-Modal ----
            // Kollidiert das Gericht mit der Sonderkost der gestempelten Person?
            // (Bei Sparmenüs sind die Allergene der Bestandteile bereits eingerechnet.)
            dishWarn(dish) {
                if (!this.person || !dish) return false;
                const pa = this.person.allergenIds || [];
                const pd = this.person.dietIds || [];
                return (dish.allergenIds || []).some(id => pa.includes(id))
                    || (dish.dietIds || []).some(id => pd.includes(id));
            },
            allergenHit(id) { return this.person && (this.person.allergenIds || []).includes(id); },
            dietHit(id) { return this.person && (this.person.dietIds || []).includes(id); },
            openDishModal(dish) { this.modalDish = dish; this.modalOpen = true; },
            closeDishModal() { this.modalOpen = false; },

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
            nachschlagPlus() { if (!this.rightEnabled()) return; this.nachschlagTotal = Math.round((this.nachschlagTotal + 0.5) * 100) / 100; },
            nachschlagMinus() { this.nachschlagTotal = Math.max(0, Math.round((this.nachschlagTotal - 0.5) * 100) / 100); },
            // ---- Zahlen-Tastatur (manueller Nachschlag-Betrag) ----
            openPad() { if (!this.rightEnabled()) return; this.padValue = ''; this.padOpen = true; },
            padKey(ch) {
                if (ch === ',') { if (!this.padValue.includes(',')) this.padValue += (this.padValue === '' ? '0,' : ','); return; }
                if (this.padValue.includes(',') && this.padValue.split(',')[1].length >= 2) return; // max 2 Nachkommastellen
                this.padValue = (this.padValue === '0') ? ch : this.padValue + ch;
            },
            padBackspace() { this.padValue = this.padValue.slice(0, -1); },
            padClear() { this.padValue = ''; },
            padConfirm() { this.nachschlagTotal = Math.round((parseFloat(this.padValue.replace(',', '.')) || 0) * 100) / 100; this.padOpen = false; },
            padCancel() { this.padOpen = false; },

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
                const nachschlag = this.nachschlagTotal > 0 ? [{ amount: this.nachschlagTotal, qty: 1 }] : [];
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

            // Tageswechsel: Modus-Anker mitnehmen, damit die Ansicht erhalten bleibt.
            gotoDate(d) { if (d) window.location = this.urls.base + '?date=' + d + '#' + this.hashForMode(this.mode); },

            // ---- KW-Navigation (Woche zurück / vor) ----
            mondayOf(d) { const x = new Date(d); const wd = (x.getDay() + 6) % 7; x.setDate(x.getDate() - wd); x.setHours(0, 0, 0, 0); return x; },
            ymd(d) { return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); },
            get hasPrevKw() {
                const mon = this.mondayOf(new Date(this.date + 'T00:00:00'));
                return this.openDates.some(ds => new Date(ds + 'T00:00:00') < mon);
            },
            get hasNextKw() {
                const sun = this.mondayOf(new Date(this.date + 'T00:00:00')); sun.setDate(sun.getDate() + 6);
                return this.openDates.some(ds => new Date(ds + 'T00:00:00') > sun);
            },
            // Springt zur vorigen/nächsten KW immer auf den Wochenstart (Montag bzw. den
            // ersten Öffnungstag der Woche, falls Montag geschlossen). Überspringt Wochen
            // ganz ohne Öffnungstage.
            kwStep(dir) {
                const curMon = this.mondayOf(new Date(this.date + 'T00:00:00'));
                const set = this.openSet;
                for (let i = 1; i <= 60; i++) {
                    const mon = new Date(curMon); mon.setDate(mon.getDate() + dir * 7 * i);
                    const week = [];
                    for (let k = 0; k < 7; k++) { const dd = new Date(mon); dd.setDate(dd.getDate() + k); week.push(this.ymd(dd)); }
                    const openInWeek = week.filter(ds => set.has(ds));
                    if (openInWeek.length) { this.gotoDate(openInWeek[0]); return; }
                }
            },

            // ---- OGS-Ansicht ----
            // Nach Vorname sortiert (Umlaut-tolerant); bei Gleichstand nach vollem Namen.
            get ogsSorted() {
                return [...this.ogsEaters].sort((a, b) =>
                    (a.first || '').localeCompare(b.first || '', 'de', { sensitivity: 'base' })
                    || (a.name || '').localeCompare(b.name || '', 'de'));
            },
            // Offene zuerst, erledigte (abgeholt=grün / abgelehnt=rot) ans Ende – beide
            // je nach Vorname sortiert. So sinken erledigte in den Spalten nach unten.
            get ogsActive() { return this.ogsSorted.filter(e => !e.served); },
            get ogsPicked() { return this.ogsSorted.filter(e => e.served); },
            get ogsOrdered() { return [...this.ogsActive, ...this.ogsPicked]; },
            get ogsServedCount() { return this.ogsEaters.filter(e => e.served && !e.declined).length; },
            get ogsDeclinedCount() { return this.ogsEaters.filter(e => e.served && e.declined).length; },
            ogsSonderkost(e) { return [...(e.allergens || []), ...(e.diets || [])]; },

            // Long-Press öffnet das Detail-Modal (Unverträglichkeiten + abgeholt/abgelehnt);
            // ein kurzer Tipp schaltet abgeholt ⇄ offen. Der Timer unterscheidet beides.
            ogsPressStart(id) {
                this.ogsLongFired = false;
                clearTimeout(this.ogsPressTimer);
                this.ogsPressTimer = setTimeout(() => { this.ogsLongFired = true; this.openOgsModal(id); }, 500);
            },
            ogsPressEnd() { clearTimeout(this.ogsPressTimer); },
            ogsTileClick(id) {
                if (this.ogsLongFired) { this.ogsLongFired = false; return; } // war ein Long-Press
                this.ogsToggle(id);
            },

            openOgsModal(id) {
                const e = this.ogsEaters.find(x => x.user_id === id);
                if (!e) return;
                this.ogsModalEater = e;
                this.ogsModalOpen = true;
            },
            closeOgsModal() { this.ogsModalOpen = false; },
            // Aus dem Modal: ausdrücklich abgeholt / abgelehnt / offen setzen.
            async ogsSet(outcome) {
                const e = this.ogsModalEater;
                this.closeOgsModal();
                if (e) await this.ogsToggle(e.user_id, outcome);
            },

            // outcome: null = Tap-Umschalter (abgeholt ⇄ offen); sonst served|declined|open.
            async ogsToggle(id, outcome = null) {
                if (this.ogsBusy) return;
                const e = this.ogsEaters.find(x => x.user_id === id);
                if (!e) return;
                this.ogsBusy = true;
                try {
                    const res = await fetch(this.urls.ogsToggle, {
                        method: 'POST',
                        headers: { 'Content-Type':'application/json', 'Accept':'application/json', 'X-CSRF-TOKEN': this.csrf },
                        body: JSON.stringify({ eater_id: id, date: this.date, outcome }),
                    });
                    const data = await res.json();
                    if (!data.ok) { this.banner = { ok:false, text: data.error || 'Buchung fehlgeschlagen.' }; this.ogsBusy = false; return; }
                    // Spontan Dazugekommener beim Zurücknehmen komplett vom Board nehmen.
                    if (data.removed) {
                        this.ogsEaters = this.ogsEaters.filter(x => x.user_id !== id);
                    } else {
                        e.served = data.served;
                        e.declined = data.declined;
                    }
                } catch (err) {
                    this.banner = { ok:false, text:'Fehler beim Buchen: ' + err };
                }
                this.ogsBusy = false;
            },

            // ---- Übersicht (Wochen-Matrix) ----
            // Merkt sich, aus welcher Ansicht die Übersicht geöffnet wurde (#vorbesteller/#ogs),
            // damit das rote X wieder genau dorthin zurückführt.
            openOverview() {
                if (this.mode !== 'overview') this.ovReturnMode = this.mode;
                this.mode = 'overview';
                this.loadOverview();
            },
            // Männchen-Button: rein in die Übersicht bzw. (rotes X) zurück zur Herkunfts-Ansicht.
            toggleOverview() { this.mode === 'overview' ? (this.mode = this.ovReturnMode) : this.openOverview(); },
            async loadOverview() {
                if (this.ovLoaded || this.ovLoading) return;
                this.ovLoading = true;
                try {
                    const res = await fetch(this.urls.overview + '?date=' + this.date, { headers: { 'Accept':'application/json' } });
                    const data = await res.json();
                    this.ovDays = data.days || [];
                    this.ovPeople = data.people || [];
                    this.ovLoaded = true;
                } catch (e) {
                    this.banner = { ok:false, text:'Übersicht konnte nicht geladen werden: ' + e };
                }
                this.ovLoading = false;
            },
            // Such-Overlay (kein Modal): tippen filtert die Liste ab dem 3. Buchstaben.
            toggleOvSearch() { this.ovSearchOpen = !this.ovSearchOpen; },
            ovKey(ch) { this.ovQuery += (ch === ' ' ? ' ' : ch.toLowerCase()); },
            ovBackspace() { this.ovQuery = this.ovQuery.slice(0, -1); },
            ovClear() { this.ovQuery = ''; },
            get ovFiltered() {
                const q = this.ovQuery.trim().toLowerCase();
                if (q.length < 3) return this.ovPeople;
                return this.ovPeople.filter(p => (p.name || '').toLowerCase().includes(q));
            },
            // Raster: Namensspalte 16rem (wie die KW-Box), dann je Öffnungstag eine
            // gleich breite Spalte – bündig zu den Header-Tagen.
            get ovGridStyle() { return 'grid-template-columns: 16rem repeat(' + this.ovDays.length + ', minmax(0, 1fr))'; },
            reportUrl(id) { return this.urls.reportPerson + '/' + id; },

            // ---- Touch-Kalender ----
            get openSet() { return new Set(this.openDates); },
            get calLabel() {
                const m = ['Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];
                return m[this.calMonth] + ' ' + this.calYear;
            },
            get calCells() {
                const first = new Date(this.calYear, this.calMonth, 1);
                const startWeekday = (first.getDay() + 6) % 7; // Mo=0
                const daysInMonth = new Date(this.calYear, this.calMonth + 1, 0).getDate();
                const set = this.openSet;
                const cells = [];
                for (let i = 0; i < startWeekday; i++) cells.push(null);
                for (let d = 1; d <= daysInMonth; d++) {
                    const ds = this.calYear + '-' + String(this.calMonth + 1).padStart(2, '0') + '-' + String(d).padStart(2, '0');
                    cells.push({ day: d, date: ds, open: set.has(ds), current: ds === this.date, today: ds === this.today });
                }
                return cells;
            },
            openDateModal() {
                const [y, m] = this.date.split('-').map(Number);
                this.calYear = y; this.calMonth = m - 1;
                this.dateModalOpen = true;
            },
            calPrev() { if (this.calMonth === 0) { this.calMonth = 11; this.calYear--; } else this.calMonth--; },
            calNext() { if (this.calMonth === 11) { this.calMonth = 0; this.calYear++; } else this.calMonth++; },
            jumpToday() { const [y, m] = this.today.split('-').map(Number); this.calYear = y; this.calMonth = m - 1; },
            pickDate(ds) { this.gotoDate(ds); },
        }));
    });
</script>

<div x-data="terminal()" x-cloak class="flex h-full flex-col select-none">

    {{-- Zu kleiner Bildschirm: Vollbild-Hinweis (Terminal braucht mind. 1000 × 700). --}}
    <div x-show="tooSmall" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center bg-gray-900 p-8 text-center">
        <div class="max-w-md">
            <div class="text-6xl">🖥️</div>
            <h2 class="mt-4 text-2xl font-bold text-white">Bildschirm zu klein</h2>
            <p class="mt-3 text-lg text-gray-300">Das Ausgabe-Terminal benötigt mindestens <strong class="text-white">1000 × 700 Pixel</strong>.</p>
            <p class="mt-2 text-sm text-gray-400">Aktuell: <span x-text="vw"></span> × <span x-text="vh"></span> px</p>
            <a href="{{ route('module.schulkantine.servings.index') }}"
               class="mt-6 inline-block rounded-lg bg-indigo-600 px-5 py-3 text-sm font-medium text-white hover:bg-indigo-700">Zur normalen Ausgabe</a>
        </div>
    </div>

    {{-- ================= INFOZEILE (10%) ================= --}}
    {{-- Gleicher Header in allen Modi. Im Übersicht-Modus wird die KW-Box so breit
         wie die Namensspalte und der untere Rand entfällt, damit Header und Matrix
         nahtlos ineinander übergehen (Tage/KW stehen dann nur oben, nicht doppelt). --}}
    <header class="flex h-[10%] min-h-[72px] w-full items-stretch bg-white"
            :class="mode === 'overview' ? '' : 'border-b border-gray-300'"
            :style="mode === 'overview' ? ('padding-right:' + sbw + 'px') : ''">

        {{-- Links: ‹ Woche zurück + KW/Datum (Kalender). Der ›-Button (Woche vor) steht
             bewusst RECHTS nach den Tagen. In der Übersicht so breit wie die Namensspalte
             (16rem), damit die Spalten zusammenpassen. --}}
        <div class="flex items-stretch border-r border-gray-200 bg-gray-50"
             :class="mode === 'overview' ? 'w-64 shrink-0' : 'w-[13%] min-w-[168px]'">
            {{-- Woche zurück --}}
            <button type="button" @click="kwStep(-1)" :disabled="!hasPrevKw" title="Woche zurück"
                    class="flex w-8 shrink-0 items-center justify-center border-r border-gray-200 text-3xl font-bold text-gray-500 hover:bg-gray-100 disabled:opacity-30">‹</button>
            {{-- Mitte: KW + Datum, öffnet den Kalender --}}
            <button type="button" @click="openDateModal()"
                    class="flex min-w-0 flex-1 flex-col items-center justify-center gap-0.5 px-1 leading-tight hover:bg-gray-100">
                <div class="text-2xl font-bold text-indigo-700">KW {{ $week['kw'] }}</div>
                <div class="text-sm font-semibold text-gray-700">{{ $date->isoFormat('dd, D.M.') }}</div>
                <div class="text-[10px] font-medium text-gray-400">📅 Tag wählen</div>
            </button>
        </div>

        {{-- Rechts: Wochenüberblick ODER Person --}}
        <div class="relative flex-1 overflow-hidden">
            {{-- Wochenüberblick (Leerlauf) --}}
            <div x-show="!person" class="flex h-full items-stretch divide-x divide-gray-100 overflow-x-auto">
                @foreach ($week['days'] as $wd)
                    {{-- Ganze Tages-Zelle klickbar: springt auf diesen Tag (wie Kalenderauswahl). --}}
                    <button type="button" @click="gotoDate('{{ $wd['date'] }}')"
                            class="flex min-w-[9rem] flex-1 flex-col px-3 py-1 text-left transition hover:bg-indigo-100/70 {{ $wd['isWorking'] ? 'bg-indigo-50/60' : '' }}">
                        <div class="flex items-baseline justify-between">
                            <span class="text-sm font-semibold {{ $wd['isWorking'] ? 'text-indigo-700' : 'text-gray-700' }}">{{ $wd['weekday'] }} {{ $wd['dayLabel'] }}</span>
                        </div>
                        {{-- Vorbesteller/Übersicht: Menüs des Tages mit Bestellanzahl --}}
                        <div x-show="mode !== 'ogs'" class="mt-0.5 space-y-0.5 overflow-hidden text-[11px] leading-tight text-gray-500">
                            @forelse ($wd['dishes'] as $d)
                                <div class="flex justify-between gap-2">
                                    <span class="truncate">{{ $d['name'] }}</span>
                                    <span class="shrink-0 font-semibold text-gray-700">{{ $d['ordered'] }}×</span>
                                </div>
                            @empty
                                <div class="text-gray-300">–</div>
                            @endforelse
                        </div>
                        {{-- OGS: nur die Anzahl der Vorbestellungen (essende OGS-Kinder) --}}
                        <div x-show="mode === 'ogs'" x-cloak class="mt-0.5 flex items-baseline gap-1 leading-tight">
                            <span class="text-2xl font-bold {{ $wd['ogsCount'] ? 'text-indigo-700' : 'text-gray-300' }}">{{ $wd['ogsCount'] }}</span>
                            <span class="text-[11px] font-medium text-gray-400">essen</span>
                        </div>
                    </button>
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

        {{-- Rechts (nach den Tagen): › Woche vor + Übersicht-Button. Als Block gemessen
             (x-ref="ovRight"), damit die Übersicht-Matrix rechtsbündig darunter passt. --}}
        <div x-ref="ovRight" class="flex items-stretch">
            {{-- Woche vor – steht nach den Tagen der aktuellen Woche --}}
            <button type="button" @click="kwStep(1)" :disabled="!hasNextKw" title="Woche vor"
                    class="flex w-8 shrink-0 items-center justify-center border-l border-gray-200 bg-gray-50 text-3xl font-bold text-gray-500 hover:bg-gray-100 disabled:opacity-30">›</button>
            {{-- Übersicht öffnen (Männchen) bzw. schließen (rotes X → zurück zur Herkunft) --}}
            <div class="flex items-center border-l border-gray-200 bg-gray-50 px-3">
                <button type="button" @click="toggleOverview()" :title="mode === 'overview' ? 'Übersicht schließen' : 'Übersicht'"
                        class="flex h-14 w-14 items-center justify-center rounded-xl shadow-sm transition"
                        :class="mode === 'overview' ? 'bg-red-600 text-white hover:bg-red-700' : 'bg-white text-indigo-600 hover:bg-indigo-50'">
                    {{-- Übersicht offen: weißes X zum Schließen --}}
                    <svg x-show="mode === 'overview'" x-cloak viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" class="h-8 w-8"><path d="M6 6l12 12M18 6L6 18"/></svg>
                    {{-- sonst: Männchen --}}
                    <svg x-show="mode !== 'overview'" viewBox="0 0 24 24" fill="currentColor" class="h-8 w-8"><path d="M12 12a5 5 0 1 0-5-5 5 5 0 0 0 5 5zm0 2c-4.42 0-8 2.24-8 5v1h16v-1c0-2.76-3.58-5-8-5z"/></svg>
                </button>
            </div>
        </div>
    </header>

    {{-- Banner (Fehler/Erfolg) --}}
    <div x-show="banner" x-cloak
         class="absolute left-1/2 top-[11%] z-40 -translate-x-1/2 rounded-xl px-6 py-3 text-lg font-semibold shadow-lg"
         :class="banner?.ok ? 'bg-green-600 text-white' : 'bg-red-600 text-white'"
         @click="banner=null" x-text="banner?.text"></div>

    {{-- ================= OGS-ANSICHT ================= --}}
    {{-- Keine Menüs/Spalten-Trennung: jedes heute essende OGS-Kind ist eine
         klickbare Kachel (Tipp = abgeholt). Spaltenweise nach Vorname (CSS-
         Mehrspalten füllt Spalte für Spalte → automatisch A–C | D–K | …).
         Abgeholte springen ins grüne Band unten; erneuter Tipp nimmt zurück. --}}
    <main x-show="mode === 'ogs'" x-cloak class="flex min-h-0 flex-1 flex-col bg-gray-50">
        @if (! $open)
            <div class="flex flex-1 items-center justify-center text-center">
                <div>
                    <div class="text-2xl font-semibold text-amber-700">🔒 Heute geschlossen</div>
                    <p class="mt-2 text-gray-500">{{ $closedReason }}</p>
                </div>
            </div>
        @else
            {{-- Kopfzeile: Fortschritt abgeholt / offen / gesamt --}}
            <div class="flex items-center justify-between border-b border-gray-200 bg-white px-4 py-2">
                <div class="flex items-center gap-3">
                    <h2 class="text-lg font-bold text-gray-700">OGS – Essen abholen</h2>
                    <button type="button" @click="mode = 'vorbesteller'"
                            class="rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">↔ zu den Vorbestellern wechseln</button>
                </div>
                <div class="flex items-center gap-2 text-sm font-semibold">
                    <span class="rounded-full bg-green-100 px-3 py-1 text-green-700"><span x-text="ogsServedCount"></span> abgeholt</span>
                    <span x-show="ogsDeclinedCount" x-cloak class="rounded-full bg-red-100 px-3 py-1 text-red-700"><span x-text="ogsDeclinedCount"></span> abgelehnt</span>
                    <span class="rounded-full bg-gray-100 px-3 py-1 text-gray-600"><span x-text="ogsActive.length"></span> offen</span>
                    <span class="text-gray-400">von <span x-text="ogsEaters.length"></span></span>
                </div>
            </div>

            {{-- Leerfall: heute isst kein OGS-Kind --}}
            <div x-show="!ogsEaters.length" x-cloak class="flex flex-1 items-center justify-center text-center">
                <div>
                    <div class="text-6xl">🧑‍🍳</div>
                    <p class="mt-4 text-xl font-medium text-gray-400">Heute essen keine OGS-Kinder.</p>
                </div>
            </div>

            {{-- Alle Kinder in EINER Spaltenliste: offene oben, abgeholte grün ans Ende
                 (sinken unten in die Spalten). Tipp schaltet abgeholt ⇄ offen um.
                 Extra Abstand unten, damit die fixierte Fußleiste (Chip/Suchen/…) die
                 letzte Zeile nicht verdeckt. --}}
            <div x-show="ogsEaters.length" x-cloak class="min-h-0 flex-1 overflow-y-auto p-3 pb-20">
                <div class="columns-2 gap-3 md:columns-3 xl:columns-4">
                    <template x-for="e in ogsOrdered" :key="e.user_id">
                        {{-- Kurzer Tipp = abgeholt ⇄ offen; Long-Press = Detail-Modal
                             (Unverträglichkeiten + abgeholt/abgelehnt). Drei Zustände:
                             offen (weiß ○) · abgeholt (grün ✓) · abgelehnt (rot ✗). --}}
                        <button type="button" :disabled="ogsBusy"
                                @click="ogsTileClick(e.user_id)"
                                @pointerdown="ogsPressStart(e.user_id)"
                                @pointerup="ogsPressEnd()" @pointerleave="ogsPressEnd()" @pointercancel="ogsPressEnd()"
                                @contextmenu.prevent
                                class="mb-3 flex w-full select-none break-inside-avoid items-center gap-3 rounded-2xl border-2 p-3 text-left shadow-sm transition disabled:opacity-60"
                                :class="!e.served ? 'border-gray-200 bg-white hover:border-indigo-400 active:bg-indigo-50'
                                        : (e.declined ? 'border-red-400 bg-red-50 hover:border-red-500 active:bg-red-100'
                                                      : 'border-green-400 bg-green-50 hover:border-green-500 active:bg-green-100')">
                            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl text-xl font-bold"
                                 :class="!e.served ? 'bg-indigo-100 text-indigo-700'
                                         : (e.declined ? 'bg-red-200 text-red-800' : 'bg-green-200 text-green-800')"
                                 x-text="(e.first || '?').slice(0,1)"></div>
                            <div class="min-w-0 flex-1">
                                <div class="truncate text-lg font-bold leading-tight"
                                     :class="!e.served ? 'text-gray-800' : (e.declined ? 'text-red-900' : 'text-green-900')" x-text="e.name"></div>
                                <div x-show="ogsSonderkost(e).length" x-cloak class="truncate text-xs font-semibold text-amber-700">
                                    ⚠ <span x-text="ogsSonderkost(e).join(', ')"></span>
                                </div>
                            </div>
                            <span class="shrink-0 text-3xl"
                                  :class="!e.served ? 'text-gray-300' : (e.declined ? 'text-red-600' : 'text-green-600')"
                                  x-text="!e.served ? '○' : (e.declined ? '✗' : '✓')"></span>
                        </button>
                    </template>
                </div>
            </div>
        @endif
    </main>

    {{-- ================= ÜBERSICHT (Wochen-Matrix) ================= --}}
    {{-- Geht nahtlos aus dem Header hervor: KW/Tage stehen bereits oben. Jede Zeile
         ist ein Raster, dessen Spalten exakt zu Header (KW-Box + Tages-Spalten)
         passen: Namensspalte = 16rem (wie KW-Box), Tage gleich breit, rechter
         Abstand = Männchen-Button (pr-20). Name verlinkt auf die Personen-Auswertung. --}}
    <main x-show="mode === 'overview'" x-cloak class="flex min-h-0 flex-1 flex-col bg-gray-50">
        <div class="min-h-0 flex-1 overflow-y-scroll overflow-x-hidden pb-20" :style="'padding-right:' + rcw + 'px'">
            <div x-show="ovLoading" x-cloak class="py-10 text-center text-gray-400">Lädt …</div>
            <div x-show="!ovLoading && !ovDays.length" x-cloak class="py-10 text-center text-gray-400">In dieser Woche sind keine Öffnungstage.</div>

            <template x-if="!ovLoading && ovDays.length">
                <div class="text-sm">
                    <template x-for="p in ovFiltered" :key="p.user_id">
                        <div class="grid border-b border-gray-100" :style="ovGridStyle">
                            {{-- Name (Spalte = KW-Box-Breite) --}}
                            <div class="border-r border-gray-200 px-3 py-2" :class="p.mode === 'ogs' ? 'bg-indigo-50/50' : ''">
                                <a :href="reportUrl(p.user_id)" target="_blank" rel="noopener"
                                   class="font-semibold text-indigo-700 hover:underline" x-text="p.name"></a>
                                <span class="ml-1 rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-medium uppercase text-gray-500" x-text="p.group"></span>
                            </div>
                            {{-- Tages-Spalten (bündig unter den Header-Tagen) --}}
                            <template x-for="d in ovDays" :key="d.date">
                                <div class="border-r border-gray-100 px-3 py-2" :class="p.mode === 'ogs' ? 'bg-indigo-50/20' : ''">
                                    {{-- OGS: teilgenommen ja/nein --}}
                                    <template x-if="p.mode === 'ogs'">
                                        <span class="text-sm font-bold" :class="p.days[d.date] && p.days[d.date].ogs ? 'text-green-600' : 'text-gray-300'"
                                              x-text="p.days[d.date] && p.days[d.date].ogs ? '✓ isst' : '–'"></span>
                                    </template>
                                    {{-- Vorbesteller: die vorbestellten Gerichte --}}
                                    <template x-if="p.mode !== 'ogs'">
                                        <div>
                                            <template x-if="p.days[d.date] && p.days[d.date].dishes.length">
                                                <div class="space-y-0.5">
                                                    <template x-for="name in p.days[d.date].dishes" :key="name">
                                                        <div class="text-gray-700" x-text="name"></div>
                                                    </template>
                                                </div>
                                            </template>
                                            <span x-show="!p.days[d.date] || !p.days[d.date].dishes.length" x-cloak class="text-gray-300">–</span>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </template>
                    <div x-show="!ovFiltered.length" x-cloak class="px-3 py-10 text-center text-gray-400">Keine Treffer.</div>
                </div>
            </template>
        </div>
    </main>

    {{-- ================= ARBEITSZEILE (90%) ================= --}}
    <main x-show="mode === 'vorbesteller'" class="flex min-h-0 flex-1">

        @if (! $open)
            <div class="flex flex-1 items-center justify-center text-center">
                <div>
                    <div class="text-2xl font-semibold text-amber-700">🔒 Heute geschlossen</div>
                    <p class="mt-2 text-gray-500">{{ $closedReason }}</p>
                </div>
            </div>
        @else

        {{-- ---------- LINKS: Menüs (1/3) ---------- --}}
        <section class="flex w-1/2 min-w-0 flex-col border-r border-gray-300 bg-white xl:w-1/3">
            <div class="flex items-center justify-between border-b border-gray-200 px-4 py-2">
                <div class="flex items-center gap-3">
                    <h2 class="text-lg font-bold text-gray-700">Menüs</h2>
                    @if ($hasOgs)
                        <button type="button" @click="mode = 'ogs'"
                                class="rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">↔ zur OGS wechseln</button>
                    @endif
                </div>
                <div class="flex items-center gap-3 text-[11px] font-semibold uppercase tracking-wide">
                    <span class="text-gray-500">bestellt</span>
                    <span class="text-amber-600">offen</span>
                    <span class="text-green-600">ausgeg.</span>
                </div>
            </div>

            {{-- Bei „keine Vorbestellung" das Scrollen sperren, damit das Overlay den
                 ganzen sichtbaren Bereich abdeckt und unten nicht das Grau wegscrollt. --}}
            <div class="relative flex-1 p-3" :class="(person && !person.hasOrder) ? 'overflow-hidden' : 'overflow-y-auto'">
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
                                    {{-- Ganze Kachel öffnet das Detail-Modal; die Auswahl läuft
                                         über den eigenen Button rechts (@click.stop). --}}
                                    <div @click="openDishModal(dish)"
                                         class="flex w-full cursor-pointer items-stretch gap-4 rounded-2xl border-2 p-3 text-left transition"
                                         :class="{
                                                'border-green-500 bg-green-50 ring-2 ring-green-300': tileState(group.category_id, dish.id)==='taken',
                                                'border-amber-500 bg-amber-50 ring-2 ring-amber-300': tileState(group.category_id, dish.id)==='alt',
                                                'border-red-300 bg-red-50 opacity-70': tileState(group.category_id, dish.id)==='declined',
                                                'border-indigo-300 bg-white hover:border-indigo-500': tileState(group.category_id, dish.id)==='selectable',
                                                'border-gray-200 bg-white': tileState(group.category_id, dish.id)==='idle'
                                            }">
                                        {{-- Bild oder Platzhalter + ⚠️ bei Unverträglichkeit --}}
                                        <div class="relative h-24 w-24 shrink-0 overflow-hidden rounded-xl border border-black/5 bg-gray-50">
                                            <template x-if="dish.photo"><img :src="dish.photo" alt="" class="h-24 w-24 object-cover"></template>
                                            <template x-if="!dish.photo"><x-schulkantine::dish-placeholder /></template>
                                            <div x-show="dishWarn(dish)" x-cloak
                                                 class="absolute left-0 top-0 flex h-8 w-8 items-center justify-center rounded-br-xl bg-yellow-400 text-lg font-black text-yellow-900 shadow">!</div>
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
                                            <span class="flex items-center gap-2 text-xl font-bold leading-tight text-gray-800">
                                                <span x-text="dish.name"></span>
                                                <span x-show="dishWarn(dish)" x-cloak class="shrink-0 rounded-md bg-yellow-100 px-1.5 text-sm font-bold text-yellow-800">⚠ nicht geeignet</span>
                                            </span>
                                            <div x-show="dish.is_bundle" class="text-sm text-teal-700" x-text="dish.components.join(' + ')"></div>
                                        </div>
                                        {{-- Auswahl-Button (nur in einer bestellten Kategorie) --}}
                                        <button type="button" x-show="tileClickable(group.category_id)" x-cloak
                                                @click.stop="clickTile(group.category_id, dish.id)"
                                                class="flex w-24 shrink-0 flex-col items-center justify-center gap-1 rounded-xl border-2 text-sm font-bold transition"
                                                :class="{
                                                    'border-green-500 bg-green-100 text-green-700': tileState(group.category_id, dish.id)==='taken',
                                                    'border-amber-500 bg-amber-100 text-amber-700': tileState(group.category_id, dish.id)==='alt',
                                                    'border-red-300 bg-red-100 text-red-600': tileState(group.category_id, dish.id)==='declined',
                                                    'border-gray-300 bg-white text-gray-400 hover:border-indigo-400': tileState(group.category_id, dish.id)==='selectable'
                                                }">
                                            <span class="text-3xl" x-text="['taken','alt'].includes(tileState(group.category_id, dish.id)) ? '✓' : (tileState(group.category_id, dish.id)==='declined' ? '✗' : '○')"></span>
                                            <span x-text="tileState(group.category_id, dish.id)==='taken' ? 'ausgeben' : (tileState(group.category_id, dish.id)==='alt' ? 'Alternative' : (tileState(group.category_id, dish.id)==='declined' ? 'nein' : 'wählen'))"></span>
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </fieldset>
                    </template>
                    <div x-show="!planGroups.length" class="py-10 text-center text-gray-400">Kein vorbestellbares Menü heute.</div>
                </div>
            </div>
        </section>

        {{-- ---------- RECHTS: Besonderheiten (2/3) ---------- --}}
        <section class="flex w-1/2 min-w-0 flex-col bg-gray-50 xl:w-2/3">
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
                                        <button type="button" @click.stop="walkinMinus(dish.id)" :disabled="!walkinQty[dish.id]"
                                                class="step-btn flex h-16 w-16 shrink-0 items-center justify-center rounded-xl bg-gray-200 text-4xl font-bold text-gray-700 shadow-sm disabled:opacity-30">−</button>
                                        {{-- Artikel-Kachel wie links: klickbar fürs Detail-Modal, Bild + Name + Preis, ⚠️ bei Konflikt. --}}
                                        <div @click="openDishModal(dish)"
                                             class="flex w-[19rem] cursor-pointer items-stretch gap-3 rounded-2xl border-2 bg-white p-2 shadow-sm"
                                             :class="walkinQty[dish.id] ? 'border-indigo-400 ring-2 ring-indigo-200' : 'border-gray-200'">
                                            <div class="relative h-20 w-20 shrink-0 overflow-hidden rounded-xl border border-black/5 bg-gray-50">
                                                <template x-if="dish.photo"><img :src="dish.photo" alt="" class="h-20 w-20 object-cover"></template>
                                                <template x-if="!dish.photo"><x-schulkantine::dish-placeholder /></template>
                                                <div x-show="dishWarn(dish)" x-cloak
                                                     class="absolute left-0 top-0 flex h-7 w-7 items-center justify-center rounded-br-xl bg-yellow-400 text-base font-black text-yellow-900 shadow">!</div>
                                            </div>
                                            <div class="flex min-w-0 flex-1 flex-col justify-center pr-1">
                                                <span class="text-lg font-semibold leading-tight text-gray-800" x-text="dish.name"></span>
                                                <span class="mt-0.5 text-base font-medium text-gray-500" x-text="euro(dish.price)"></span>
                                                <span x-show="dishWarn(dish)" x-cloak class="mt-0.5 text-xs font-bold text-yellow-700">⚠ nicht geeignet</span>
                                            </div>
                                        </div>
                                        <button type="button" @click.stop="walkinPlus(dish.id)"
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

                {{-- Unten: Nachschlag – exakt auf die Walk-in-Zeilen darüber ausgerichtet:
                     gleiche −/+-Buttons, Münze über die Artikel-Breite zentriert, Preis in
                     der Preis-Spalte. Der Preis ist klickbar (öffnet die Zahlen-Tastatur). --}}
                <div class="border-t border-gray-200 bg-white p-3">
                    <div class="mb-2 text-xs font-bold uppercase tracking-wide text-gray-400">Nachschlag</div>
                    <div class="flex items-center justify-center gap-8">
                        <button type="button" @click="nachschlagMinus()" :disabled="nachschlagTotal <= 0"
                                class="step-btn flex h-16 w-16 shrink-0 items-center justify-center rounded-xl bg-gray-200 text-4xl font-bold text-gray-700 shadow-sm disabled:opacity-30">−</button>
                        <div class="flex w-[19rem] items-center justify-center rounded-2xl border-2 bg-amber-50 p-2 shadow-sm"
                             :class="nachschlagTotal > 0 ? 'border-amber-400 ring-2 ring-amber-200' : 'border-amber-200'">
                            <div class="h-20 w-20"><x-schulkantine::coin value="50c" /></div>
                        </div>
                        <button type="button" @click="nachschlagPlus()"
                                class="step-btn flex h-16 w-16 shrink-0 items-center justify-center rounded-xl bg-amber-500 text-4xl font-bold text-white shadow-sm">+</button>
                        {{-- Preis = klickbar zum Betrag eingeben (dezent gepunktet unterstrichen). --}}
                        <button type="button" @click="openPad()"
                                class="min-w-[6.5rem] whitespace-nowrap text-left text-xl font-extrabold underline decoration-dotted underline-offset-4 hover:text-amber-700"
                                :class="nachschlagTotal > 0 ? 'text-amber-900' : 'text-gray-400'" x-text="euro(nachschlagTotal)"></button>
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

        <button @click="mode === 'overview' ? toggleOvSearch() : openSearch()"
                class="rounded-full px-4 py-2 text-sm font-medium text-white shadow-lg"
                :class="(mode === 'overview' && ovSearchOpen) ? 'bg-indigo-600 hover:bg-indigo-700' : 'bg-gray-800/80 hover:bg-gray-700'">🔍 Suchen</button>

        <a href="{{ route('module.schulkantine.servings.index') }}"
           class="rounded-full bg-gray-800/70 px-4 py-2 text-sm font-medium text-white shadow-lg hover:bg-gray-800">✕ Terminal verlassen</a>
    </div>

    {{-- Übersicht-Such-Overlay: KEIN Modal – nur eine schwebende Tastatur; die Liste
         im mittleren Bereich filtert live (ab dem 3. Buchstaben). --}}
    <div x-show="mode === 'overview' && ovSearchOpen" x-cloak
         class="fixed bottom-16 left-1/2 z-40 w-full max-w-2xl -translate-x-1/2 rounded-2xl border border-gray-200 bg-white/95 p-4 shadow-2xl backdrop-blur">
        <div class="mb-3 flex items-center gap-2">
            <div class="flex-1 rounded-xl border border-gray-300 px-4 py-2.5 text-lg">
                <span x-show="ovQuery" x-text="ovQuery"></span>
                <span x-show="!ovQuery" x-cloak class="text-gray-400">Name eingeben – ab dem 3. Buchstaben filtert die Liste …</span>
            </div>
            <button type="button" @click="toggleOvSearch()" class="rounded-xl px-3 py-2 text-xl text-gray-400 hover:bg-gray-100">✕</button>
        </div>
        <div class="select-none space-y-2">
            <template x-for="(row, i) in keyboardRows" :key="'ov'+i">
                <div class="flex justify-center gap-1.5">
                    <template x-for="k in row" :key="'ov'+k">
                        <button type="button" @click="ovKey(k)"
                                class="h-12 min-w-[2.75rem] flex-1 rounded-lg bg-gray-100 text-lg font-semibold text-gray-800 shadow-sm hover:bg-gray-200 active:bg-indigo-200"
                                x-text="k"></button>
                    </template>
                </div>
            </template>
            <div class="flex justify-center gap-1.5">
                <button type="button" @click="ovKey(' ')" class="h-12 flex-[3] rounded-lg bg-gray-100 text-base font-semibold text-gray-700 shadow-sm hover:bg-gray-200 active:bg-indigo-200">Leerzeichen</button>
                <button type="button" @click="ovBackspace()" class="h-12 flex-1 rounded-lg bg-amber-100 text-2xl font-bold text-amber-800 shadow-sm hover:bg-amber-200">⌫</button>
                <button type="button" @click="ovClear()" class="h-12 flex-1 rounded-lg bg-red-100 text-base font-bold text-red-700 shadow-sm hover:bg-red-200">Löschen</button>
            </div>
        </div>
    </div>

    {{-- Such-Modal: Live-Suche nach Person (max. 3 Treffer, Name + Klasse) mit
         eingebauter Bildschirmtastatur (Touch). --}}
    <div x-show="searchOpen" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/40 p-6"
         @click.self="closeSearch()" @keydown.escape.window="closeSearch()">
        <div class="w-full max-w-2xl rounded-2xl bg-white p-5 shadow-2xl">
            <div class="mb-3 flex items-center justify-between">
                <h3 class="text-lg font-bold text-gray-800">Person suchen</h3>
                <button @click="closeSearch()" class="rounded-lg px-3 py-1 text-xl text-gray-400 hover:bg-gray-100">✕</button>
            </div>
            {{-- inputmode=none: die OS-Tastatur bleibt aus, es zählt die Bildschirmtastatur unten. --}}
            <input type="search" x-model="searchQuery" @input="queueSearch()" x-ref="searchInput" inputmode="none"
                   placeholder="Namen eingeben …" readonly
                   class="w-full rounded-xl border-gray-300 px-4 py-3 text-xl shadow-sm focus:border-indigo-500 focus:ring-indigo-500">

            {{-- Feste Höhe für bis zu drei Trefferzeilen, damit die Tastatur darunter
                 nicht springt, wenn Treffer erscheinen oder verschwinden. --}}
            <div class="mt-3 h-[13.5rem] space-y-2 overflow-hidden">
                <template x-for="r in searchResults" :key="r.id">
                    <button @click="pickSearch(r.id)"
                            class="flex w-full items-center justify-between gap-3 rounded-xl border border-gray-200 p-4 text-left hover:border-indigo-400 hover:bg-indigo-50">
                        <span class="truncate text-xl font-semibold text-gray-800" x-text="r.name"></span>
                        <span class="shrink-0 rounded-full bg-indigo-100 px-3 py-1 text-base font-semibold text-indigo-700" x-text="'Klasse: ' + (r.group || '–')"></span>
                    </button>
                </template>
                <div x-show="searchQuery.trim().length >= 3 && !searchResults.length && !searching" x-cloak class="py-3 text-center text-sm text-gray-400">Keine Treffer.</div>
                <div x-show="searchQuery.trim().length < 3" x-cloak class="py-3 text-center text-sm text-gray-400">
                    <span x-show="searchQuery.trim().length === 0">Namen eingeben – ab dem 3. Buchstaben wird gesucht.</span>
                    <span x-show="searchQuery.trim().length > 0" x-cloak>Noch <span x-text="3 - searchQuery.trim().length"></span> Buchstabe<span x-show="(3 - searchQuery.trim().length) !== 1">n</span> …</span>
                </div>
            </div>

            {{-- Bildschirmtastatur (QWERTZ) --}}
            <div class="mt-4 select-none space-y-2 border-t border-gray-100 pt-4">
                <template x-for="(row, i) in keyboardRows" :key="i">
                    <div class="flex justify-center gap-1.5">
                        <template x-for="k in row" :key="k">
                            <button type="button" @click="keyPress(k)"
                                    class="h-14 min-w-[3rem] flex-1 rounded-lg bg-gray-100 text-xl font-semibold text-gray-800 shadow-sm hover:bg-gray-200 active:bg-indigo-200"
                                    x-text="k"></button>
                        </template>
                    </div>
                </template>
                <div class="flex justify-center gap-1.5">
                    <button type="button" @click="keyPress(' ')" class="h-14 flex-[3] rounded-lg bg-gray-100 text-base font-semibold text-gray-700 shadow-sm hover:bg-gray-200 active:bg-indigo-200">Leerzeichen</button>
                    <button type="button" @click="keyBackspace()" class="h-14 flex-1 rounded-lg bg-amber-100 text-2xl font-bold text-amber-800 shadow-sm hover:bg-amber-200">⌫</button>
                    <button type="button" @click="keyClear()" class="h-14 flex-1 rounded-lg bg-red-100 text-base font-bold text-red-700 shadow-sm hover:bg-red-200">Löschen</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Zahlen-Tastatur: manueller Nachschlag-Betrag --}}
    <div x-show="padOpen" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-6"
         @click.self="padCancel()" @keydown.escape.window="padCancel()">
        <div class="w-full max-w-sm rounded-2xl bg-white p-5 shadow-2xl">
            <div class="mb-3 flex items-center justify-between">
                <h3 class="text-lg font-bold text-gray-800">Nachschlag-Betrag</h3>
                <button @click="padCancel()" class="rounded-lg px-3 py-1 text-xl text-gray-400 hover:bg-gray-100">✕</button>
            </div>
            <div class="mb-4 rounded-xl border-2 border-amber-200 bg-amber-50 px-4 py-3 text-right text-3xl font-extrabold text-amber-900">
                <span x-text="(padValue || '0') + ' €'"></span>
            </div>
            <div class="grid grid-cols-3 gap-2">
                <template x-for="n in ['1','2','3','4','5','6','7','8','9']" :key="n">
                    <button type="button" @click="padKey(n)" class="h-16 rounded-xl bg-gray-100 text-2xl font-bold text-gray-800 shadow-sm hover:bg-gray-200 active:bg-amber-200" x-text="n"></button>
                </template>
                <button type="button" @click="padKey(',')" class="h-16 rounded-xl bg-gray-100 text-2xl font-bold text-gray-800 shadow-sm hover:bg-gray-200 active:bg-amber-200">,</button>
                <button type="button" @click="padKey('0')" class="h-16 rounded-xl bg-gray-100 text-2xl font-bold text-gray-800 shadow-sm hover:bg-gray-200 active:bg-amber-200">0</button>
                <button type="button" @click="padBackspace()" class="h-16 rounded-xl bg-amber-100 text-2xl font-bold text-amber-800 shadow-sm hover:bg-amber-200">⌫</button>
            </div>
            <div class="mt-4 flex gap-2">
                <button type="button" @click="padClear()" class="h-14 flex-1 rounded-xl bg-red-100 text-base font-bold text-red-700 shadow-sm hover:bg-red-200">Löschen</button>
                <button type="button" @click="padCancel()" class="h-14 flex-1 rounded-xl border border-gray-300 bg-white text-base font-semibold text-gray-600 hover:bg-gray-50">Abbrechen</button>
                <button type="button" @click="padConfirm()" class="h-14 flex-[1.5] rounded-xl bg-green-600 text-base font-bold text-white shadow hover:bg-green-700">Übernehmen</button>
            </div>
        </div>
    </div>

    {{-- OGS-Detail-Modal (Long-Press auf ein OGS-Kind): alle Unverträglichkeiten,
         dann ausdrücklich abgeholt / abgelehnt / offen wählen. --}}
    <div x-show="ogsModalOpen" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/40 p-6"
         @click.self="closeOgsModal()" @keydown.escape.window="closeOgsModal()">
        <template x-if="ogsModalEater">
            <div class="w-full max-w-md rounded-2xl bg-white p-5 shadow-2xl">
                <div class="mb-3 flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h3 class="truncate text-2xl font-bold text-gray-800" x-text="ogsModalEater.name"></h3>
                        <div class="mt-0.5 text-sm font-medium"
                             :class="!ogsModalEater.served ? 'text-gray-400' : (ogsModalEater.declined ? 'text-red-600' : 'text-green-600')"
                             x-text="!ogsModalEater.served ? 'Status: offen' : (ogsModalEater.declined ? 'Status: abgelehnt' : 'Status: abgeholt')"></div>
                    </div>
                    <button @click="closeOgsModal()" class="shrink-0 rounded-lg px-3 py-1 text-xl text-gray-400 hover:bg-gray-100">✕</button>
                </div>

                {{-- Unverträglichkeiten --}}
                <div class="rounded-xl bg-gray-50 p-3">
                    <div class="text-xs font-bold uppercase tracking-wide text-gray-400">Unverträglichkeiten</div>
                    <div class="mt-1.5 flex flex-wrap gap-1.5">
                        <template x-for="a in (ogsModalEater.allergens || [])" :key="'a'+a">
                            <span class="rounded-full bg-amber-100 px-2.5 py-1 text-sm font-semibold text-amber-800" x-text="a"></span>
                        </template>
                        <template x-for="d in (ogsModalEater.diets || [])" :key="'d'+d">
                            <span class="rounded-full bg-purple-100 px-2.5 py-1 text-sm font-semibold text-purple-800" x-text="d"></span>
                        </template>
                        <span x-show="!ogsSonderkost(ogsModalEater).length" x-cloak class="text-sm text-gray-400">keine hinterlegt</span>
                    </div>
                </div>

                {{-- Aktionen: abgeholt / abgelehnt (+ zurücknehmen, wenn schon erledigt) --}}
                <div class="mt-4 grid grid-cols-2 gap-2">
                    <button type="button" @click="ogsSet('served')" :disabled="ogsBusy"
                            class="flex items-center justify-center gap-2 rounded-xl bg-green-600 py-4 text-lg font-bold text-white shadow hover:bg-green-700 disabled:opacity-60">
                        <span class="text-2xl">✓</span> Abgeholt
                    </button>
                    <button type="button" @click="ogsSet('declined')" :disabled="ogsBusy"
                            class="flex items-center justify-center gap-2 rounded-xl bg-red-600 py-4 text-lg font-bold text-white shadow hover:bg-red-700 disabled:opacity-60">
                        <span class="text-2xl">✗</span> Abgelehnt
                    </button>
                </div>
                <button type="button" x-show="ogsModalEater.served" x-cloak @click="ogsSet('open')" :disabled="ogsBusy"
                        class="mt-2 w-full rounded-xl border border-gray-300 bg-white py-3 text-base font-semibold text-gray-600 hover:bg-gray-50 disabled:opacity-60">↺ Zurücknehmen (offen)</button>
            </div>
        </template>
    </div>

    {{-- Gericht-Detail-Modal: alle Werte, konfliktbehaftete hervorgehoben. --}}
    <div x-show="modalOpen" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/40 p-6"
         @click.self="closeDishModal()" @keydown.escape.window="closeDishModal()">
        <template x-if="modalDish">
            <div class="w-full max-w-lg rounded-2xl bg-white p-5 shadow-2xl">
                <div class="mb-3 flex items-start justify-between gap-3">
                    <h3 class="text-2xl font-bold text-gray-800" x-text="modalDish.name"></h3>
                    <button @click="closeDishModal()" class="shrink-0 rounded-lg px-3 py-1 text-xl text-gray-400 hover:bg-gray-100">✕</button>
                </div>

                {{-- Warnung, wenn es zur Sonderkost der Person nicht passt --}}
                <div x-show="dishWarn(modalDish)" x-cloak class="mb-3 rounded-xl bg-yellow-100 px-4 py-2 text-base font-bold text-yellow-900">
                    ⚠ Nicht geeignet für <span x-text="person?.name"></span> – enthält gemiedene Zutaten.
                </div>

                <div class="flex gap-4">
                    <div class="h-28 w-28 shrink-0 overflow-hidden rounded-xl border border-black/5 bg-gray-50">
                        <template x-if="modalDish.photo"><img :src="modalDish.photo" alt="" class="h-28 w-28 object-cover"></template>
                        <template x-if="!modalDish.photo"><x-schulkantine::dish-placeholder /></template>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="text-xl font-bold text-gray-800" x-text="euro(modalDish.price)"></div>
                        <div x-show="modalDish.is_bundle" x-cloak class="mt-1 text-sm font-medium text-teal-700">
                            Sparmenü: <span x-text="modalDish.components.join(' + ')"></span>
                        </div>
                    </div>
                </div>

                {{-- Allergene --}}
                <div class="mt-4">
                    <div class="text-xs font-bold uppercase tracking-wide text-gray-400">Allergene</div>
                    <div class="mt-1 flex flex-wrap gap-1.5">
                        <template x-for="a in modalDish.allergens" :key="a.id">
                            <span class="rounded-full px-2.5 py-1 text-sm font-medium"
                                  :class="allergenHit(a.id) ? 'bg-red-600 text-white' : 'bg-gray-100 text-gray-700'">
                                <span x-text="a.code"></span> <span x-text="a.name"></span>
                            </span>
                        </template>
                        <span x-show="!modalDish.allergens.length" class="text-sm text-gray-400">keine</span>
                    </div>
                </div>

                {{-- Zusatzstoffe --}}
                <div class="mt-3">
                    <div class="text-xs font-bold uppercase tracking-wide text-gray-400">Zusatzstoffe</div>
                    <div class="mt-1 flex flex-wrap gap-1.5">
                        <template x-for="a in modalDish.additives" :key="a.id">
                            <span class="rounded-full bg-gray-100 px-2.5 py-1 text-sm font-medium text-gray-700"><span x-text="a.code"></span> <span x-text="a.name"></span></span>
                        </template>
                        <span x-show="!modalDish.additives.length" class="text-sm text-gray-400">keine</span>
                    </div>
                </div>

                {{-- Nicht geeignet für (Diäten) --}}
                <div class="mt-3">
                    <div class="text-xs font-bold uppercase tracking-wide text-gray-400">Nicht geeignet für</div>
                    <div class="mt-1 flex flex-wrap gap-1.5">
                        <template x-for="d in modalDish.unsuitable" :key="d.id">
                            <span class="rounded-full px-2.5 py-1 text-sm font-medium"
                                  :class="dietHit(d.id) ? 'bg-red-600 text-white' : 'bg-gray-100 text-gray-700'" x-text="d.name"></span>
                        </template>
                        <span x-show="!modalDish.unsuitable.length" class="text-sm text-gray-400">–</span>
                    </div>
                </div>

                <button @click="closeDishModal()" class="mt-5 w-full rounded-xl bg-gray-800 py-3 text-base font-semibold text-white hover:bg-gray-700">Schließen</button>
            </div>
        </template>
    </div>

    {{-- Touch-Kalender: großer Monatsraster, nur Öffnungstage wählbar. --}}
    <div x-show="dateModalOpen" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-6"
         @click.self="dateModalOpen = false" @keydown.escape.window="dateModalOpen = false">
        <div class="w-full max-w-md rounded-2xl bg-white p-5 shadow-2xl">
            <div class="mb-3 flex items-center justify-between gap-2">
                <button type="button" @click="calPrev()" class="flex h-12 w-12 items-center justify-center rounded-xl bg-gray-100 text-3xl font-bold text-gray-600 hover:bg-gray-200">‹</button>
                <div class="text-lg font-bold text-gray-800" x-text="calLabel"></div>
                <button type="button" @click="calNext()" class="flex h-12 w-12 items-center justify-center rounded-xl bg-gray-100 text-3xl font-bold text-gray-600 hover:bg-gray-200">›</button>
            </div>
            <div class="grid grid-cols-7 gap-1 text-center text-xs font-semibold uppercase text-gray-400">
                <div>Mo</div><div>Di</div><div>Mi</div><div>Do</div><div>Fr</div><div>Sa</div><div>So</div>
            </div>
            <div class="mt-1 grid grid-cols-7 gap-1">
                <template x-for="(c, i) in calCells" :key="i">
                    <div>
                        <template x-if="c">
                            <button type="button" @click="c.open && pickDate(c.date)" :disabled="!c.open"
                                    class="flex h-12 w-full items-center justify-center rounded-lg text-lg font-bold transition"
                                    :class="c.current ? 'bg-indigo-600 text-white'
                                            : (c.open ? (c.today ? 'bg-indigo-100 text-indigo-700 ring-2 ring-indigo-400' : 'bg-gray-100 text-gray-800 hover:bg-indigo-100')
                                                      : 'cursor-default text-gray-300')"
                                    x-text="c.day"></button>
                        </template>
                        <template x-if="!c"><div class="h-12"></div></template>
                    </div>
                </template>
            </div>
            <div class="mt-4 flex gap-2">
                <button type="button" @click="jumpToday()" class="h-12 flex-1 rounded-xl bg-gray-100 text-base font-semibold text-gray-700 hover:bg-gray-200">Heute</button>
                <button type="button" @click="dateModalOpen = false" class="h-12 flex-1 rounded-xl border border-gray-300 bg-white text-base font-semibold text-gray-600 hover:bg-gray-50">Schließen</button>
            </div>
        </div>
    </div>


</div>
@endif
</body>
</html>
