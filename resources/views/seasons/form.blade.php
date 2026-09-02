<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800">
            {{ $season->exists ? 'Saison bearbeiten' : 'Neue Saison' }}
        </h1>
    </x-slot>

    <div class="max-w-2xl">
        @include('schulkantine::seasons._settings-form')
    </div>
</x-app-layout>
