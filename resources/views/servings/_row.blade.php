{{-- Eine Ausgabe-Zeile (Esser + Gerichte + Abhaken). Erwartet: $row, $date, $canServe.
     Optional: $search=true → Zeile per Alpine-Namensfilter der Elternliste ein-/ausblendbar. --}}
<div class="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between {{ $row['served'] ? 'bg-green-50/50' : 'even:bg-gray-50/70' }}"
     @if ($search ?? false) x-show="match(@js(\Illuminate\Support\Str::lower($row['user']->name)))" x-cloak @endif>

    <div class="min-w-0">
        <div class="flex flex-wrap items-center gap-2">
            <span class="font-semibold text-gray-800">{{ $row['user']->name }}</span>
            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500">{{ $row['group'] }}</span>
            @if ($row['warn'])
                <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700">⚠️ Verträglichkeiten prüfen</span>
            @endif
        </div>

        {{-- Bestellte Gerichte (Menü) inkl. Warnungen --}}
        @if ($row['dishes']->isNotEmpty())
            <div class="mt-1 flex flex-wrap gap-1.5">
                @foreach ($row['dishes'] as $d)
                    <span class="inline-flex flex-col gap-0.5 rounded-md border px-2 py-0.5 text-sm
                                 {{ ($d['allergenHits'] || $d['dietHits']) ? 'border-red-300 bg-red-50 text-red-800' : 'border-gray-200 bg-gray-50 text-gray-700' }}">
                        <span class="inline-flex items-center gap-1">
                            {{ $d['dish']?->name ?? '—' }}
                            @if ($d['allergenHits'] || $d['dietHits'])
                                <span class="text-xs font-medium">⚠️ {{ implode(', ', array_merge($d['allergenHits'], $d['dietHits'])) }}</span>
                            @endif
                        </span>
                        @if (! empty($d['components']))
                            {{-- Sparmenü: Das Personal gibt die Bestandteile aus, nicht „ein Sparmenü".
                                 Der Treffer wird je Bestandteil markiert – „welches soll ich weglassen?" --}}
                            <span class="flex flex-wrap items-center gap-1 text-xs">
                                @foreach ($d['components'] as $c)
                                    <span class="inline-flex items-center gap-0.5 rounded px-1 {{ $c['hits'] ? 'bg-red-600 font-semibold text-white' : 'bg-white/70 text-gray-500' }}">
                                        {{ $c['name'] }}@if ($c['hits'])<span class="font-normal"> ⚠️ {{ implode(', ', $c['hits']) }}</span>@endif
                                    </span>
                                    @if (! $loop->last)<span class="text-gray-400">+</span>@endif
                                @endforeach
                            </span>
                        @endif
                    </span>
                @endforeach
            </div>
        @else
            <div class="mt-1 text-xs text-gray-400">OGS-Essen (ja/nein)</div>
        @endif

        {{-- Sonderkost des Essers (Info) --}}
        @if ($row['allergens'] || $row['diets'])
            <div class="mt-1 text-xs text-gray-400">
                @if ($row['allergens']) Allergien: {{ implode(', ', $row['allergens']) }}. @endif
                @if ($row['diets']) Diäten: {{ implode(', ', $row['diets']) }}. @endif
            </div>
        @endif
    </div>

    {{-- Aktionen je Esser --}}
    <div class="flex shrink-0 items-center gap-2">
        @if ($canServe)
            {{-- „öffnen" simuliert das Vorhalten des Chips → Modal (nur Menü-Esser). --}}
            @if ($row['dishes']->isNotEmpty())
                <button type="button"
                        onclick="window.dispatchEvent(new CustomEvent('kantine-open-eater', { detail: {{ $row['user']->id }} }))"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-indigo-300 bg-white px-3 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-50">
                    öffnen
                </button>
            @endif
            <form method="POST" action="{{ route('module.schulkantine.servings.toggle') }}">
                @csrf
                <input type="hidden" name="eater_id" value="{{ $row['user']->id }}">
                <input type="hidden" name="date" value="{{ $date->toDateString() }}">
                <button type="submit"
                        class="inline-flex items-center gap-1.5 rounded-lg border px-3 py-2 text-sm font-medium transition
                               {{ $row['served']
                                    ? 'border-green-300 bg-green-100 text-green-800 hover:bg-green-200'
                                    : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50' }}">
                    @if ($row['served'])
                        ✓ ausgegeben – zurücknehmen
                    @else
                        abhaken
                    @endif
                </button>
            </form>
        @else
            <span class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium
                         {{ $row['served'] ? 'text-green-700' : 'text-gray-400' }}">
                {{ $row['served'] ? '✓ ausgegeben' : 'offen' }}
            </span>
        @endif
    </div>
</div>
