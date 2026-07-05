<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2">
            <x-module-icon name="restaurant" class="text-2xl text-indigo-600" />
            <h1 class="text-xl font-semibold text-gray-800">Kantine – Einstellungen</h1>
        </div>
    </x-slot>

    <div class="max-w-2xl space-y-6">
        {{-- Erfolgsmeldung („Einstellungen gespeichert.") zeigt das App-Layout global. --}}
        <form method="POST" action="{{ route('module.schulkantine.settings.update') }}"
              class="space-y-6 rounded-xl border border-gray-200 bg-white p-6">
            @csrf
            @method('PUT')

            <div>
                <h2 class="text-sm font-semibold text-gray-700">Fristen</h2>
                <p class="mt-0.5 text-xs text-gray-400">
                    Beide Fristen werden gegen den Öffnungskalender gerechnet (geschlossene Tage werden übersprungen).
                </p>

                <div class="mt-3 grid gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="order_deadline_time" value="Bestellschluss (voriger Öffnungstag)" />
                        <x-text-input id="order_deadline_time" name="order_deadline_time" type="time" class="mt-1 block"
                                      :value="old('order_deadline_time', $settings->order_deadline_time)" required />
                        <p class="mt-1 text-xs text-gray-400">Bis wann am Vortag (Öffnungstag) bestellt/geändert werden darf. Standard 14:00.</p>
                        <x-input-error :messages="$errors->get('order_deadline_time')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="cancel_deadline_time" value="Abbestellen (am selben Tag)" />
                        <x-text-input id="cancel_deadline_time" name="cancel_deadline_time" type="time" class="mt-1 block"
                                      :value="old('cancel_deadline_time', $settings->cancel_deadline_time)" required />
                        <p class="mt-1 text-xs text-gray-400">Bis wann am Essenstag noch abbestellt werden darf (z. B. Kind krank). Standard 09:00.</p>
                        <x-input-error :messages="$errors->get('cancel_deadline_time')" class="mt-2" />
                    </div>
                </div>
            </div>

            <div class="border-t border-gray-100 pt-6">
                <h2 class="text-sm font-semibold text-gray-700">Wochen-Freigabe</h2>
                <div class="mt-3">
                    <x-input-label for="release_lead_weeks" value="Automatischer Vorlauf (Wochen)" />
                    <x-text-input id="release_lead_weeks" name="release_lead_weeks" type="number" min="0" max="52" class="mt-1 block w-32"
                                  :value="old('release_lead_weeks', $settings->release_lead_weeks)" required />
                    <p class="mt-1 text-xs text-gray-400">
                        Wie viele Wochen im Voraus automatisch zum Bestellen freigegeben werden. Einzelne Wochen lassen sich
                        im Speiseplan manuell früher freigeben oder zurückhalten.
                    </p>
                    <x-input-error :messages="$errors->get('release_lead_weeks')" class="mt-2" />
                </div>
            </div>

            <div class="flex items-center gap-3 border-t border-gray-100 pt-6">
                <x-primary-button class="gap-1.5">
                    <x-module-icon name="save" class="text-base" />
                    Speichern
                </x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
