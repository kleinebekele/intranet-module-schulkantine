{{-- Hinweis, wenn die Zugriffsstufe für die Aktion der Seite nicht reicht
     (z. B. nur „lesen" auf „Essen bestellen"). Parameter: $route (Zielroute
     der Aktion), $text (was nicht geht). --}}
@unless (app(\App\Modules\Support\Modulzugriff::class)->darfRoute($route))
    <div class="mb-4 flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        <i class='bx bx-lock-alt text-base'></i>
        <span>Du hast hier nur Leserechte – {{ $text }}</span>
    </div>
@endunless
