{{-- Kopfzeile einer Saison (Saison-Seite und ihre Menü&Serve-Tabs). Erwartet $season. --}}
<div class="flex items-center justify-between gap-4">
    <div class="flex items-center gap-2">
        <x-module-icon name="calendar" class="text-2xl text-indigo-600" />
        <h1 class="text-xl font-semibold text-gray-800">{{ $season->name }}</h1>
        @if ($season->is_active)
            <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">aktiv</span>
        @endif
    </div>
    <a href="{{ route('module.schulkantine.seasons.index') }}"
       class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
        <x-module-icon name="back" class="text-base" />
        Zurück
    </a>
</div>
