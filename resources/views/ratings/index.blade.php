<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2">
            <x-module-icon name="like" class="text-2xl text-indigo-600" />
            <h1 class="text-xl font-semibold text-gray-800">Essen bewerten</h1>
        </div>
    </x-slot>

    <div class="max-w-3xl space-y-5">
        @if (session('status'))
            <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                {{ session('status') }}
            </div>
        @endif

        <p class="text-sm text-gray-500">
            Wie hat das Essen geschmeckt? Tippe auf <span class="font-medium text-green-600">👍</span> oder
            <span class="font-medium text-rose-600">👎</span>. Du kannst deine Wahl jederzeit ändern – ein
            weiterer Tipp auf denselben Daumen nimmt die Bewertung wieder zurück. Bewertbar sind nur Essen, die
            wirklich ausgegeben wurden. <span class="text-gray-400">Die Küche sieht nur, wie oft ein Gericht
            👍/👎 bekam – niemals, wer bewertet hat.</span>
        </p>

        @php
            $wochentage = ['Sonntag','Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag'];
        @endphp

        @forelse ($households as $hh)
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
                <div class="border-b border-gray-100 bg-gray-50/70 px-4 py-2 text-sm font-semibold text-gray-700">
                    {{ $hh['user']->name }}
                </div>
                <ul class="divide-y divide-gray-50">
                    @foreach ($hh['servings'] as $serving)
                        @php $current = optional($serving->rating)->rating; @endphp
                        <li class="flex items-center justify-between gap-4 px-4 py-3">
                            <div class="min-w-0">
                                <div class="truncate font-medium text-gray-800">{{ $serving->dish?->name ?? 'Essen' }}</div>
                                <div class="text-xs text-gray-400">
                                    {{ $wochentage[$serving->date->dayOfWeek] }}, {{ $serving->date->format('d.m.Y') }}
                                </div>
                            </div>

                            <div class="flex shrink-0 items-center gap-2">
                                {{-- Daumen hoch --}}
                                @if ($current === \Intranet\Modules\Schulkantine\Models\MealRating::UP)
                                    <form method="POST" action="{{ route('module.schulkantine.ratings.destroy', $serving) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" title="Bewertung zurücknehmen"
                                                class="flex h-10 w-10 items-center justify-center rounded-lg border border-green-500 bg-green-500 text-lg text-white shadow-sm hover:bg-green-600">
                                            👍
                                        </button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('module.schulkantine.ratings.rate', $serving) }}">
                                        @csrf
                                        <input type="hidden" name="rating" value="{{ \Intranet\Modules\Schulkantine\Models\MealRating::UP }}">
                                        <button type="submit" title="Hat mir geschmeckt"
                                                class="flex h-10 w-10 items-center justify-center rounded-lg border border-gray-300 bg-white text-lg text-gray-500 hover:border-green-400 hover:bg-green-50">
                                            👍
                                        </button>
                                    </form>
                                @endif

                                {{-- Daumen runter --}}
                                @if ($current === \Intranet\Modules\Schulkantine\Models\MealRating::DOWN)
                                    <form method="POST" action="{{ route('module.schulkantine.ratings.destroy', $serving) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" title="Bewertung zurücknehmen"
                                                class="flex h-10 w-10 items-center justify-center rounded-lg border border-rose-500 bg-rose-500 text-lg text-white shadow-sm hover:bg-rose-600">
                                            👎
                                        </button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('module.schulkantine.ratings.rate', $serving) }}">
                                        @csrf
                                        <input type="hidden" name="rating" value="{{ \Intranet\Modules\Schulkantine\Models\MealRating::DOWN }}">
                                        <button type="submit" title="Hat mir nicht geschmeckt"
                                                class="flex h-10 w-10 items-center justify-center rounded-lg border border-gray-300 bg-white text-lg text-gray-500 hover:border-rose-400 hover:bg-rose-50">
                                            👎
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @empty
            <div class="rounded-xl border border-gray-200 bg-white px-4 py-10 text-center text-sm text-gray-500">
                Noch keine ausgegebenen Essen zum Bewerten vorhanden. Sobald an der Ausgabe ein Essen abgehakt
                wurde, erscheint es hier.
            </div>
        @endforelse
    </div>
</x-app-layout>
