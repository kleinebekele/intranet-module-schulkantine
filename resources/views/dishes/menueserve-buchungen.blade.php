<x-app-layout>
    <x-slot name="header">
        @include('schulkantine::seasons._kopf')
    </x-slot>

    @php
        $tage = collect($plan)->groupBy('tag')->sortKeys();
        $summe = fn ($feld) => collect($vorschau)->sum($feld);
        $farbe = fn ($s) => match ($s) {
            'bereit' => 'text-sky-700',
            'storniert', 'ogs' => 'text-gray-400',
            default => 'text-amber-700',
        };
    @endphp

    <div class="w-full space-y-5">
        @include('schulkantine::seasons._menueserve-nav', ['unter' => 'buchungen'])
        <div class="flex flex-wrap items-end justify-between gap-3">
            <form method="GET" action="{{ route('module.schulkantine.seasons.menueserve.buchungen') }}" class="flex items-end gap-2">
                <div>
                    <label for="ab" class="block text-xs font-medium text-gray-500">Buchungen ab</label>
                    <input id="ab" name="ab" type="date" value="{{ $ab->format('Y-m-d') }}" onchange="this.form.submit()"
                           class="mt-1 rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
            </form>
        </div>

        <p class="text-sm text-gray-500">
            Jede Buchung wird über die Linear-Nummer einer Person zugeordnet und als Bestellung für das Speiseplan-Menü
            des Tages angelegt (ohne Bestellfristen). Gezählt wird in <strong>Bestellungen</strong>: ein Menü oder ein Einzelgericht.
            <strong>„Nur übernehmen"</strong> legt an, was fehlt, und löscht nichts. <strong>„Übernehmen und löschen"</strong>
            löscht zusätzlich bei den angehakten Personen, was bei uns steht, aber nicht so in Menü&amp;Serve gebucht ist.
            Wer an einem Tag nur bei uns bestellt hat (keine Menü&amp;Serve-Buchung), ist standardmäßig nicht angehakt.
            OGS-Kinder laufen über das Abo – dafür der Tab „OGS übernehmen".
        </p>

        @if ($fehler)
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">Menü&amp;Serve ist nicht lesbar: {{ $fehler }}</div>
        @endif

        @if ($plan)
            {{-- Je Tag: was die Übernahme bewirkt. Ein Formular für alles; „Tag übernehmen" schickt
                 nur seinen Tag, „Alle Tage übernehmen" alle. Gelöscht wird nur im Modus „löschen"
                 und nur bei angehakten Personen. --}}
            <form method="POST" action="{{ route('module.schulkantine.seasons.menueserve.buchungen.uebernehmen') }}" id="ms-uebernahme"
                  x-data="{ modus: 'nur', n: {}, rechne() { const n = {}; this.$root.querySelectorAll('input[data-tag]:checked').forEach(c => { n[c.dataset.tag] = (n[c.dataset.tag] || 0) + Number(c.dataset.n); }); this.n = n; }, get gesamt() { return Object.values(this.n).reduce((a, b) => a + b, 0); } }"
                  x-init="rechne()" @change="rechne()"
                  onsubmit="return confirm('Übernahme starten? Angelegt wird, was fehlt. Gelöscht wird nur bei „Übernehmen und löschen“ und nur bei den angehakten Personen.')"
                  class="space-y-4">
                @csrf
                <input type="hidden" name="ab" value="{{ $ab->format('Y-m-d') }}">

                <div class="flex flex-wrap items-center gap-4 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm">
                    <span class="font-medium text-gray-700">Modus:</span>
                    <label class="inline-flex items-center gap-2"><input type="radio" name="modus" value="nur" x-model="modus" class="text-indigo-600"> Nur übernehmen (nichts löschen)</label>
                    <label class="inline-flex items-center gap-2"><input type="radio" name="modus" value="loeschen" x-model="modus" class="text-red-600"> Übernehmen und angehakte löschen</label>
                </div>

                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-100 text-left text-xs uppercase tracking-wide text-gray-400">
                                    <th class="px-4 py-2 font-medium">Tag</th>
                                    <th class="px-3 py-2 text-right font-medium">Buchungen</th>
                                    @foreach ($statusText as $s => $text)
                                        <th class="px-3 py-2 text-right font-medium {{ $farbe($s) }}">{{ $text }}</th>
                                    @endforeach
                                    <th class="px-3 py-2 text-right font-medium text-sky-700" title="Bestellungen (Menü bzw. Einzelgericht), die neu angelegt werden">wird angelegt</th>
                                    <th class="px-3 py-2 text-right font-medium text-gray-500" title="Steht schon genauso bei uns – bleibt">schon vorhanden</th>
                                    <th class="px-3 py-2 text-right font-medium text-red-700" title="Bestellungen der angehakten Personen, die gelöscht werden (nur im Modus „löschen“)">wird gelöscht</th>
                                    <th class="px-3 py-2"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($tage as $tag => $liste)
                                    @php
                                        $v = $vorschau[$tag] ?? ['neu' => 0, 'vorhanden' => 0, 'loeschbar' => []];
                                        $moeglich = collect($v['loeschbar'])->sum(fn ($p) => count($p['posten']));
                                    @endphp
                                    <tr>
                                        <td class="whitespace-nowrap px-4 py-2 font-medium text-gray-900">
                                            {{ \Illuminate\Support\Carbon::parse($tag)->locale('de')->isoFormat('dd DD.MM.YYYY') }}
                                            <span class="ml-1 text-xs font-normal text-gray-400">{{ $liste->pluck('titel')->filter()->unique()->implode(', ') }}</span>
                                        </td>
                                        <td class="px-3 py-2 text-right tabular-nums text-gray-700">{{ $liste->count() }}</td>
                                        @foreach ($statusText as $s => $text)
                                            @php $n = $liste->where('status', $s)->count(); @endphp
                                            <td class="px-3 py-2 text-right tabular-nums {{ $n ? $farbe($s) : 'text-gray-300' }}">{{ $n ?: '–' }}</td>
                                        @endforeach
                                        <td class="px-3 py-2 text-right font-semibold tabular-nums {{ $v['neu'] ? 'text-sky-700' : 'text-gray-300' }}">{{ $v['neu'] ?: '–' }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums {{ $v['vorhanden'] ? 'text-gray-500' : 'text-gray-300' }}">{{ $v['vorhanden'] ?: '–' }}</td>
                                        <td class="px-3 py-2 text-right font-semibold tabular-nums">
                                            <span x-show="modus === 'loeschen'" :class="(n['{{ $tag }}'] || 0) ? 'text-red-700' : 'text-gray-300'"
                                                  x-text="(n['{{ $tag }}'] || 0) || '–'"></span>
                                            <span x-show="modus !== 'loeschen'" class="font-normal text-gray-300"
                                                  title="{{ $moeglich }} Bestellungen wären löschbar">–</span>
                                        </td>
                                        <td class="px-3 py-2 text-right">
                                            @if ($v['neu'] > 0 || $moeglich > 0)
                                                <button type="submit" name="tag" value="{{ $tag }}"
                                                        class="whitespace-nowrap rounded-md border border-sky-300 bg-white px-2 py-1 text-xs font-medium text-sky-800 hover:bg-sky-50">Tag übernehmen</button>
                                            @endif
                                        </td>
                                    </tr>
                                    @if ($v['loeschbar'])
                                        {{-- Wer würde gelöscht? Je Person anhakbar. --}}
                                        <tr x-show="modus === 'loeschen'" x-cloak class="bg-red-50/40">
                                            <td colspan="{{ 6 + count($statusText) }}" class="px-4 py-2">
                                                <div class="mb-1 text-xs font-medium uppercase tracking-wide text-red-700">Löschen am {{ \Illuminate\Support\Carbon::parse($tag)->format('d.m.') }} – Personen anhaken</div>
                                                <div class="grid gap-x-6 gap-y-1 sm:grid-cols-2 xl:grid-cols-3">
                                                    @foreach ($v['loeschbar'] as $p)
                                                        <label class="flex items-start gap-2 text-sm">
                                                            <input type="checkbox" name="loeschen[{{ $tag }}][]" value="{{ $p['user_id'] }}"
                                                                   data-tag="{{ $tag }}" data-n="{{ count($p['posten']) }}" @checked($p['inMs'])
                                                                   class="mt-0.5 rounded border-gray-300 text-red-600 focus:ring-red-500">
                                                            <span>
                                                                <span class="font-medium text-gray-900">{{ $p['name'] }}</span>
                                                                <span class="text-gray-500">– {{ implode(', ', $p['posten']) }}</span>
                                                                @if (! $p['inMs'])
                                                                    <span class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-800">nur bei uns bestellt</span>
                                                                @else
                                                                    <span class="ml-1 rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-semibold text-gray-600">in Menü&amp;Serve anders gebucht</span>
                                                                @endif
                                                            </span>
                                                        </label>
                                                    @endforeach
                                                </div>
                                            </td>
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-end gap-4">
                    <span class="text-sm text-gray-600">
                        Insgesamt: <strong class="text-sky-700">{{ $summe('neu') }}</strong> anlegen ·
                        <strong>{{ $summe('vorhanden') }}</strong> bleiben ·
                        <strong class="text-red-700" x-text="modus === 'loeschen' ? gesamt : 0"></strong> löschen
                    </span>
                    <button type="submit"
                            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                        Alle Tage übernehmen
                    </button>
                </div>
            </form>

            {{-- Unbekannte Konten: wer ist das? --}}
            @if ($unbekannt)
                <div class="overflow-hidden rounded-xl border border-amber-200 bg-white">
                    <div class="border-b border-amber-100 bg-amber-50 px-4 py-2 text-sm font-medium text-amber-800">Bei uns unbekannt – {{ count($unbekannt) }} Konten</div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-100 text-left text-xs uppercase tracking-wide text-gray-400">
                                    <th class="px-4 py-2 font-medium">Konto in Menü&amp;Serve</th>
                                    <th class="px-3 py-2 font-medium">Linear</th>
                                    <th class="px-3 py-2 text-right font-medium">Buchungen</th>
                                    <th class="px-3 py-2 font-medium">Warum unbekannt</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($unbekannt as $u)
                                    <tr>
                                        <td class="px-4 py-2">
                                            <div class="font-medium text-gray-900">{{ $u['name'] !== '' ? $u['name'] : '(ohne Namen)' }}</div>
                                            @if ($u['kartenart'])<div class="text-xs text-gray-400">{{ $u['kartenart'] }}</div>@endif
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-2 text-gray-600">
                                            {{ $u['adrnr'] !== '' ? $u['adrnr'] : '–' }}@if ($u['linear']) · {{ $u['linear'] }}@endif
                                        </td>
                                        <td class="px-3 py-2 text-right tabular-nums text-gray-600"
                                            title="{{ collect($u['tage'])->map(fn ($t) => \Illuminate\Support\Carbon::parse($t)->format('d.m.'))->implode(', ') }}">{{ $u['buchungen'] }}</td>
                                        <td class="px-3 py-2 text-amber-700">{{ $u['hinweis'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            {{-- Was sonst nicht geht – damit man es nachziehen kann --}}
            @php $probleme = collect($plan)->whereIn('status', ['zuordnung', 'speiseplan']); @endphp
            @if ($probleme->isNotEmpty())
                <div class="overflow-hidden rounded-xl border border-amber-200 bg-white">
                    <div class="border-b border-amber-100 bg-amber-50 px-4 py-2 text-sm font-medium text-amber-800">Nicht übernehmbar – {{ $probleme->count() }} Buchungen</div>
                    <ul class="divide-y divide-gray-100 text-sm">
                        @foreach ($probleme->sortBy('tag') as $b)
                            <li class="flex flex-wrap gap-x-3 px-4 py-1.5">
                                <span class="w-24 text-gray-500">{{ \Illuminate\Support\Carbon::parse($b['tag'])->format('d.m.Y') }}</span>
                                <span class="w-56 font-medium text-gray-900">{{ $b['user']?->name ?? 'Linear-Nr. '.$b['adrnr'] }}</span>
                                <span class="text-amber-700">{{ $statusText[$b['status']] }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

        @elseif (! $fehler)
            <div class="rounded-xl border border-gray-200 bg-white px-4 py-10 text-center text-sm text-gray-500">Ab {{ $ab->format('d.m.Y') }} gibt es in Menü&amp;Serve keine Buchungen.</div>
        @endif
    </div>
</x-app-layout>
