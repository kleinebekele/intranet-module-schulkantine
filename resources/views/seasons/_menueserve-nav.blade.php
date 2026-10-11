{{-- Tab-Leiste der Saison für die Menü&Serve-Seiten (Saison-Tabs als Links, darunter die
     Unter-Tabs). Erwartet $season und $unter ('menues' | 'buchungen' | 'ogs'). --}}
@php
    $tabKlasse = fn (bool $aktiv) => 'rounded-md px-3 py-1.5 font-medium '.($aktiv ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-50');
@endphp
<div class="space-y-3">
    <div class="inline-flex rounded-lg border border-gray-200 bg-white p-0.5 text-sm">
        <a href="{{ route('module.schulkantine.seasons.show', ['season' => $season, 'tab' => 'schliesstage']) }}" class="{{ $tabKlasse(false) }}">Schließtage</a>
        <a href="{{ route('module.schulkantine.seasons.show', ['season' => $season, 'tab' => 'menues']) }}" class="{{ $tabKlasse(false) }}">Menüs</a>
        <a href="{{ route('module.schulkantine.seasons.show', ['season' => $season, 'tab' => 'einstellungen']) }}" class="{{ $tabKlasse(false) }}">Einstellungen</a>
        <a href="{{ route('module.schulkantine.seasons.menueserve', ['season' => $season]) }}" class="{{ $tabKlasse(true) }}">Menü&amp;Serve</a>
    </div>
    <div class="flex flex-wrap gap-1 border-b border-gray-200 text-sm">
        @foreach (['menues' => ['seasons.menueserve', 'Menüs übernehmen'], 'buchungen' => ['seasons.menueserve.buchungen', 'Buchungen übernehmen'], 'ogs' => ['seasons.menueserve.ogs', 'OGS übernehmen']] as $key => [$route, $label])
            <a href="{{ route('module.schulkantine.'.$route, ['season' => $season]) }}"
               class="-mb-px border-b-2 px-3 py-2 font-medium {{ $unter === $key ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-gray-500 hover:text-gray-700' }}">{{ $label }}</a>
        @endforeach
    </div>
</div>
