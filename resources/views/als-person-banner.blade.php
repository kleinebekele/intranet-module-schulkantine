{{-- „Als Person handeln": unübersehbarer Balken unter der Kopfleiste + Rückfrage vor jeder Änderung.
     Nur auf Mensa-Seiten (Provider) – außerhalb wirkt es nicht und wird nicht angezeigt. --}}
<div id="kantine-als-person" class="fixed inset-x-0 top-16 z-40 border-b-4 border-red-900 bg-red-600 text-white shadow-lg">
    <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-2 sm:px-6 lg:pl-72">
        <div class="text-base font-extrabold uppercase tracking-wide sm:text-lg">
            ⚠️ Du handelst gerade als {{ $person->name }}
            <span class="ml-2 text-sm font-semibold normal-case tracking-normal text-red-100">– alles, was du hier einstellst, gilt für diese Person</span>
        </div>
        <form method="POST" action="{{ route('module.schulkantine.als.ende') }}" data-als-person-ende>
            @csrf
            <button type="submit" class="rounded-lg bg-white px-3 py-1.5 text-sm font-bold text-red-700 hover:bg-red-50">Beenden – wieder ich selbst</button>
        </form>
    </div>
</div>
<style>
    /* Platz für den Balken schaffen, damit er nichts verdeckt. */
    main { padding-top: 3.25rem; }
    /* Die feste Seitenleiste des Cores beginnt sonst unter dem Balken. */
    .fixed.top-16.w-64 { top: 7rem !important; }
</style>
<script>
        (function () {
            const name = @js($person->name);
            const frage = 'Du änderst gerade für ' + name + ' – nicht für dich selbst.\n\nFortfahren?';
            // Der Dialog des Cores (window.bestaetige), sonst das Browser-Popup.
            const frag = () => window.bestaetige
                ? window.bestaetige(frage, { titel: '⚠️ Als ' + name, knopf: 'Ja, für ' + name, gefaehrlich: true })
                : Promise.resolve(window.confirm(frage));

            // Jede Formular-Änderung (alles außer GET) vorher bestätigen lassen. Läuft vor der
            // Rückfrage der Seite (dieses Skript steht im Kopf, also früher angemeldet);
            // danach geht das Formular normal weiter – inkl. eigener Rückfrage und Knopfwert.
            document.addEventListener('submit', function (e) {
                const f = e.target;
                if (!(f instanceof HTMLFormElement) || f.hasAttribute('data-als-person-ende') || f.dataset.alsBestaetigt === '1') return;
                if ((f.getAttribute('method') || 'get').toLowerCase() === 'get' || f.action.endsWith('/logout')) return;
                e.preventDefault();
                e.stopImmediatePropagation();
                const knopf = e.submitter;
                frag().then(function (ja) {
                    if (!ja) return;
                    f.dataset.alsBestaetigt = '1';
                    f.requestSubmit(knopf || undefined);
                });
            }, true);

            // Auch Änderungen, die die Seite per fetch schickt.
            if (!window.__kantineAlsFetch) {
                window.__kantineAlsFetch = true;
                const original = window.fetch;
                window.fetch = function (input, init) {
                    const methode = ((init && init.method) || (input && input.method) || 'GET').toUpperCase();
                    if (methode === 'GET' || methode === 'HEAD') return original.apply(this, arguments);
                    const self = this, args = arguments;
                    return frag().then(function (ja) {
                        return ja ? original.apply(self, args) : Promise.reject(new Error('Abgebrochen: als ' + name));
                    });
                };
            }
        })();
    </script>
