<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2">
            <x-module-icon name="cart" class="text-2xl text-indigo-600" />
            <h1 class="text-xl font-semibold text-gray-800">Bestell-Terminal</h1>
        </div>
    </x-slot>

    <div class="space-y-4">
        <div class="rounded-xl border border-gray-200 bg-white p-6">
            <h2 class="text-base font-semibold text-gray-800">Terminal-Adresse</h2>
            <p class="mt-1 text-sm text-gray-500">
                Diese Adresse an den Schul-Terminals als Startseite öffnen (am besten im Kiosk-Modus von Chrome oder Edge).
                Eine Intranet-Anmeldung ist dort nicht nötig – bestellt wird nach Chip-Anmeldung.
            </p>
            <div class="mt-3 flex flex-wrap items-center gap-3">
                <code class="rounded-lg bg-gray-100 px-3 py-2 text-sm text-gray-800">{{ $terminalUrl }}</code>
                <a href="{{ $terminalUrl }}" target="_blank" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Terminal öffnen</a>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-6">
            <h2 class="text-base font-semibold text-gray-800">Freigegebene Netze</h2>
            <p class="mt-1 text-sm text-gray-500">
                Nur von diesen Adressen aus ist das Terminal erreichbar. Eine Adresse oder ein Netz je Zeile, z. B.
                <code>192.168.10.0/24</code> oder eine einzelne IP; alles ab <code>#</code> ist Kommentar.
                Leer = nirgends erreichbar. Angemeldete Admins kommen immer hinein.
            </p>

            <div class="mt-4 rounded-lg border px-4 py-3 text-sm {{ $ipErlaubt ? 'border-green-200 bg-green-50 text-green-800' : 'border-amber-200 bg-amber-50 text-amber-800' }}">
                Deine Adresse, wie der Server sie sieht: <strong>{{ $ip }}</strong> –
                {{ $ipErlaubt ? 'freigegeben, ein Terminal hier käme hinein.' : 'nicht freigegeben.' }}
                <div class="mt-1 text-xs opacity-80">
                    Am besten an einem Schul-Terminal nachsehen, welche Adresse hier erscheint, und die Gegenprobe von außerhalb
                    (z. B. Handy im Mobilnetz) machen: dort muss eine andere Adresse stehen.
                </div>
            </div>

            @if ($errors->any())
                <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('module.schulkantine.bestellterminal.update') }}" class="mt-4">
                @csrf
                @method('PUT')
                <textarea name="netze" rows="5" class="w-full rounded-lg border-gray-300 font-mono text-sm"
                          placeholder="192.168.10.0/24  # Schulnetz">{{ old('netze', $netzeText) }}</textarea>
                @darfRoute('module.schulkantine.bestellterminal.update')
                    <div class="mt-3 flex justify-end">
                        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Speichern</button>
                    </div>
                @enddarfRoute
            </form>
        </div>
    </div>
</x-app-layout>
