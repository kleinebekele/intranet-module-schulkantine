{{-- Alle kostenpflichtigen Posten eines Essers in einem Monat (Detail-Dialog).
     Erwartet $z (Zelle aus HaushaltsAbrechnung), $euro. --}}
@php $posten = \Intranet\Modules\Schulkantine\Support\HaushaltsAbrechnung::posten($z); @endphp

@if ($z['details'])
    @if ($posten === [])
        <p class="py-3 text-sm text-gray-500">In diesem Monat ist nichts angefallen.</p>
    @else
        <table class="w-full text-sm">
            <tbody class="divide-y divide-gray-100">
                @foreach ($posten as $p)
                    <tr>
                        <td class="whitespace-nowrap py-1.5 pr-3 text-gray-500">{{ $p['datum']->locale('de')->isoFormat('dd DD.MM.') }}</td>
                        <td class="py-1.5 pr-3 text-gray-800">
                            {{ $p['was'] }}
                            @if ($p['hinweis'])
                                <span class="ml-1 text-xs {{ str_starts_with($p['hinweis'], 'No-Show') ? 'font-medium text-rose-600' : 'text-gray-400' }}">{{ $p['hinweis'] }}</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap py-1.5 text-right tabular-nums text-gray-800">{{ $euro($p['betrag']) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="border-t border-gray-200">
                    <td colspan="2" class="pt-2 font-semibold text-gray-700">Summe</td>
                    <td class="pt-2 text-right font-bold tabular-nums text-gray-900">{{ $euro($z['betrag']) }}</td>
                </tr>
            </tfoot>
        </table>
    @endif
@elseif ($z['quelle'] === 'linear')
    <p class="py-3 text-sm text-gray-600">
        Abgerechnet im alten System: <span class="font-semibold">{{ $euro($z['betrag']) }}</span>.<br>
        <span class="text-gray-400">Einzelposten liegen für diesen Monat nicht vor – aus dem alten System haben wir nur die Abrechnungsbeträge übernommen.</span>
    </p>
@else
    <p class="py-3 text-sm text-gray-400">Für diesen Monat liegen keine Daten vor.</p>
@endif
