{{-- Wochenbudget eines Kindes. Erwartet $eater, $bud (Budget::ansicht), $weekStart, $money.
     $budgetNurAnsicht (Bestell-Terminal): nur anzeigen; sonst Knopf + Dialog (diese Woche oder
     dauerhaft) und offene Überschreitungen zum Bestätigen. --}}
@php
    $rest = $bud['betrag'] !== null ? round($bud['betrag'] - $bud['genutzt'], 2) : null;
    $offen = collect($bud['offen'] ?? []);
@endphp
@if ($budgetNurAnsicht ?? false)
    <div class="flex items-center gap-1.5">
        <span class="text-[11px] uppercase tracking-wide text-gray-400">Budget diese Woche</span>
        <span class="rounded-full px-2 py-0.5 text-sm font-bold {{ $rest !== null && $rest < 0 ? 'bg-red-50 text-red-700' : 'bg-amber-50 text-amber-700' }}">
            {{ $bud['betrag'] !== null ? $money($bud['betrag']).' · noch übrig '.$money($rest) : 'kein Limit' }}
        </span>
    </div>
@else
<div x-data="{ open: false }" class="flex flex-wrap items-center gap-1.5">
    @foreach ($offen as $u)
        <form method="POST" action="{{ route('module.schulkantine.sonderkost.ueberschreitung', ['user' => $eater, 'ueberschreitung' => $u->id]) }}"
              onsubmit="return confirm(@js('Überschreitung von '.$money($u->ueber).' am '.\Illuminate\Support\Carbon::parse($u->tag)->format('d.m.Y').' bestätigen? Danach kann an der Ausgabe wieder überschritten werden.'))"
              class="flex items-center gap-1 rounded-full bg-red-600 py-0.5 pl-2 pr-0.5 text-xs font-semibold text-white">
            @csrf
            <span class="whitespace-nowrap">Budget überschritten: {{ $money($u->ueber) }} am {{ \Illuminate\Support\Carbon::parse($u->tag)->format('d.m.') }}</span>
            <button type="submit" class="rounded-full bg-white px-2 py-0.5 font-bold text-red-700 hover:bg-red-50">bestätigen</button>
        </form>
    @endforeach
    <span class="text-[11px] uppercase tracking-wide text-gray-400">Budget KW {{ $weekStart->isoWeek() }}</span>
    <button type="button" @click="open = true"
            class="rounded-full border border-dashed border-amber-300 bg-amber-50 px-2 py-0.5 text-sm font-bold text-amber-700 hover:bg-amber-100"
            title="Wochenbudget für spontane Käufe ändern{{ $bud['betrag'] !== null ? ' – diese Woche schon '.$money($bud['genutzt']).' genutzt' : '' }}">
        {{ $bud['betrag'] !== null ? $money($bud['betrag']) : 'kein Limit' }}@if ($bud['nurWoche'])<span class="ml-1 text-[10px] font-medium">(nur diese Woche)</span>@endif
    </button>

    <div x-show="open" x-cloak @keydown.escape.window="open = false"
         class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display:none;">
        <div class="absolute inset-0 bg-black/40" @click="open = false"></div>
        <form method="POST" action="{{ route('module.schulkantine.sonderkost.budget', $eater) }}"
              x-data="{ geltung: '{{ $bud['nurWoche'] ? 'woche' : 'dauerhaft' }}' }"
              class="relative z-10 w-full max-w-sm rounded-2xl bg-white p-5 text-left shadow-xl">
            @csrf
            <input type="hidden" name="woche" value="{{ $weekStart->toDateString() }}">
            <h3 class="text-base font-semibold text-gray-800">Wochenbudget – {{ $eater->name }}</h3>
            <p class="mt-1 text-xs text-gray-500">
                So viel darf {{ $eater->name }} pro Woche spontan an der Ausgabe kaufen (z. B. Getränk, Nachschlag).
                Diese Woche schon genutzt: {{ $money($bud['genutzt']) }}.
            </p>
            <label class="mt-3 block text-xs font-medium text-gray-600">Betrag (€)</label>
            <input type="text" inputmode="decimal" name="betrag"
                   value="{{ $bud['betrag'] !== null ? number_format($bud['betrag'], 2, ',', '') : '' }}"
                   placeholder="0,00"
                   class="mt-1 w-32 rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <div class="mt-3 space-y-1.5 text-sm text-gray-700">
                <label class="flex items-center gap-2">
                    <input type="radio" name="geltung" value="woche" x-model="geltung" class="border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    nur für KW {{ $weekStart->isoWeek() }} ({{ $weekStart->format('d.m.') }}–{{ $weekStart->copy()->addDays(6)->format('d.m.') }})
                </label>
                <label class="flex items-center gap-2">
                    <input type="radio" name="geltung" value="dauerhaft" x-model="geltung" class="border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    dauerhaft für alle Wochen
                </label>
            </div>
            <p class="mt-2 text-[11px] text-gray-400">
                Dauerhaft jetzt: {{ $bud['allgemein'] !== null ? $money($bud['allgemein']) : 'kein Limit' }} – auch unter „Meine Daten" einstellbar.
                <span x-show="geltung === 'woche'">Leer lassen = in dieser Woche wieder das dauerhafte Budget.</span>
                <span x-show="geltung === 'dauerhaft'">Leer = 0 € (keine spontanen Käufe).</span>
            </p>
            <label class="mt-3 flex items-start gap-2 text-sm text-gray-700">
                <input type="hidden" name="am_ausgabeterminal" value="0">
                <input type="checkbox" name="am_ausgabeterminal" value="1" @checked($bud['amAusgabeterminal'] ?? false)
                       class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                <span>Budget dem Ausgabe-Personal anzeigen</span>
            </label>
            <div class="mt-4 flex justify-end gap-2">
                <button type="button" @click="open = false" class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50">Abbrechen</button>
                <button type="submit" class="rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">Speichern</button>
            </div>
        </form>
    </div>
</div>
@endif
