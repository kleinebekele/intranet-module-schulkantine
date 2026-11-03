<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2">
            <x-module-icon name="restaurant" class="text-2xl text-indigo-600" />
            <h1 class="text-xl font-semibold text-gray-800">Ausgabe – Tagesprotokoll</h1>
        </div>
    </x-slot>

    @php
        $artText = ['bestellt' => 'Angemeldet / bestellt', 'abbestellt' => 'Abbestellt / entfernt', 'ausgabe' => 'Ausgabe', 'zurueck' => 'Zurückgenommen'];
        $artFarbe = [
            'bestellt' => 'bg-indigo-100 text-indigo-800',
            'abbestellt' => 'bg-gray-200 text-gray-700',
            'ausgabe' => 'bg-green-100 text-green-800',
            'zurueck' => 'bg-red-100 text-red-700',
        ];
    @endphp

    <div class="max-w-full space-y-6">
        {{-- Tages-Navigation --}}
        <div class="flex items-center justify-between gap-4">
            <div class="flex items-center gap-2">
                <a href="{{ route('module.schulkantine.servings.index', ['date' => $date->toDateString()]) }}#tag-{{ $date->toDateString() }}"
                   class="inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">‹ Zur Übersicht</a>
                @if ($prevDate)
                    <a href="{{ route('module.schulkantine.servings.protokoll', ['date' => $prevDate]) }}"
                       class="inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">‹ Vortag</a>
                @endif
            </div>
            <div class="text-center">
                <div class="text-2xl font-bold text-gray-800">{{ ucfirst($date->isoFormat('dddd')) }}, {{ $date->format('d.m.Y') }}</div>
                <div class="text-xs text-gray-400">Saison „{{ $season->name }}"</div>
            </div>
            <div>
                @if ($nextDate)
                    <a href="{{ route('module.schulkantine.servings.protokoll', ['date' => $nextDate]) }}"
                       class="inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Folgetag ›</a>
                @endif
            </div>
        </div>

        @foreach (['ausgabe', 'ogs'] as $feld)
            @error($feld)
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700">{{ $message }}</div>
            @enderror
        @endforeach

        {{-- OGS-Kinder, die in der Übersicht als „spontan" zählen --}}
        @if ($ogsSpontan->isNotEmpty())
            <div class="overflow-hidden rounded-xl border border-amber-200 bg-white">
                <div class="border-b border-amber-100 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-800">
                    OGS spontan ({{ $ogsSpontan->count() }}) – ohne Vorbestellung, erst an der Ausgabe angemeldet
                </div>
                <ul class="divide-y divide-gray-100 text-sm">
                    @foreach ($ogsSpontan as $e)
                        <li class="flex flex-wrap items-center gap-x-4 gap-y-1 px-4 py-2.5">
                            <span class="w-12 shrink-0 text-xs text-gray-500">{{ $e['klasse'] ?? '–' }}</span>
                            <span class="font-medium text-gray-800">{{ $e['sortName'] }}</span>
                            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">{{ $e['grund'] }}</span>
                            <span class="text-gray-500">{{ $e['served'] ? 'abgeholt' : 'nicht abgeholt' }} · {{ $e['herkunft'] }}</span>
                            @if ($darfOgsEntfernen && ! $abgerechnet($e['user']->id))
                                <form method="POST" action="{{ route('module.schulkantine.servings.ogs.remove') }}" class="ml-auto"
                                      data-bestaetigen="{{ $e['sortName'] }} am {{ $date->format('d.m.Y') }} von der OGS-Liste nehmen?{{ $e['served'] ? ' Die Ausgabe wird mit zurückgenommen.' : '' }} Der Tag wird dann nicht berechnet."
                                      data-titel="OGS-Anmeldung entfernen" data-knopf="Entfernen">
                                    @csrf
                                    <input type="hidden" name="date" value="{{ $date->toDateString() }}">
                                    <input type="hidden" name="eater_id" value="{{ $e['user']->id }}">
                                    <input type="hidden" name="ziel" value="protokoll">
                                    <button type="submit" class="rounded-md border border-red-200 px-2.5 py-1 text-xs font-medium text-red-700 hover:bg-red-50">Anmeldung entfernen</button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Chronik --}}
        <div x-data="{ q: '', art: '' }" class="overflow-hidden rounded-xl border border-gray-200 bg-white">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 bg-gray-50 px-4 py-2">
                <span class="text-sm font-semibold text-gray-700">Wer hat wann was gebucht <span class="font-normal text-gray-400">(neueste zuerst)</span></span>
                <div class="flex flex-wrap items-center gap-2">
                    <div class="inline-flex rounded-lg border border-gray-200 bg-white p-0.5 text-xs">
                        <button type="button" @click="art = ''" :class="art === '' ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-50'" class="rounded-md px-2.5 py-1 font-medium">Alle</button>
                        @foreach ($artText as $k => $t)
                            <button type="button" @click="art = @js($k)" :class="art === @js($k) ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-50'" class="rounded-md px-2.5 py-1 font-medium">{{ $t }}</button>
                        @endforeach
                    </div>
                    <input type="search" x-model="q" placeholder="Name …"
                           class="w-48 rounded-lg border-gray-300 py-1 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
            </div>

            @if ($eintraege->isEmpty())
                <p class="px-4 py-6 text-center text-sm text-gray-500">Für diesen Tag ist nichts gebucht.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 text-left text-xs uppercase tracking-wide text-gray-400">
                                <th class="px-4 py-2 font-medium">Zeit</th>
                                <th class="px-3 py-2 font-medium">Art</th>
                                <th class="px-3 py-2 font-medium">Für</th>
                                <th class="px-3 py-2 font-medium">Was</th>
                                <th class="px-3 py-2 font-medium">Von</th>
                                <th class="px-3 py-2 font-medium">Wo</th>
                                <th class="px-3 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach ($eintraege as $e)
                                <tr x-show="(art === '' || art === @js($e['art'])) && (q.trim() === '' || @js(mb_strtolower($e['person'].' '.$e['wer'])).includes(q.trim().toLowerCase()))">
                                    <td class="whitespace-nowrap px-4 py-2 text-gray-600">
                                        {{ $e['zeit']?->isSameDay($date) ? $e['zeit']->format('H:i:s') : $e['zeit']?->format('d.m. H:i') }}
                                    </td>
                                    <td class="px-3 py-2">
                                        <span class="whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium {{ $artFarbe[$e['art']] }}">{{ $artText[$e['art']] }}</span>
                                    </td>
                                    <td class="px-3 py-2">
                                        <span class="font-medium text-gray-800">{{ $e['person'] }}</span>
                                        @if ($e['klasse'])<span class="ml-1 text-xs text-gray-400">{{ $e['klasse'] }}</span>@endif
                                    </td>
                                    <td class="px-3 py-2 text-gray-700">
                                        {{ $e['was'] }}
                                        @if ($e['zusatz'])<div class="text-xs text-gray-400">ursprünglich {{ $e['zusatz'] }}</div>@endif
                                    </td>
                                    <td class="px-3 py-2 text-gray-600">{{ $e['wer'] }}</td>
                                    <td class="px-3 py-2 text-xs text-gray-400">{{ $e['quelle'] }}</td>
                                    <td class="px-3 py-2 text-right">
                                        @if ($e['serving'] && $darfZuruecknehmen)
                                            @if ($abgerechnet($e['serving']->user_id))
                                                <span class="text-xs text-gray-400" title="Monat ist an Linear übertragen">abgerechnet</span>
                                            @else
                                                <form method="POST" action="{{ route('module.schulkantine.servings.destroy', $e['serving']) }}"
                                                      data-bestaetigen="{{ $e['was'] }} für {{ $e['person'] }} zurücknehmen?"
                                                      data-titel="Ausgabe zurücknehmen" data-knopf="Zurücknehmen">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="whitespace-nowrap rounded-md border border-red-200 px-2.5 py-1 text-xs font-medium text-red-700 hover:bg-red-50">Zurücknehmen</button>
                                                </form>
                                            @endif
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <p class="text-xs text-gray-400">
            Ausgabe-Zeilen zeigen den Stand der letzten Buchung am Terminal (wer, wann). Bestellungen, Abbestellungen und Rücknahmen
            stammen aus dem Audit-Log. „Zurücknehmen" löscht nur die Ausgabe – die Bestellung bleibt bestehen; eine erst an der
            Ausgabe angelegte OGS-Anmeldung entfernt der Knopf „Anmeldung entfernen" oben. Was Eltern vorab gebucht haben, ist
            vorbestellt – auch außerhalb des Abos.
        </p>
    </div>
</x-app-layout>
