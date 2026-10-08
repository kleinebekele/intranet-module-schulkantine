<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2">
            <x-module-icon name="restaurant" class="text-2xl text-indigo-600" />
            <h1 class="text-xl font-semibold text-gray-800">Menü&amp;Serve-Menüs zuordnen</h1>
        </div>
    </x-slot>

    <div class="w-full space-y-5">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <form method="GET" action="{{ route('module.schulkantine.dishes.menueserve') }}" class="flex items-end gap-2">
                <div>
                    <label for="ab" class="block text-xs font-medium text-gray-500">Menüs ab</label>
                    <input id="ab" name="ab" type="date" value="{{ $ab->format('Y-m-d') }}" onchange="this.form.submit()"
                           class="mt-1 rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
            </form>
            <a href="{{ route('module.schulkantine.dishes.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← zurück zu den Gerichten</a>
        </div>

        <p class="text-sm text-gray-500">
            Je Menü&amp;Serve-Menü: welche Hauptspeise und welche Nachspeise aus unseren Gerichten ist es?
            Eindeutige Treffer sind vorausgewählt (<span class="text-sky-700">blau</span> = Vorschlag, noch nicht gespeichert).
            Gespeichert wird nur die Zuordnung – der Speiseplan bleibt unberührt.
        </p>

        @if ($fehler)
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">Menü&amp;Serve ist nicht lesbar: {{ $fehler }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
        @endif

        @if ($menues)
            <form method="POST" action="{{ route('module.schulkantine.dishes.menueserve.save') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="ab" value="{{ $ab->format('Y-m-d') }}">

                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-100 text-left text-xs uppercase tracking-wide text-gray-400">
                                    <th class="px-4 py-2 font-medium">Tag</th>
                                    <th class="px-3 py-2 font-medium">Menü&amp;Serve</th>
                                    <th class="px-3 py-2 font-medium">Hauptspeise bei uns</th>
                                    <th class="px-3 py-2 font-medium">Nachspeise bei uns</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($menues as $m)
                                    <tr class="align-top">
                                        <td class="whitespace-nowrap px-4 py-3">
                                            <div class="font-medium text-gray-900">{{ \Illuminate\Support\Carbon::parse($m['datum'])->locale('de')->isoFormat('dd DD.MM.YYYY') }}</div>
                                            <div class="text-xs text-gray-400">{{ $m['linie'] }}</div>
                                        </td>
                                        <td class="px-3 py-3">
                                            <div class="font-medium text-gray-900">
                                                {{ $m['titel'] }}
                                                @if ($m['art'])
                                                    <span class="ml-1 text-xs font-normal {{ $m['art'] === 7 ? 'text-green-700' : 'text-gray-500' }}">{{ $arten[$m['art']] }}</span>
                                                @endif
                                            </div>
                                            <div class="text-xs text-gray-500">Haupt: {{ $m['hauptspeise'] ?? '–' }}</div>
                                            <div class="text-xs text-gray-500">Nach: {{ $m['nachspeise'] ?? '–' }}</div>
                                        </td>
                                        @foreach (['hauptspeise_id', 'nachspeise_id'] as $feld)
                                            @php $gewaehlt = old('menue.'.$m['ms_id'].'.'.$feld, $m[$feld]); @endphp
                                            <td class="px-3 py-3">
                                                <select name="menue[{{ $m['ms_id'] }}][{{ $feld }}]"
                                                        class="w-full min-w-[14rem] rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 {{ $gewaehlt && ! $m['gemerkt'] ? 'text-sky-700' : '' }}">
                                                    <option value="">– keine –</option>
                                                    @foreach ($gruppen as $kategorie => $liste)
                                                        <optgroup label="{{ $kategorie }}">
                                                            @foreach ($liste as $d)
                                                                <option value="{{ $d->id }}" @selected((int) $gewaehlt === $d->id)>{{ $d->name }}{{ $d->is_active ? '' : ' (inaktiv)' }}</option>
                                                            @endforeach
                                                        </optgroup>
                                                    @endforeach
                                                </select>
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Zuordnung speichern</button>
                </div>
            </form>
        @elseif (! $fehler)
            <div class="rounded-xl border border-gray-200 bg-white px-4 py-10 text-center text-sm text-gray-500">Ab {{ $ab->format('d.m.Y') }} gibt es in Menü&amp;Serve keine Menüs.</div>
        @endif
    </div>
</x-app-layout>
