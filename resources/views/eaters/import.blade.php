<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h1 class="text-xl font-semibold text-gray-800">Teilnehmer importieren</h1>
            <a href="{{ route('module.schulkantine.eaters.index') }}"
               class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700">
                <x-module-icon name="back" class="text-base" />
                Zurück
            </a>
        </div>
    </x-slot>

    <div class="max-w-2xl space-y-6">
        {{-- Anleitung --}}
        <div class="rounded-xl border border-gray-200 bg-white p-6 text-sm text-gray-600">
            <h2 class="text-base font-semibold text-gray-800">CSV-Format</h2>
            <p class="mt-2">Gedacht für Schüler <strong>ohne eigenen Intranet-Account</strong>. Erste Zeile = Spaltenüberschriften (Reihenfolge egal, Komma oder Semikolon):</p>
            <pre class="mt-3 overflow-x-auto rounded-lg bg-gray-50 p-3 text-xs text-gray-700">external_id,name,group,parent1,parent2,parent3,parent4</pre>
            <ul class="mt-3 list-disc space-y-1 pl-5">
                <li><strong>external_id</strong> – eindeutiger Schlüssel (z. B. Schüler-Nr.). Gleiche ID erneut = Aktualisierung statt Dublette.</li>
                <li><strong>name</strong> – Name des Kindes.</li>
                <li><strong>group</strong> – Name einer bestehenden Kundengruppe; wird der <strong>aktiven Saison</strong> zugeordnet.</li>
                <li><strong>parent1–4</strong> – Eltern als Benutzer, erkannt per <strong>E-Mail</strong> (mit „@") oder <strong>exaktem Namen</strong>.</li>
            </ul>
            @if ($activeSeason)
                <p class="mt-3 text-xs text-gray-400">Aktive Saison: „{{ $activeSeason->name }}" – Gruppen werden hierfür gesetzt.</p>
            @else
                <p class="mt-3 text-xs text-amber-600">Achtung: keine aktive Saison – Gruppen-Zuordnungen werden übersprungen.</p>
            @endif
        </div>

        {{-- Upload --}}
        <form method="POST" action="{{ route('module.schulkantine.eaters.import') }}"
              enctype="multipart/form-data"
              class="rounded-xl border border-gray-200 bg-white p-6">
            @csrf
            <x-input-label for="file" value="CSV-Datei" />
            <input id="file" name="file" type="file" accept=".csv,text/csv,text/plain" required
                   class="mt-2 block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100">
            <x-input-error :messages="$errors->get('file')" class="mt-2" />

            <div class="mt-4">
                <x-primary-button class="gap-1.5">
                    <x-module-icon name="download" class="text-base" />
                    Importieren
                </x-primary-button>
            </div>
        </form>

        {{-- Ergebnis --}}
        @if ($result)
            <div class="rounded-xl border border-gray-200 bg-white p-6">
                <h2 class="text-base font-semibold text-gray-800">Ergebnis</h2>
                <div class="mt-3 flex flex-wrap gap-2 text-sm">
                    <span class="inline-flex rounded-full bg-green-50 px-3 py-1 font-medium text-green-700">{{ $result['created'] }} neu</span>
                    <span class="inline-flex rounded-full bg-indigo-50 px-3 py-1 font-medium text-indigo-700">{{ $result['updated'] }} aktualisiert</span>
                    <span class="inline-flex rounded-full bg-gray-100 px-3 py-1 font-medium text-gray-600">{{ $result['skipped'] }} übersprungen</span>
                    <span class="inline-flex rounded-full bg-amber-50 px-3 py-1 font-medium text-amber-700">{{ count($result['warnings']) }} Hinweise</span>
                </div>

                @if (! empty($result['warnings']))
                    <ul class="mt-4 max-h-64 space-y-1 overflow-y-auto rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">
                        @foreach ($result['warnings'] as $warning)
                            <li>{{ $warning }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif
    </div>
</x-app-layout>
