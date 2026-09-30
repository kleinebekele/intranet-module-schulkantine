{{-- Preise je Vertragsgruppe aus Linear. Erwartet: $an (Menü nutzt Linear-Preis);
     optional $mitSchalter (Checkbox fürs Formular). --}}
@php
    $lp = \Intranet\Modules\Schulkantine\Support\LinearPreise::class;
    $preise = $lp::aktuell();
    $eur = fn ($v) => number_format((float) $v, 2, ',', '.').' €';
@endphp

<div class="rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
    @if ($mitSchalter ?? false)
        <input type="hidden" name="linear_price" value="0">
        <label class="inline-flex items-center gap-2 font-medium">
            <input type="checkbox" name="linear_price" value="1" @checked($an)
                   class="rounded border-gray-300 text-sky-600 focus:ring-sky-500">
            Preis aus Linear (je Vertragsgruppe)
        </label>
        <p class="mt-1 text-xs text-sky-700">Angehakt: Jeder Esser zahlt den Linear-Preis seines Vertrags. Der Preis oben gilt dann nur, wenn Linear nicht erreichbar ist.</p>
    @endif

    @if ($preise)
        <div class="{{ ($mitSchalter ?? false) ? 'mt-2' : '' }} text-xs">
            <span class="font-medium">In Linear gemeldet:</span>
            @foreach ($lp::ARTEN as $art => $name)
                <span class="whitespace-nowrap">{{ $name }} <b>{{ isset($preise[$art]) ? $eur($preise[$art]) : '–' }}</b>@if (! $loop->last) · @endif</span>
            @endforeach
            <span class="block">Nicht zuzuordnen (Fallback, teuerster Preis): <b>{{ $eur(max($preise)) }}</b></span>
        </div>
    @else
        <div class="{{ ($mitSchalter ?? false) ? 'mt-2' : '' }} text-xs text-amber-700">⚠️ Linear-Preise sind gerade nicht abrufbar – es gilt der im Menü eingetragene Preis.</div>
    @endif
</div>
