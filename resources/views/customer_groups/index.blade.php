<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div class="flex items-center gap-2">
                <x-module-icon name="users" class="text-2xl text-indigo-600" />
                <h1 class="text-xl font-semibold text-gray-800">Kundengruppen</h1>
            </div>
            <a href="{{ route('module.schulkantine.customer-groups.create') }}"
               class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                <x-module-icon name="plus" class="text-base" />
                Neue Kundengruppe
            </a>
        </div>
    </x-slot>

    <div class="max-w-4xl">
        <div class="rounded-xl border border-gray-200 bg-white p-6">
            @if ($groups->isEmpty())
                <p class="text-sm text-gray-500">Noch keine Kundengruppen. Lege die erste an! (z. B. OGS, Schule ab Klasse 5, Personal)</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-left text-xs font-medium uppercase tracking-wide text-gray-400">
                                <th class="px-3 py-2">Name</th>
                                <th class="px-3 py-2">Bestellmodus</th>
                                <th class="px-3 py-2">Ausgabe</th>
                                <th class="px-3 py-2">Status</th>
                                <th class="px-3 py-2 text-right">Aktion</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($groups as $group)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-3 py-2 font-medium text-gray-800">{{ $group->name }}</td>
                                    <td class="px-3 py-2">
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $group->ordering_mode === 'ja_nein' ? 'bg-amber-50 text-amber-700' : 'bg-indigo-50 text-indigo-700' }}">
                                            {{ $group->orderingModeLabel() }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-gray-600">
                                        @if ($group->pickup_from || $group->pickup_to)
                                            {{ $group->pickup_from ?: '…' }}&ndash;{{ $group->pickup_to ?: '…' }} Uhr
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2">
                                        @if ($group->is_active)
                                            <span class="inline-flex rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">aktiv</span>
                                        @else
                                            <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500">inaktiv</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        <a href="{{ route('module.schulkantine.customer-groups.edit', $group) }}" title="Bearbeiten"
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
