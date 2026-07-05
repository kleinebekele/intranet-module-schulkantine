<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2">
            <x-module-icon name="restaurant" class="text-2xl text-indigo-600" />
            <h1 class="text-xl font-semibold text-gray-800">Meine Sonderkost</h1>
        </div>
    </x-slot>

    <div class="max-w-3xl space-y-6">
        {{-- Erfolgsmeldung zeigt das App-Layout global; hier nur Fehler. --}}
        @if ($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
        @endif

        <p class="text-sm text-gray-500">
            Hier hinterlegst du Allergien und Diäten für dich selbst – und als Elternteil auch für deine Kinder.
            Beim Bestellen werden unpassende Gerichte dann mit <span class="font-medium text-red-600">⚠️ Nicht geeignet</span> markiert
            (bestellen bleibt trotzdem möglich).
        </p>

        @foreach ($household as $m)
            @php $person = $m['user']; @endphp
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
                <div class="flex flex-wrap items-center gap-2 border-b border-gray-100 bg-gray-50/60 px-4 py-3">
                    <span class="font-semibold text-gray-800">{{ $person->name }}</span>
                    @if ($person->id === auth()->id())
                        <span class="rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-600">ich</span>
                    @else
                        <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-600">mein Kind</span>
                    @endif
                    <span class="text-xs text-gray-400">{{ $m['group']?->name ?? 'keine Gruppe' }}</span>
                </div>

                <form method="POST" action="{{ route('module.schulkantine.sonderkost.update', $person) }}"
                      class="space-y-6 p-6">
                    @csrf
                    @method('PUT')

                    {{-- Allergene --}}
                    <div>
                        <x-input-label value="Allergien (verträgt diese Allergene NICHT)" />
                        <div class="mt-2 grid grid-cols-1 gap-x-4 gap-y-2 sm:grid-cols-2">
                            @foreach ($allergens as $allergen)
                                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                    <input type="checkbox" name="allergens[]" value="{{ $allergen->id }}" @checked(in_array($allergen->id, $m['selAllergens']))
                                           class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    <span><span class="font-medium text-gray-400">{{ $allergen->code }}</span> {{ $allergen->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    {{-- Diäten --}}
                    <div>
                        <x-input-label value="Diäten (Essen muss dafür geeignet sein)" />
                        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-2">
                            @forelse ($diets as $diet)
                                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                    <input type="checkbox" name="diets[]" value="{{ $diet->id }}" @checked(in_array($diet->id, $m['selDiets']))
                                           class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    {{ $diet->name }}
                                </label>
                            @empty
                                <span class="text-xs text-gray-400">Keine Diäten hinterlegt.</span>
                            @endforelse
                        </div>
                    </div>

                    <div class="flex items-center gap-3 pt-2">
                        <x-primary-button class="gap-1.5">
                            <x-module-icon name="save" class="text-base" />
                            Speichern
                        </x-primary-button>
                    </div>
                </form>
            </div>
        @endforeach
    </div>
</x-app-layout>
