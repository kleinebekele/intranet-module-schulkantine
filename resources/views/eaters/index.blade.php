<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div class="flex items-center gap-2">
                <x-module-icon name="users" class="text-2xl text-indigo-600" />
                <h1 class="text-xl font-semibold text-gray-800">Teilnehmer</h1>
            </div>
            @if (Route::has('module.userimport.index'))
                <a href="{{ route('module.userimport.index') }}"
                   class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    <x-module-icon name="download" class="text-base" />
                    Benutzer importieren
                </a>
            @endif
        </div>
    </x-slot>

    <div class="max-w-5xl">
        <div class="mb-4 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600">
            Teilnehmer sind Benutzer des Intranets. Die <strong>Gruppe ergibt sich aus der Rolle</strong>
            (Priorität OGS → Schüler → Sonstige) – sie wird über den Benutzer/Import gesteuert, nicht hier.
            Hier pflegst du nur die <strong>Verträglichkeiten</strong>.
        </div>

        {{-- Filterleiste --}}
        <form method="GET" action="{{ route('module.schulkantine.eaters.index') }}"
              class="mb-4 flex flex-wrap items-end gap-3">
            <div class="min-w-[12rem] flex-1">
                <label for="search" class="block text-xs font-medium text-gray-500">Suche (Name oder E-Mail)</label>
                <input id="search" name="search" type="text" value="{{ $search }}"
                       placeholder="z. B. Müller"
                       class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <button type="submit"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                <x-module-icon name="search" class="text-base" /> Filtern
            </button>
            @if ($search !== '')
                <a href="{{ route('module.schulkantine.eaters.index') }}" class="px-2 py-2 text-sm text-gray-500 hover:text-gray-700">Zurücksetzen</a>
            @endif
        </form>

        <div class="mb-2 text-xs text-gray-400">
            {{ $users->count() }} Teilnehmer
            @if ($search !== '')
                <span class="text-gray-300">·</span> gefiltert
            @endif
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-6">
            @if ($users->isEmpty())
                <p class="text-sm text-gray-500">
                    @if ($search !== '')
                        Keine Teilnehmer gefunden. Passe die Suche an.
                    @else
                        Noch keine Benutzer vorhanden. Lege sie über den Benutzer-Import an.
                    @endif
                </p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-left text-xs font-medium uppercase tracking-wide text-gray-400">
                                <th class="px-3 py-2">Name</th>
                                <th class="px-3 py-2">E-Mail</th>
                                <th class="px-3 py-2">Gruppe (aus Rolle)</th>
                                <th class="px-3 py-2">Verträglichkeiten</th>
                                <th class="px-3 py-2 text-center">Chip</th>
                                <th class="px-3 py-2 text-right">Aktion</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($users as $user)
                                @php $group = \Intranet\Modules\Schulkantine\Models\CustomerGroup::forUser($user, $groups); @endphp
                                <tr class="hover:bg-gray-50">
                                    <td class="px-3 py-2 font-medium text-gray-800">{{ $user->name }}</td>
                                    <td class="px-3 py-2 text-gray-500">{{ $user->email }}</td>
                                    <td class="px-3 py-2">
                                        @if ($group)
                                            <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">{{ $group->name }}</span>
                                        @else
                                            <span class="text-gray-300">—</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2">
                                        @if ($user->kantineAllergens->isNotEmpty())
                                            <span class="inline-flex rounded-full bg-rose-50 px-2 py-0.5 text-xs font-medium text-rose-700">{{ $user->kantineAllergens->count() }} Allergene</span>
                                        @endif
                                        @if ($user->kantineDiets->isNotEmpty())
                                            <span class="inline-flex rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">{{ $user->kantineDiets->count() }} Diäten</span>
                                        @endif
                                        @if ($user->kantineAllergens->isEmpty() && $user->kantineDiets->isEmpty())
                                            <span class="text-gray-300">—</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        @php
                                            $userChips = $chips->get($user->id) ?: collect();
                                            $isOgs = $group && $group->ordering_mode === \Intranet\Modules\Schulkantine\Models\CustomerGroup::MODE_JA_NEIN;
                                            $chipTip = $userChips->map(fn ($c) => ($c->isSchool()
                                                    ? 'Schul-Chip'.($c->deposit > 0 ? ' (Pfand '.number_format((float) $c->deposit, 2, ',', '.').' €)' : '')
                                                    : 'eigener Chip').' · '.$c->uid)->implode("\n");
                                        @endphp
                                        @if ($isOgs)
                                            <span class="cursor-help text-gray-300" title="OGS-Kind – kein Chip nötig (Ausgabe über die Sammelliste)">–</span>
                                        @elseif ($userChips->isNotEmpty())
                                            <span class="cursor-help font-semibold text-green-600" title="{{ $chipTip }}">✓@if ($userChips->count() > 1)<span class="ml-0.5 text-xs font-normal text-gray-400">×{{ $userChips->count() }}</span>@endif</span>
                                        @else
                                            <span class="cursor-help font-semibold text-red-400" title="Kein Chip registriert">✗</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        <a href="{{ route('module.schulkantine.eaters.edit', $user) }}" title="Verträglichkeiten bearbeiten"
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
