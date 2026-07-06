<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2">
            <x-module-icon name="trophy" class="text-2xl text-indigo-600" />
            <h1 class="text-xl font-semibold text-gray-800">Bewertungen – beliebteste Essen</h1>
        </div>
    </x-slot>

    <div class="max-w-4xl space-y-5">
        {{-- Zeitraumfilter --}}
        <div class="flex flex-wrap items-end justify-between gap-4">
            <form method="GET" action="{{ route('module.schulkantine.ratings.report') }}" class="flex items-end gap-2">
                <div>
                    <label for="monat" class="block text-xs font-medium text-gray-500">Zeitraum</label>
                    <select id="monat" name="monat" onchange="this.form.submit()"
                            class="mt-1 rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Gesamter Zeitraum</option>
                        @foreach ($months as $ym)
                            @php
                                [$y, $m] = explode('-', $ym);
                                $namen = [1=>'Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];
                            @endphp
                            <option value="{{ $ym }}" @selected($ym === $monthValue)>{{ $namen[(int) $m] }} {{ $y }}</option>
                        @endforeach
                    </select>
                </div>
            </form>
            <div class="text-xs text-gray-400">{{ $totalVotes }} Bewertung{{ $totalVotes === 1 ? '' : 'en' }} insgesamt</div>
        </div>

        <div class="rounded-lg border border-indigo-100 bg-indigo-50 px-4 py-3 text-xs text-indigo-700">
            🔒 <strong>Anonym:</strong> Hier siehst du nur die <em>Anzahl</em> der Daumen je Gericht – nie, wer
            bewertet hat.
        </div>

        @if ($dishes->isEmpty())
            <div class="rounded-xl border border-gray-200 bg-white px-4 py-10 text-center text-sm text-gray-500">
                Für diesen Zeitraum liegen noch keine Bewertungen vor.
            </div>
        @else
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 text-left text-xs uppercase tracking-wide text-gray-400">
                                <th class="px-4 py-2 font-medium">#</th>
                                <th class="px-4 py-2 font-medium">Gericht</th>
                                <th class="px-3 py-2 text-center font-medium">👍</th>
                                <th class="px-3 py-2 text-center font-medium">👎</th>
                                <th class="px-4 py-2 font-medium">Zustimmung</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach ($dishes as $i => $row)
                                <tr>
                                    <td class="px-4 py-3 text-gray-400 tabular-nums">
                                        @if ($i === 0) 🥇 @elseif ($i === 1) 🥈 @elseif ($i === 2) 🥉 @else {{ $i + 1 }} @endif
                                    </td>
                                    <td class="px-4 py-3 font-medium text-gray-800">{{ $row['dish']->name }}</td>
                                    <td class="px-3 py-3 text-center font-semibold text-green-600 tabular-nums">{{ $row['up'] }}</td>
                                    <td class="px-3 py-3 text-center font-semibold text-rose-600 tabular-nums">{{ $row['down'] }}</td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <div class="h-2 w-28 overflow-hidden rounded-full bg-rose-100">
                                                <div class="h-full rounded-full bg-green-500" style="width: {{ $row['quote'] }}%"></div>
                                            </div>
                                            <span class="w-10 text-right text-xs tabular-nums text-gray-500">{{ $row['quote'] }}%</span>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
