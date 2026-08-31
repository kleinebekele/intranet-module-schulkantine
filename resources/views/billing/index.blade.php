<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2">
            <x-module-icon name="tag" class="text-2xl text-indigo-600" />
            <h1 class="text-xl font-semibold text-gray-800">Meine Abrechnung</h1>
        </div>
    </x-slot>

    @php
        $euro = fn ($v) => number_format((float) $v, 2, ',', '.').' €';
    @endphp

    <div class="max-w-full">
        @if (! $season)
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Es ist keine Saison als „aktiv" markiert.
            </div>
        @else
            {{-- Monat wählen + Haushalts-Summe --}}
            <div class="mb-4 flex flex-wrap items-end justify-between gap-4">
                <form method="GET" action="{{ route('module.schulkantine.abrechnung.index') }}" class="flex items-end gap-2">
                    <div>
                        <label for="monat" class="block text-xs font-medium text-gray-500">Monat</label>
                        <select id="monat" name="monat" onchange="this.form.submit()"
                                class="mt-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach ($months as $m)
                                <option value="{{ $m['value'] }}" @selected($m['value'] === $monthValue)>{{ $m['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <noscript><button type="submit" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white">Anzeigen</button></noscript>
                </form>

                <div class="rounded-xl border-2 border-indigo-200 bg-indigo-50 px-5 py-3 text-right">
                    <div class="text-xs uppercase tracking-wide text-indigo-500">Haushalt gesamt · {{ $monthLabel }}</div>
                    <div class="text-2xl font-bold text-indigo-800">{{ $euro($total) }}</div>
                </div>
            </div>

            {{-- Personen des Haushalts --}}
            @if (empty($members))
                <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-6 text-center text-sm text-gray-500">
                    Für diesen Haushalt gibt es keine Personen.
                </div>
            @else
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($members as $m)
                        <div class="flex flex-col rounded-xl border border-gray-200 bg-white p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="truncate font-semibold text-gray-800">{{ $m['user']->name }}</div>
                                    @if ($m['group'])
                                        <span class="mt-0.5 inline-block rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500">{{ $m['group'] }}</span>
                                    @endif
                                </div>
                                <div class="text-right">
                                    <div class="text-lg font-bold text-gray-900">{{ $euro($m['total']) }}</div>
                                </div>
                            </div>

                            @if ($m['total'] == 0.0 && $m['menu_total'] == 0.0 && $m['ogs_total'] == 0.0 && $m['spontan_total'] == 0.0 && $m['pfand_net'] == 0.0)
                                <p class="mt-3 text-sm text-gray-400">Keine Kosten in diesem Monat.</p>
                            @else
                                <dl class="mt-3 space-y-1 text-sm">
                                    @if ($m['menu_total'] != 0.0)
                                        <div class="flex justify-between"><dt class="text-gray-500">Menü-Vorbestellungen</dt><dd class="tabular-nums text-gray-700">{{ $euro($m['menu_total']) }}</dd></div>
                                    @endif
                                    @if ($m['ogs_total'] != 0.0)
                                        <div class="flex justify-between"><dt class="text-gray-500">OGS-Essen · {{ $m['ogs_days'] }} Tage</dt><dd class="tabular-nums text-gray-700">{{ $euro($m['ogs_total']) }}</dd></div>
                                    @endif
                                    @if ($m['spontan_total'] != 0.0)
                                        <div class="flex justify-between"><dt class="text-gray-500">Spontane Abholungen</dt><dd class="tabular-nums text-gray-700">{{ $euro($m['spontan_total']) }}</dd></div>
                                    @endif
                                    @if ($m['pfand_net'] != 0.0)
                                        <div class="flex justify-between"><dt class="text-gray-500">Chip-Pfand</dt><dd class="tabular-nums {{ $m['pfand_net'] < 0 ? 'text-green-600' : 'text-gray-700' }}">{{ ($m['pfand_net'] > 0 ? '+' : '').$euro($m['pfand_net']) }}</dd></div>
                                    @endif
                                </dl>
                            @endif

                            <a href="{{ route('module.schulkantine.abrechnung.show', ['user' => $m['user']->id, 'monat' => $monthValue]) }}"
                               class="mt-4 inline-flex items-center gap-1 self-start text-sm font-medium text-indigo-600 hover:text-indigo-800">
                                Einzelposten ansehen <span aria-hidden="true">›</span>
                            </a>
                        </div>
                    @endforeach
                </div>
            @endif

            <p class="mt-6 text-xs text-gray-400">
                Grundlage: verbindliche Vorbestellungen (rechtzeitig storniert = nicht berechnet, No-Shows zahlen trotzdem),
                spontane Abholungen und Chip-Pfand. Die eigentliche Zahlung läuft extern.
            </p>
        @endif
    </div>
</x-app-layout>
