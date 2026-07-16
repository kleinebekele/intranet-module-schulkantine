<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2">
            <x-module-icon name="chart" class="text-2xl text-indigo-600" />
            <h1 class="text-xl font-semibold text-gray-800">Abrechnung · {{ $user->name }}</h1>
        </div>
    </x-slot>

    @php
        $euro = fn ($v) => number_format((float) $v, 2, ',', '.').' €';
        $day = fn ($d) => \Illuminate\Support\Carbon::parse($d)->isoFormat('dd, DD.MM.YYYY');
    @endphp

    <div class="max-w-3xl space-y-5">
        {{-- Kopf: zurück + Kontext --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('module.schulkantine.reports.index', ['monat' => $monthValue]) }}"
               class="inline-flex items-center gap-1 text-sm font-medium text-indigo-600 hover:text-indigo-800">
                <x-module-icon name="back" class="text-base" /> zurück zur Auswertung
            </a>
            <span class="text-xs text-gray-400">{{ $monthLabel }} · Saison „{{ $season->name }}"</span>
        </div>

        {{-- Personen-Karte --}}
        <div class="rounded-xl border border-gray-200 bg-white p-5">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <div class="text-lg font-semibold text-gray-800">{{ $user->name }}</div>
                    <div class="mt-0.5 text-sm text-gray-500">
                        {{ $group?->name ?? 'Ohne Gruppe' }}
                        @if ($parent) · Haushalt {{ $parent->name }} @endif
                    </div>
                    <div class="text-xs text-gray-400">{{ $user->email }}</div>
                </div>
                <div class="text-right">
                    <div class="text-xs uppercase tracking-wide text-gray-400">Summe {{ $monthLabel }}</div>
                    <div class="text-2xl font-bold text-gray-900">{{ $euro($total) }}</div>
                    @if ($paid)
                        <span class="mt-1 inline-flex items-center gap-1 rounded-full bg-green-100 px-2.5 py-1 text-xs font-medium text-green-700">
                            ✓ bezahlt @if ($settlement?->paid_at) am {{ $settlement->paid_at->format('d.m.Y') }} @endif
                        </span>
                    @else
                        <span class="mt-1 inline-block text-xs font-medium text-amber-600">offen</span>
                    @endif
                </div>
            </div>
            {{-- Kein manuelles „bezahlt": Der Bezahlt-Status kommt ausschließlich aus
                 dem externen Zahlungs-Import (folgt) – hier nicht setzbar. --}}
            @if ($isAdmin)
            <div class="mt-4 border-t border-gray-100 pt-3 text-xs text-gray-400">
                Der Bezahlt-Status wird ausschließlich über den externen Zahlungs-Import gesetzt.
            </div>
            @endif
        </div>

        {{-- Menü-Bestellungen --}}
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
            <div class="flex items-center justify-between border-b border-gray-100 bg-gray-50 px-4 py-2">
                <span class="text-sm font-semibold text-gray-700">Menü-Vorbestellungen</span>
                <span class="text-sm font-semibold text-gray-700">{{ $euro($menu_total) }}</span>
            </div>
            @if (empty($menu))
                <p class="px-4 py-5 text-center text-sm text-gray-400">Keine Menü-Bestellungen in diesem Monat.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 text-left text-xs uppercase tracking-wide text-gray-400">
                                <th class="px-4 py-2 font-medium">Tag</th>
                                <th class="px-3 py-2 font-medium">Gericht</th>
                                <th class="px-3 py-2 font-medium">Kategorie</th>
                                <th class="px-3 py-2 text-right font-medium">Preis</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach ($menu as $row)
                                <tr>
                                    <td class="px-4 py-2 whitespace-nowrap text-gray-600">{{ $day($row['date']) }}</td>
                                    <td class="px-3 py-2 font-medium text-gray-800">
                                        {{ $row['dish'] }}
                                        @if ($row['outcome'] === 'none')
                                            <span class="ml-1 rounded-full bg-rose-50 px-1.5 py-0.5 text-xs font-medium text-rose-600"
                                                  title="Bestellt, aber nicht abgeholt – wird trotzdem berechnet">No-Show</span>
                                        @elseif ($row['outcome'] === 'alternative')
                                            <span class="ml-1 rounded-full bg-amber-50 px-1.5 py-0.5 text-xs font-medium text-amber-700"
                                                  title="Statt des bestellten Menüs wurde das Alternativmenü genommen – berechnet wird wie bestellt">↦ Alternativmenü genommen</span>
                                        @elseif ($row['outcome'] === 'declined')
                                            <span class="ml-1 rounded-full bg-rose-50 px-1.5 py-0.5 text-xs font-medium text-rose-600"
                                                  title="Bei der Ausgabe nicht genommen – wird trotzdem berechnet">nicht genommen{{ $row['reason'] ? ': '.$row['reason'] : '' }}</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-gray-500">{{ $row['category'] }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums text-gray-700">{{ $euro($row['price']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- OGS --}}
        @if ($ogs['price'] > 0 && ($ogs['days'] > 0 || ! empty($ogs['cancelled'])))
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
                <div class="flex items-center justify-between border-b border-gray-100 bg-gray-50 px-4 py-2">
                    <span class="text-sm font-semibold text-gray-700">
                        OGS-Essen
                        <span class="font-normal text-gray-400">· {{ $ogs['days'] }} Tage × {{ $euro($ogs['price']) }}
                            @if ($ogs['subscribed']) (Saison-Abo) @endif</span>
                    </span>
                    <span class="text-sm font-semibold text-gray-700">{{ $euro($ogs['total']) }}</span>
                </div>
                <div class="px-4 py-3 text-sm text-gray-600">
                    @if (! empty($ogs['dates']))
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($ogs['dates'] as $d)
                                @php
                                    $st = $d['status'] ?? 'none';
                                    $cls = $st === 'taken' ? 'bg-green-100 text-green-700'
                                        : ($st === 'declined' ? 'bg-red-100 text-red-700' : 'bg-amber-50 text-amber-700 ring-1 ring-amber-200');
                                    $sym = $st === 'taken' ? '✓' : ($st === 'declined' ? '✗' : '○');
                                @endphp
                                <span class="rounded px-1.5 py-0.5 text-xs {{ $cls }}">{{ $sym }} {{ $d['date']->isoFormat('dd DD.MM.') }}</span>
                            @endforeach
                        </div>
                        <div class="mt-2 text-xs text-gray-400">
                            <span class="font-semibold text-green-600">{{ $ogs['picked'] }}</span> abgeholt ·
                            <span class="font-semibold text-red-600">{{ $ogs['declined'] }}</span> abgelehnt ·
                            <span class="font-semibold text-amber-600">{{ $ogs['noshow'] }}</span> nicht abgeholt
                            <span class="text-gray-300">— alle Tage berechnet</span>
                        </div>
                    @endif
                    @if (! empty($ogs['cancelled']))
                        <div class="mt-2 text-xs text-gray-400">
                            Abbestellt (nicht berechnet):
                            @foreach ($ogs['cancelled'] as $d)
                                <span class="rounded bg-rose-50 px-1.5 py-0.5 text-rose-500">{{ \Illuminate\Support\Carbon::parse($d)->isoFormat('dd DD.MM.') }}</span>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        @endif

        {{-- Spontane Abholungen --}}
        @if (! empty($spontan))
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
                <div class="flex items-center justify-between border-b border-gray-100 bg-gray-50 px-4 py-2">
                    <span class="text-sm font-semibold text-gray-700">Spontane Abholungen</span>
                    <span class="text-sm font-semibold text-gray-700">{{ $euro($spontan_total) }}</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 text-left text-xs uppercase tracking-wide text-gray-400">
                                <th class="px-4 py-2 font-medium">Tag</th>
                                <th class="px-3 py-2 font-medium">Gericht</th>
                                <th class="px-3 py-2 font-medium">Kategorie</th>
                                <th class="px-3 py-2 text-right font-medium">Preis</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach ($spontan as $row)
                                <tr>
                                    <td class="px-4 py-2 whitespace-nowrap text-gray-600">{{ $day($row['date']) }}</td>
                                    <td class="px-3 py-2 font-medium text-gray-800">{{ $row['dish'] }}</td>
                                    <td class="px-3 py-2 text-gray-500">{{ $row['category'] }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums text-gray-700">{{ $euro($row['price']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Chip-Pfand --}}
        @if (! empty($chips))
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
                <div class="flex items-center justify-between border-b border-gray-100 bg-gray-50 px-4 py-2">
                    <span class="text-sm font-semibold text-gray-700">Chip-Pfand</span>
                    <span class="text-sm font-semibold text-gray-700">{{ $euro($pfand_net) }}</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <tbody class="divide-y divide-gray-50">
                            @foreach ($chips as $c)
                                <tr>
                                    <td class="px-4 py-2 whitespace-nowrap text-gray-600">{{ $day($c['date']) }}</td>
                                    <td class="px-3 py-2 text-gray-700">
                                        {{ $c['type'] === 'aus' ? 'Chip ausgegeben (Pfand)' : 'Chip zurückgegeben' }}
                                        <span class="ml-1 font-mono text-xs text-gray-400">{{ $c['uid'] }}</span>
                                    </td>
                                    <td class="px-3 py-2 text-right tabular-nums {{ $c['amount'] < 0 ? 'text-green-600' : 'text-gray-700' }}">
                                        {{ ($c['amount'] > 0 ? '+' : '').$euro($c['amount']) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Gesamt --}}
        <div class="flex items-center justify-between rounded-xl border-2 border-gray-200 bg-gray-50 px-5 py-3">
            <span class="font-semibold text-gray-700">Gesamt {{ $monthLabel }}</span>
            <span class="text-xl font-bold text-gray-900">{{ $euro($total) }}</span>
        </div>

        <p class="text-xs text-gray-400">
            Abrechnungsbasis: verbindliche Vorbestellungen (No-Shows zahlen trotzdem), spontane Abholungen und Chip-Pfand.
            Rechtzeitig stornierte Bestellungen sind nicht enthalten.
        </p>
    </div>
</x-app-layout>
