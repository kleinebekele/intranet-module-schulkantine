<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2">
            <x-module-icon name="restaurant" class="text-2xl text-indigo-600" />
            <h1 class="text-xl font-semibold text-gray-800">Meine Daten</h1>
        </div>
    </x-slot>

    <div class="max-w-3xl space-y-6">
        {{-- Erfolgsmeldung zeigt das App-Layout global; hier nur Fehler. --}}
        @if ($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
        @endif

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

                {{-- Budget (Spontankäufe) & Kategorie-Freigaben – Eltern für ihre Kinder --}}
                @if ($m['canLimits'])
                    @php $walkinCats = $categories->where('allows_walkin', true); @endphp
                    <form method="POST" action="{{ route('module.schulkantine.sonderkost.limits', $person) }}"
                          class="space-y-5 border-b border-gray-100 px-6 py-5">
                        @csrf

                        {{-- Vorbestellung erlaubt für (erster Block – ohne margin-top, sonst doppelter Abstand oben) --}}
                        <div class="!mt-0">
                            <x-input-label value="Vorbestellung erlaubt für" />
                            <div class="mt-2 flex flex-wrap gap-x-4 gap-y-2">
                                @foreach ($categories as $cat)
                                    @php $perm = $m['perms']->get($cat->id); @endphp
                                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                        <input type="checkbox" name="preorder[]" value="{{ $cat->id }}"
                                               @checked($perm ? $perm->may_preorder : true)
                                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                        {{ $cat->name }}
                                    </label>
                                @endforeach
                            </div>
                            <p class="mt-1 text-xs text-gray-400">Nicht angehakt = darf diese Kategorie nicht vorbestellen (z. B. keinen Nachtisch).</p>
                            <p class="mt-1 text-xs text-gray-400">Bereits bestelltes Essen kann dein Kind trotzdem jederzeit selbst wieder abbestellen – unabhängig von diesen Freigaben.</p>
                        </div>

                        {{-- Spontankauf erlaubt für --}}
                        @if ($walkinCats->isNotEmpty())
                            <div>
                                <x-input-label value="Spontankauf erlaubt für" />
                                <div class="mt-2 flex flex-wrap gap-x-4 gap-y-2">
                                    @foreach ($walkinCats as $cat)
                                        @php $perm = $m['perms']->get($cat->id); @endphp
                                        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                            <input type="checkbox" name="walkin[]" value="{{ $cat->id }}"
                                                   @checked($perm ? $perm->may_walkin : true)
                                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                            {{ $cat->name }}
                                        </label>
                                    @endforeach
                                </div>
                                <p class="mt-1 text-xs text-gray-400">Nicht angehakt = darf diese Kategorie nicht spontan kaufen (z. B. kein Eis).</p>
                            </div>
                        @endif

                        {{-- Wochenbudget für Spontankäufe --}}
                        <div>
                            <x-input-label value="Wochenbudget für Spontankäufe" />
                            <div class="mt-1 flex items-center gap-2">
                                <input type="number" step="0.01" min="0" name="weekly_budget"
                                       value="{{ $m['weeklyBudget'] !== null ? number_format((float) $m['weeklyBudget'], 2, '.', '') : '' }}"
                                       placeholder="leer = kein Limit"
                                       class="block w-44 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <span class="text-sm text-gray-400">€ / Woche</span>
                            </div>
                            <p class="mt-1 text-xs text-gray-400">Gilt nur für spontane Käufe am Ausgabetresen – nicht für Vorbestellungen.</p>
                        </div>

                        <div>
                            <button type="submit" class="inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                                <x-module-icon name="save" class="text-base" /> Budget &amp; Freigaben speichern
                            </button>
                        </div>
                    </form>
                @endif

                <form method="POST" action="{{ route('module.schulkantine.sonderkost.update', $person) }}"
                      class="space-y-6 p-6">
                    @csrf
                    @method('PUT')

                    {{-- Allergene (erster Block – ohne margin-top, sonst doppelter Abstand oben) --}}
                    <div class="!mt-0">
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

                    <div>
                        <button type="submit" class="inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                            <x-module-icon name="save" class="text-base" /> Speichern
                        </button>
                    </div>
                </form>

                {{-- NFC-Chips (eigene Aktionen, außerhalb des Sonderkost-Formulars) --}}
                <div class="border-t border-gray-100 px-6 py-5">
                    <x-input-label value="NFC-Chips (für die Essensausgabe)" />

                    @if ($m['isOgs'])
                        <p class="mt-2 text-sm text-gray-400">OGS-Kind – hier ist kein Chip nötig. Die Ausgabe läuft über die Sammelliste.</p>
                    @else
                        {{-- Schul-Chips: nur lesbar --}}
                        @foreach ($m['schoolChips'] as $chip)
                            <div class="mt-2 flex flex-wrap items-center justify-between gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2">
                                <div class="text-sm">
                                    <span class="font-medium text-amber-900">🔒 Schul-Chip</span>
                                    <span class="font-mono text-amber-800">{{ $chip->uid }}</span>
                                    @if ($chip->deposit > 0)
                                        <span class="text-amber-700">· Pfand {{ number_format((float) $chip->deposit, 2, ',', '.') }} €</span>
                                    @endif
                                </div>
                                <span class="text-xs text-amber-600">von der Schule – Rückgabe nur über die Schule</span>
                            </div>
                        @endforeach

                        {{-- Eigene Chips: entfernbar --}}
                        @foreach ($m['ownChips'] as $chip)
                            <div class="mt-2 flex items-center justify-between gap-2 rounded-lg border border-green-200 bg-green-50 px-3 py-2">
                                <div class="text-sm">
                                    <span class="font-medium text-green-800">✓ Eigener Chip</span>
                                    <span class="font-mono text-green-700">{{ $chip->uid }}</span>
                                </div>
                                <form method="POST" action="{{ route('module.schulkantine.chips.remove', $chip) }}"
                                      onsubmit="return confirm('Chip entfernen?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-xs font-medium text-gray-400 hover:text-red-600">entfernen</button>
                                </form>
                            </div>
                        @endforeach

                        {{-- Weiteren eigenen Chip hinzufügen --}}
                        <div x-data="{
                                msg: '', ok: false,
                                supported: typeof NDEFReader !== 'undefined',
                                async scan() {
                                    if (!this.supported) { this.ok=false; this.msg='Web-NFC geht nur auf einem Handy mit NFC (Android/Chrome). Du kannst die Kennung auch manuell eintragen.'; return; }
                                    try {
                                        this.ok=false; this.msg='Chip jetzt an das Handy halten …';
                                        const reader = new NDEFReader();
                                        await reader.scan();
                                        reader.onreading = (e) => { this.$refs.uid.value = e.serialNumber || ''; this.ok=true; this.msg='Chip gelesen – jetzt auf Hinzufügen tippen.'; };
                                        reader.onreadingerror = () => { this.ok=false; this.msg='Konnte den Chip nicht lesen – nochmal.'; };
                                    } catch (err) { this.ok=false; this.msg='Scan nicht möglich: ' + (err && err.message ? err.message : err); }
                                }
                             }" class="mt-3">
                            <form method="POST" action="{{ route('module.schulkantine.chips.register', $person) }}"
                                  class="flex flex-col gap-2 sm:flex-row sm:items-center">
                                @csrf
                                <input type="text" name="nfc_uid" x-ref="uid" placeholder="Kennung eines weiteren eigenen Chips"
                                       class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:max-w-xs">
                                <button type="button" @click="scan()"
                                        class="inline-flex items-center justify-center gap-1.5 rounded-md border border-indigo-300 bg-white px-3 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-50">
                                    <x-module-icon name="search" class="text-base" /> Chip scannen
                                </button>
                                <button type="submit"
                                        class="inline-flex items-center justify-center gap-1.5 rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                                    Hinzufügen
                                </button>
                            </form>
                            <p class="mt-1 text-xs" :class="ok ? 'text-green-600' : 'text-gray-500'" x-text="msg"></p>
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</x-app-layout>
