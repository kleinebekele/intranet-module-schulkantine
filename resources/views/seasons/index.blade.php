<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <x-module-icon name="calendar" class="text-2xl text-indigo-600" />
                <h1 class="text-xl font-semibold text-gray-800">Saisons &amp; Kalender</h1>
            </div>
            <a href="{{ route('module.schulkantine.seasons.create') }}"
               class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                + Neue Saison
            </a>
        </div>
    </x-slot>

    <div class="max-w-4xl space-y-4">
        @forelse ($seasons as $season)
            <a href="{{ route('module.schulkantine.seasons.show', $season) }}"
               class="block rounded-xl border border-gray-200 bg-white p-5 hover:border-indigo-300">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-800">{{ $season->name }}</h2>
                    @if ($season->is_active)
                        <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">aktiv</span>
                    @endif
                </div>
                <p class="mt-1 text-sm text-gray-500">
                    {{ $season->start_date->format('d.m.Y') }} &ndash; {{ $season->end_date->format('d.m.Y') }}
                    &middot; {{ $season->closed_days_count }} Schließtage
                    @if ($season->bundesland) &middot; {{ $season->bundesland }} @endif
                </p>
            </a>
        @empty
            <div class="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center text-gray-500">
                Noch keine Saison angelegt. Lege die erste an!
            </div>
        @endforelse
    </div>
</x-app-layout>
