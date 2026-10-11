<x-app-layout>
    <x-slot name="header">
        @include('schulkantine::seasons._kopf')
    </x-slot>

    @php
        $namen = [1 => 'Mo', 2 => 'Di', 3 => 'Mi', 4 => 'Do', 5 => 'Fr', 6 => 'Sa', 7 => 'So'];
        $kurz = fn (array $tage, array $alle) => $tage === [] ? 'kein Abo' : (array_diff($alle, $tage) === [] ? 'alle Tage' : collect($tage)->map(fn ($d) => $namen[$d])->implode(', '));
    @endphp

    <div class="w-full space-y-5">
        @include('schulkantine::seasons._menueserve-nav', ['unter' => 'ogs'])
        <h2 class="text-base font-semibold text-gray-800">OGS-Abos aus der Menü&amp;Serve-Historie</h2>
        <div class="flex flex-wrap items-end justify-between gap-3">
            <form method="GET" action="{{ route('module.schulkantine.seasons.menueserve.ogs') }}" class="flex items-end gap-2">
                <input type="hidden" name="ab" value="{{ $ab->format('Y-m-d') }}">
                <div>
                    <label for="von" class="block text-xs font-medium text-gray-500">von</label>
                    <input id="von" name="von" type="date" value="{{ $von->format('Y-m-d') }}" onchange="this.form.submit()"
                           class="mt-1 rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div>
                    <label for="bis" class="block text-xs font-medium text-gray-500">bis</label>
                    <input id="bis" name="bis" type="date" value="{{ $bis->format('Y-m-d') }}" onchange="this.form.submit()"
                           class="mt-1 rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
            </form>
        </div>

        <p class="text-sm text-gray-500">
            Je OGS-Kind und Wochentag: an wie viel Prozent der Kantinentage es in Menü&amp;Serve gebucht war.
            Vorgeschlagen sind die Wochentage ab {{ (int) ($schwelle * 100) }} %; <span class="text-amber-700">unklar</span> heißt,
            mindestens ein Wochentag liegt zwischen 25 % und 75 %. Die Kästchen lassen sich ändern; gesetzt wird nur bei angehakten Kindern.
        </p>

        @if ($fehler)
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">Menü&amp;Serve ist nicht lesbar: {{ $fehler }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
        @endif

        @if ($muster && $muster['kinder'])
            @php
                $wt = $muster['wochentage'];
                // Erledigt = Abo passt zum eindeutigen Muster, oder bei unklaren Kindern: es ist ein
                // Abo gesetzt, das nicht „alle Tage" ist (das legte bis 10/2026 die Bestellseite
                // automatisch an – es sagt also nichts darüber, ob jemand es bewusst gewählt hat).
                $erledigt = fn ($k) => $k['unklar']
                    ? $k['aktuell'] !== [] && array_diff($wt, $k['aktuell']) !== []
                    : $k['vorschlag'] === $k['aktuell'];
                $offen = collect($muster['kinder'])->reject($erledigt)->values();
                $fertig = collect($muster['kinder'])->filter($erledigt)->values();
            @endphp
            <form method="POST" action="{{ route('module.schulkantine.seasons.menueserve.ogs.abos') }}" class="space-y-4"
                  onsubmit="return confirm('Abos der angehakten Kinder nach den gewählten Wochentagen setzen?')">
                @csrf
                <input type="hidden" name="von" value="{{ $von->format('Y-m-d') }}">
                <input type="hidden" name="bis" value="{{ $bis->format('Y-m-d') }}">
                @foreach ($wt as $d)<input type="hidden" name="alle[]" value="{{ $d }}">@endforeach

                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
                    <div class="border-b border-gray-100 px-4 py-2 text-sm font-semibold text-gray-700">
                        Offen – {{ $offen->count() }} von {{ count($muster['kinder']) }} OGS-Kindern · Kantinentage:
                        {{ collect($muster['jeWochentag'])->map(fn ($n, $d) => $namen[$d].' '.$n)->implode(', ') }}
                    </div>
                    @if ($offen->isNotEmpty())
                        @include('schulkantine::dishes.partials.ogs-muster-tabelle', ['kinder' => $offen, 'erledigt' => false])
                    @else
                        <div class="px-4 py-6 text-center text-sm text-gray-500">Alle Abos sind gesetzt.</div>
                    @endif
                </div>

                @if ($fertig->isNotEmpty())
                    <details class="overflow-hidden rounded-xl border border-gray-200 bg-white">
                        <summary class="cursor-pointer select-none px-4 py-2 text-sm font-semibold text-gray-700">
                            Bereits übernommen – {{ $fertig->count() }} Kinder (aufklappen zum Ändern: Wochentage anpassen, links anhaken, setzen)
                        </summary>
                        <div class="border-t border-gray-100">
                            @include('schulkantine::dishes.partials.ogs-muster-tabelle', ['kinder' => $fertig, 'erledigt' => true])
                        </div>
                    </details>
                @endif

                <div class="flex justify-end">
                    <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Abos der angehakten Kinder setzen</button>
                </div>
            </form>
        @elseif (! $fehler)
            <div class="rounded-xl border border-gray-200 bg-white px-4 py-10 text-center text-sm text-gray-500">Im Zeitraum gibt es keine OGS-Buchungen in Menü&amp;Serve.</div>
        @endif

        <div class="space-y-3 border-t border-gray-200 pt-5">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <h2 class="text-base font-semibold text-gray-800">Tageweiser Abgleich mit den Menü&amp;Serve-Buchungen</h2>
                <form method="GET" action="{{ route('module.schulkantine.seasons.menueserve.ogs') }}" class="flex items-end gap-2">
                    <input type="hidden" name="von" value="{{ $von->format('Y-m-d') }}">
                    <input type="hidden" name="bis" value="{{ $bis->format('Y-m-d') }}">
                    <div>
                        <label for="ab" class="block text-xs font-medium text-gray-500">Buchungen ab</label>
                        <input id="ab" name="ab" type="date" value="{{ $ab->format('Y-m-d') }}" onchange="this.form.submit()"
                               class="mt-1 rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                </form>
            </div>
            @if ($ogs && $ogs['kinder'])
                @include('schulkantine::dishes.partials.ogs-abgleich')
            @elseif (! $fehler)
                <div class="rounded-xl border border-gray-200 bg-white px-4 py-6 text-center text-sm text-gray-500">Keine OGS-Buchungen in Menü&amp;Serve ab {{ $ab->format('d.m.Y') }}.</div>
            @endif
        </div>
    </div>
</x-app-layout>
