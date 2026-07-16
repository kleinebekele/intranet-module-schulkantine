<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div class="flex items-center gap-2">
                <x-module-icon name="folder" class="text-2xl text-indigo-600" />
                <h1 class="text-xl font-semibold text-gray-800">Kategorien</h1>
            </div>
            <a href="{{ route('module.schulkantine.categories.create') }}"
               class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                <x-module-icon name="plus" class="text-base" />
                Neue Kategorie
            </a>
        </div>
    </x-slot>

    <div class="max-w-4xl">
        <div class="rounded-xl border border-gray-200 bg-white p-6">
            @if ($categories->isEmpty())
                <p class="text-sm text-gray-500">Noch keine Kategorien. Lege die erste an! (z. B. Hauptmenü, Nachtisch, Getränk, Eis)</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-left text-xs font-medium uppercase tracking-wide text-gray-400">
                                <th class="px-3 py-2">Reihenfolge</th>
                                <th class="px-3 py-2">Name</th>
                                <th class="px-3 py-2">Erhältlich über</th>
                                <th class="px-3 py-2">Status</th>
                                <th class="px-3 py-2 text-right">Aktion</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($categories as $category)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-3 py-2 text-gray-400">{{ $category->sort_order }}</td>
                                    <td class="px-3 py-2 font-medium text-gray-800">
                                        <span class="inline-flex items-center gap-2">
                                            <span class="inline-block h-3 w-3 rounded-full border border-black/10" style="background-color: {{ $category->color ?? '#9ca3af' }};"></span>
                                            {{ $category->name }}
                                        </span>
                                    </td>
                                    {{-- Beide Wege zusammen: „möglich/nur Vorbestellung" allein könnte
                                         den Fall „nur spontan" nicht ausdrücken. --}}
                                    <td class="px-3 py-2">
                                        <div class="flex flex-wrap gap-1">
                                            @if ($category->allows_preorder)
                                                <span class="inline-flex rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700">Vorbestellung</span>
                                            @endif
                                            @if ($category->allows_walkin)
                                                <span class="inline-flex rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">spontan</span>
                                            @endif
                                            @if ($category->isWalkinOnly())
                                                <span class="inline-flex rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700"
                                                      title="Steht auf dem Speiseplan und ist bei der Ausgabe zu haben, kann aber nicht vorbestellt werden.">nur spontan</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-3 py-2">
                                        @if ($category->is_active)
                                            <span class="inline-flex rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">aktiv</span>
                                        @else
                                            <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500">inaktiv</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        <a href="{{ route('module.schulkantine.categories.edit', $category) }}" title="Bearbeiten"
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
