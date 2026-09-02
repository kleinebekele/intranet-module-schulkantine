<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h1 class="text-xl font-semibold text-gray-800">Menü bearbeiten · {{ $season->name }}</h1>
            <a href="{{ route('module.schulkantine.seasons.show', ['season' => $season, 'tab' => 'menues']) }}"
               class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                <x-module-icon name="back" class="text-base" />
                Zurück
            </a>
        </div>
    </x-slot>

    <div class="max-w-2xl">
        @include('schulkantine::seasons._menu-form')
    </div>
</x-app-layout>
