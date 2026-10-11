<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2">
            <x-module-icon name="restaurant" class="text-2xl text-indigo-600" />
            <h1 class="text-xl font-semibold text-gray-800">OGS-Abos aus der Menü&amp;Serve-Historie</h1>
        </div>
    </x-slot>

    @php
        $namen = [1 => 'Mo', 2 => 'Di', 3 => 'Mi', 4 => 'Do', 5 => 'Fr', 6 => 'Sa', 7 => 'So'];
        $kurz = fn (array $tage, array $alle) => $tage === [] ? 'kein Abo' : (array_diff($alle, $tage) === [] ? 'alle Tage' : collect($tage)->map(fn ($d) => $namen[$d])->implode(', '));
    @endphp

    <div class="w-full space-y-5">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <form method="GET" action="{{ route('module.schulkantine.dishes.menueserve.ogs-muster') }}" class="flex items-end gap-2">
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
            <a href="{{ route('module.schulkantine.dishes.menueserve.buchungen') }}" class="text-sm text-gray-500 hover:text-gray-700">← zurück zu den Buchungen</a>
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
                $anders = collect($muster['kinder'])->filter(fn ($k) => $k['vorschlag'] !== $k['aktuell']);
            @endphp
            <form method="POST" action="{{ route('module.schulkantine.dishes.menueserve.ogs-muster.setzen') }}" class="space-y-4"
                  onsubmit="return confirm('Abos der angehakten Kinder nach den gewählten Wochentagen setzen?')">
                @csrf
                <input type="hidden" name="von" value="{{ $von->format('Y-m-d') }}">
                <input type="hidden" name="bis" value="{{ $bis->format('Y-m-d') }}">
                @foreach ($wt as $d)<input type="hidden" name="alle[]" value="{{ $d }}">@endforeach

                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
                    <div class="border-b border-gray-100 px-4 py-2 text-sm font-semibold text-gray-700">
                        {{ count($muster['kinder']) }} OGS-Kinder · Kantinentage:
                        {{ collect($muster['jeWochentag'])->map(fn ($n, $d) => $namen[$d].' '.$n)->implode(', ') }}
                        · {{ $anders->count() }} mit anderem Muster als ihr Abo (vorausgewählt)
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-100 text-left text-xs uppercase tracking-wide text-gray-400">
                                    <th class="w-8 px-4 py-2"></th>
                                    <th class="px-3 py-2 font-medium">Kind</th>
                                    @foreach ($wt as $d)
                                        <th class="px-3 py-2 text-center font-medium">{{ $namen[$d] }}</th>
                                    @endforeach
                                    <th class="px-3 py-2 font-medium">Vorschlag</th>
                                    <th class="px-3 py-2 font-medium">Abo jetzt</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($muster['kinder'] as $k)
                                    @php $id = $k['user']->id; @endphp
                                    <tr class="{{ $k['unklar'] ? 'bg-amber-50/40' : '' }}">
                                        <td class="px-4 py-1.5">
                                            <input type="checkbox" name="auswahl[]" value="{{ $id }}" @checked($k['vorschlag'] !== $k['aktuell'] && ! $k['unklar'])
                                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-1.5">
                                            <span class="font-medium text-gray-900">{{ $k['user']->name }}</span>
                                            <span class="ml-1 text-xs text-gray-400">{{ $k['tage'] }} Tage</span>
                                            @if ($k['unklar'])<span class="ml-1 text-xs font-medium text-amber-700">unklar</span>@endif
                                        </td>
                                        @foreach ($wt as $d)
                                            @php $a = $k['anteile'][$d] ?? 0; @endphp
                                            <td class="px-3 py-1.5 text-center">
                                                <label class="inline-flex items-center gap-1.5">
                                                    <input type="checkbox" name="tage[{{ $id }}][]" value="{{ $d }}" @checked(in_array($d, $k['vorschlag'], true))
                                                           class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                                    <span class="w-9 text-right text-xs tabular-nums {{ $a >= 0.75 ? 'text-gray-900' : ($a > 0.25 ? 'text-amber-700' : 'text-gray-400') }}">{{ (int) round($a * 100) }} %</span>
                                                </label>
                                            </td>
                                        @endforeach
                                        <td class="whitespace-nowrap px-3 py-1.5 text-gray-700">{{ $kurz($k['vorschlag'], $wt) }}</td>
                                        <td class="whitespace-nowrap px-3 py-1.5 {{ $k['vorschlag'] !== $k['aktuell'] ? 'text-amber-700' : 'text-gray-500' }}">{{ $k['abo'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Abos der angehakten Kinder setzen</button>
                </div>
            </form>
        @elseif (! $fehler)
            <div class="rounded-xl border border-gray-200 bg-white px-4 py-10 text-center text-sm text-gray-500">Im Zeitraum gibt es keine OGS-Buchungen in Menü&amp;Serve.</div>
        @endif
    </div>
</x-app-layout>
