<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div class="flex items-center gap-2">
                <x-module-icon name="book" class="text-2xl text-indigo-600" />
                <h1 class="text-xl font-semibold text-gray-800">Anleitung</h1>
            </div>
            <a href="{{ route('module.schulkantine.guide.pdf') }}"
               class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                <x-module-icon name="download" class="text-base" />
                Als PDF herunterladen
            </a>
        </div>
    </x-slot>

    <div class="w-full">
        <div class="rounded-xl border border-gray-200 bg-white p-6 sm:p-8">
            @include('schulkantine::guide._styles')
            <div class="kantine-doc" style="max-width: 60rem;">
                @include('schulkantine::guide._content')
            </div>
        </div>
    </div>
</x-app-layout>
