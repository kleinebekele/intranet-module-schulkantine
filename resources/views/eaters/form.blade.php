<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800">
            Verträglichkeiten: {{ $user->name }}
        </h1>
    </x-slot>

    @php
        $selAllergens = old('allergens', $selAllergens);
        $selDiets = old('diets', $selDiets);
    @endphp

    <div class="max-w-2xl space-y-6">
        {{-- Stammdaten & abgeleitete Gruppe (read-only) --}}
        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm">
            <div class="flex flex-wrap items-center gap-x-6 gap-y-1">
                <div><span class="text-gray-400">Name:</span> <span class="font-medium text-gray-800">{{ $user->name }}</span></div>
                <div><span class="text-gray-400">E-Mail:</span> <span class="text-gray-700">{{ $user->email }}</span></div>
                <div><span class="text-gray-400">Gruppe:</span>
                    @if ($group)
                        <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">{{ $group->name }}</span>
                    @else
                        <span class="text-gray-300">—</span>
                    @endif
                </div>
            </div>

            @if ($user->parents->isNotEmpty())
                <div class="mt-3 border-t border-gray-200 pt-3">
                    <span class="text-gray-400">Eltern / Vormunde:</span>
                    <div class="mt-1 flex flex-wrap items-center gap-2">
                        @foreach ($user->parents as $parent)
                            <span class="inline-flex items-center rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700">
                                {{ $parent->name }}
                                <span class="ml-1 text-indigo-400">{{ $parent->email }}</span>
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif

            <p class="mt-2 text-xs text-gray-400">Name/E-Mail/Eltern werden im Benutzer-Bereich gepflegt; die Gruppe ergibt sich aus der Rolle des Benutzers.</p>
        </div>

        <form method="POST"
              action="{{ route('module.schulkantine.eaters.update', $user) }}"
              class="space-y-6 rounded-xl border border-gray-200 bg-white p-6">
            @csrf
            @method('PUT')

            {{-- Sonderkost: Allergene --}}
            <div>
                <x-input-label value="Allergien (verträgt diese Allergene NICHT)" />
                <div class="mt-2 grid grid-cols-1 gap-x-4 gap-y-2 sm:grid-cols-2">
                    @foreach ($allergens as $allergen)
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="allergens[]" value="{{ $allergen->id }}" @checked(in_array($allergen->id, $selAllergens))
                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span><span class="font-medium text-gray-400">{{ $allergen->code }}</span> {{ $allergen->name }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- Sonderkost: Diäten --}}
            <div>
                <x-input-label value="Diäten (Essen muss dafür geeignet sein)" />
                <div class="mt-2 flex flex-wrap gap-x-4 gap-y-2">
                    @foreach ($diets as $diet)
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="diets[]" value="{{ $diet->id }}" @checked(in_array($diet->id, $selDiets))
                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            {{ $diet->name }}
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    <x-module-icon name="save" class="text-base" />
                    Speichern
                </button>
                <a href="{{ route('module.schulkantine.eaters.index') }}"
                   class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700">
                    <x-module-icon name="x" class="text-base" />
                    Abbrechen
                </a>
            </div>
        </form>

        {{-- NFC-Chips (getrennt von der Sonderkost, eigene Aktionen) --}}
        <div class="rounded-xl border border-gray-200 bg-white p-6">
            <h2 class="text-lg font-semibold text-gray-800">NFC-Chips</h2>

            @if ($isOgs)
                <p class="mt-2 text-sm text-gray-400">OGS-Kind – kein Chip nötig. Die Ausgabe läuft über die OGS-Sammelliste / händisch.</p>
            @else
                {{-- Vorhandene Chips --}}
                @forelse ($chips as $chip)
                    <div class="mt-3 flex flex-wrap items-center justify-between gap-2 rounded-lg border px-3 py-2
                                {{ $chip->isSchool() ? 'border-amber-200 bg-amber-50' : 'border-green-200 bg-green-50' }}">
                        <div class="text-sm">
                            @if ($chip->isSchool())
                                <span class="font-medium text-amber-900">🔒 Schul-Chip</span>
                                <span class="font-mono text-amber-800">{{ $chip->uid }}</span>
                                @if ($chip->deposit > 0)
                                    <span class="text-amber-700">· Pfand {{ number_format((float) $chip->deposit, 2, ',', '.') }} €</span>
                                @endif
                                @if ($chip->lent_at)
                                    <span class="text-xs text-amber-600">· ausgegeben {{ $chip->lent_at->format('d.m.Y') }}</span>
                                @endif
                            @else
                                <span class="font-medium text-green-800">✓ Eigener Chip</span>
                                <span class="font-mono text-green-700">{{ $chip->uid }}</span>
                            @endif
                        </div>
                        <div class="flex items-center gap-3">
                            @if ($chip->isSchool())
                                <form method="POST" action="{{ route('module.schulkantine.eaters.chip.return', $chip) }}"
                                      onsubmit="return confirm('Schul-Chip zurücknehmen? Die Pfand-Rückgabe erscheint in der Abrechnung dieses Monats.')">
                                    @csrf
                                    <button type="submit" class="text-xs font-medium text-amber-700 hover:text-amber-900">zurücknehmen</button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('module.schulkantine.eaters.chip.remove', $chip) }}"
                                  onsubmit="return confirm('Chip endgültig entfernen (ohne Pfand-Buchung)?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-xs font-medium text-gray-400 hover:text-red-600">entfernen</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="mt-2 text-sm text-gray-400">Noch kein Chip zugeordnet.</p>
                @endforelse

                {{-- Schul-Chip ausgeben --}}
                <div class="mt-4 border-t border-gray-100 pt-4"
                     x-data="{
                        msg: '', ok: false,
                        supported: typeof NDEFReader !== 'undefined',
                        async scan() {
                            if (!this.supported) { this.ok=false; this.msg='Web-NFC geht nur auf einem Gerät mit NFC (Android/Chrome). Du kannst die Kennung auch manuell eintragen.'; return; }
                            try {
                                this.ok=false; this.msg='Chip jetzt an das Gerät halten …';
                                const reader = new NDEFReader();
                                await reader.scan();
                                reader.onreading = (e) => { this.$refs.uid.value = e.serialNumber || ''; this.ok=true; this.msg='Chip gelesen: ' + (e.serialNumber || '(ohne Kennung)'); };
                                reader.onreadingerror = () => { this.ok=false; this.msg='Konnte den Chip nicht lesen – nochmal.'; };
                            } catch (err) { this.ok=false; this.msg='Scan nicht möglich: ' + (err && err.message ? err.message : err); }
                        }
                     }">
                    <x-input-label value="Schul-Chip ausgeben" />
                    <form method="POST" action="{{ route('module.schulkantine.eaters.chip.issue', $user) }}" class="mt-2">
                        @csrf
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                            <input type="text" name="nfc_uid" x-ref="uid" placeholder="Chip-Kennung (UID)"
                                   class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:max-w-xs">
                            <button type="button" @click="scan()"
                                    class="inline-flex items-center justify-center gap-1.5 rounded-md border border-indigo-300 bg-white px-3 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-50">
                                <x-module-icon name="search" class="text-base" /> Chip scannen
                            </button>
                            <button type="submit"
                                    class="inline-flex items-center justify-center gap-1.5 rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                                Ausgeben
                            </button>
                        </div>
                        <label class="mt-2 inline-flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="nfc_deposit" value="1" checked
                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span><strong>5 € Pfand</strong> berechnen (Ausgabe-Monat zählt für die Abrechnung) – für selbst mitgebrachte Chips ausschalten</span>
                        </label>
                    </form>
                    <p class="mt-1 text-xs" :class="ok ? 'text-green-600' : 'text-gray-500'" x-text="msg"></p>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
