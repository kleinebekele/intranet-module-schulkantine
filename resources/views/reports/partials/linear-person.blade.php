{{-- Linear-Stand EINER Person: $lz = Zeile/Ausschluss aus der Vorschau, $z = [Text, Farbe, Tooltip] --}}
@if (! $z)
    <span class="text-gray-300">–</span>
@else
    <div class="whitespace-nowrap {{ $z[1] }}" title="{{ $z[2] }}">
        {{ $z[0] }}@if (isset($lz['grund'])): {{ $lz['grund'] }}@endif
    </div>
    @if (! isset($lz['grund']) && $lz['export'])
        <div class="whitespace-nowrap text-gray-400" title="{{ $lz['export']->hinweis }}">
            am {{ \Illuminate\Support\Carbon::parse($lz['export']->sent_at)->format('d.m.Y H:i') }}
        </div>
        @if (round((float) $lz['export']->betrag, 2) !== round((float) $lz['Betrag'], 2))
            <div class="whitespace-nowrap font-medium text-amber-700">⚠️ gesendet {{ $euro($lz['export']->betrag) }}, jetzt {{ $euro($lz['Betrag']) }}</div>
        @endif
    @endif
@endif
