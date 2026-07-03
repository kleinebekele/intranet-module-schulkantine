<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div class="flex items-center gap-2">
                <x-module-icon name="users" class="text-2xl text-indigo-600" />
                <h1 class="text-xl font-semibold text-gray-800">Teilnehmer</h1>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('module.schulkantine.eaters.import.form') }}"
                   class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    <x-module-icon name="download" class="text-base" />
                    CSV-Import
                </a>
                <a href="{{ route('module.schulkantine.eaters.create') }}"
                   class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    <x-module-icon name="plus" class="text-base" />
                    Neuer Teilnehmer
                </a>
            </div>
        </div>
    </x-slot>

    <div class="max-w-5xl">
        @unless ($activeSeason)
            <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Es ist keine Saison als „aktiv" markiert – die Gruppen-Zuordnung greift erst, wenn eine aktive Saison existiert.
            </div>
        @endunless

        {{-- Filterleiste --}}
        <form method="GET" action="{{ route('module.schulkantine.eaters.index') }}"
              class="mb-4 flex flex-wrap items-end gap-3">
            <div class="min-w-[12rem] flex-1">
                <label for="search" class="block text-xs font-medium text-gray-500">Suche (Name)</label>
                <input id="search" name="search" type="text" value="{{ $search }}"
                       placeholder="z. B. Max"
                       class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <label for="status" class="block text-xs font-medium text-gray-500">Status</label>
                <select id="status" name="status"
                        class="mt-1 block rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Alle</option>
                    <option value="active" @selected($statusFilter === 'active')>Aktiv</option>
                    <option value="inactive" @selected($statusFilter === 'inactive')>Inaktiv</option>
                </select>
            </div>
            <button type="submit"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                <x-module-icon name="search" class="text-base" /> Filtern
            </button>
            @if ($search !== '' || $statusFilter !== '')
                <a href="{{ route('module.schulkantine.eaters.index') }}" class="px-2 py-2 text-sm text-gray-500 hover:text-gray-700">Zurücksetzen</a>
            @endif
        </form>

        <div class="mb-2 text-xs text-gray-400">
            {{ $eaters->count() }} Teilnehmer
            @if ($search !== '' || $statusFilter !== '')
                <span class="text-gray-300">·</span> gefiltert
            @endif
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-6">
            @if ($eaters->isEmpty())
                <p class="text-sm text-gray-500">
                    @if ($search !== '' || $statusFilter !== '')
                        Keine Teilnehmer gefunden. Passe Suche oder Status an.
                    @else
                        Noch keine Teilnehmer. Lege den ersten an!
                    @endif
                </p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-left text-xs font-medium uppercase tracking-wide text-gray-400">
                                <th class="px-3 py-2">Name</th>
                                <th class="px-3 py-2">Login</th>
                                <th class="px-3 py-2">Gruppe (aktive Saison)</th>
                                <th class="px-3 py-2">Vormunde</th>
                                <th class="px-3 py-2">Sonderkost</th>
                                <th class="px-3 py-2">Status</th>
                                <th class="px-3 py-2 text-right">Aktion</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($eaters as $eater)
                                @php $group = $activeSeason ? $eater->groupForSeason($activeSeason->id) : null; @endphp
                                <tr class="hover:bg-gray-50">
                                    <td class="px-3 py-2 font-medium text-gray-800">{{ $eater->name }}</td>
                                    <td class="px-3 py-2">
                                        @if ($eater->user)
                                            <span class="inline-flex rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700">{{ $eater->user->name }}</span>
                                        @else
                                            <span class="text-gray-400">über Vormund</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2">
                                        @if ($group)
                                            <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">{{ $group->name }}</span>
                                        @else
                                            <span class="text-gray-300">—</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-gray-600">
                                        {{ $eater->guardians->count() ?: '—' }}
                                    </td>
                                    <td class="px-3 py-2">
                                        @if ($eater->allergens->isNotEmpty())
                                            <span class="inline-flex rounded-full bg-rose-50 px-2 py-0.5 text-xs font-medium text-rose-700">{{ $eater->allergens->count() }} Allergene</span>
                                        @endif
                                        @if ($eater->diets->isNotEmpty())
                                            <span class="inline-flex rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">{{ $eater->diets->count() }} Diäten</span>
                                        @endif
                                        @if ($eater->allergens->isEmpty() && $eater->diets->isEmpty())
                                            <span class="text-gray-300">—</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2">
                                        @if ($eater->is_active)
                                            <span class="inline-flex rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">aktiv</span>
                                        @else
                                            <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500">inaktiv</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        <a href="{{ route('module.schulkantine.eaters.edit', $eater) }}" title="Bearbeiten"
                                           class="inline-flex items-center rounded-md p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-700">
                                            <x-module-icon name="edit" class="text-base" />
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
