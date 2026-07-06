<?php

use Illuminate\Support\Facades\Route;
use Intranet\Modules\Schulkantine\Http\Controllers\CategoryController;
use Intranet\Modules\Schulkantine\Http\Controllers\ChipController;
use Intranet\Modules\Schulkantine\Http\Controllers\CustomerGroupController;
use Intranet\Modules\Schulkantine\Http\Controllers\DashboardController;
use Intranet\Modules\Schulkantine\Http\Controllers\DishController;
use Intranet\Modules\Schulkantine\Http\Controllers\EaterController;
use Intranet\Modules\Schulkantine\Http\Controllers\GuideController;
use Intranet\Modules\Schulkantine\Http\Controllers\MenuController;
use Intranet\Modules\Schulkantine\Http\Controllers\OrderController;
use Intranet\Modules\Schulkantine\Http\Controllers\RatingController;
use Intranet\Modules\Schulkantine\Http\Controllers\ReportController;
use Intranet\Modules\Schulkantine\Http\Controllers\SeasonController;
use Intranet\Modules\Schulkantine\Http\Controllers\ServingController;
use Intranet\Modules\Schulkantine\Http\Controllers\SonderkostController;

/*
 | Routen des Schulkantine-Moduls.
 |
 | Konvention (wie bei allen Modulen):
 |  - URL-Präfix:  modules/schulkantine
 |  - Namen:       module.schulkantine.*
 |  - Middleware:  'web' + 'auth'   (Session, CSRF, nur eingeloggt)
 |
 | Die Landing-Page des Moduls ist die Route "module.schulkantine.index".
*/
Route::middleware(['web', 'auth'])
    ->prefix('modules/schulkantine')
    ->name('module.schulkantine.')
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('index');

        // Admin-Anleitung: online lesen + als PDF laden (Zugriff nur Admin, im Controller geprüft).
        Route::get('anleitung', [GuideController::class, 'index'])->name('guide.index');
        Route::get('anleitung/pdf', [GuideController::class, 'pdf'])->name('guide.pdf');

        // Saison-Verwaltung (Schuljahr + Öffnungskalender). Zugriff nur für Admins (im Controller geprüft).
        Route::get('saisons', [SeasonController::class, 'index'])->name('seasons.index');
        Route::get('saisons/neu', [SeasonController::class, 'create'])->name('seasons.create');
        Route::post('saisons', [SeasonController::class, 'store'])->name('seasons.store');
        Route::get('saisons/{season}', [SeasonController::class, 'show'])->name('seasons.show');
        Route::get('saisons/{season}/bearbeiten', [SeasonController::class, 'edit'])->name('seasons.edit');
        Route::put('saisons/{season}', [SeasonController::class, 'update'])->name('seasons.update');
        Route::delete('saisons/{season}', [SeasonController::class, 'destroy'])->name('seasons.destroy');

        // Schließtage einer Saison
        Route::post('saisons/{season}/schliesstage', [SeasonController::class, 'storeClosedDay'])->name('seasons.closed-days.store');
        Route::delete('saisons/{season}/schliesstage/{closedDay}', [SeasonController::class, 'destroyClosedDay'])->name('seasons.closed-days.destroy');

        // Ferien & Feiertage per API ziehen
        Route::post('saisons/{season}/ferien-import', [SeasonController::class, 'importHolidays'])->name('seasons.import');

        // Kundengruppen (3 feste Gruppen – nur Betriebsparameter editierbar)
        Route::get('kundengruppen', [CustomerGroupController::class, 'index'])->name('customer-groups.index');
        Route::get('kundengruppen/{customerGroup}/bearbeiten', [CustomerGroupController::class, 'edit'])->name('customer-groups.edit');
        Route::put('kundengruppen/{customerGroup}', [CustomerGroupController::class, 'update'])->name('customer-groups.update');

        // Kategorien
        Route::get('kategorien', [CategoryController::class, 'index'])->name('categories.index');
        Route::get('kategorien/neu', [CategoryController::class, 'create'])->name('categories.create');
        Route::post('kategorien', [CategoryController::class, 'store'])->name('categories.store');
        Route::get('kategorien/{category}/bearbeiten', [CategoryController::class, 'edit'])->name('categories.edit');
        Route::put('kategorien/{category}', [CategoryController::class, 'update'])->name('categories.update');
        Route::delete('kategorien/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

        // Gerichte
        Route::get('gerichte', [DishController::class, 'index'])->name('dishes.index');
        Route::get('gerichte/neu', [DishController::class, 'create'])->name('dishes.create');
        Route::post('gerichte', [DishController::class, 'store'])->name('dishes.store');
        Route::get('gerichte/{dish}/bearbeiten', [DishController::class, 'edit'])->name('dishes.edit');
        Route::put('gerichte/{dish}', [DishController::class, 'update'])->name('dishes.update');
        Route::delete('gerichte/{dish}', [DishController::class, 'destroy'])->name('dishes.destroy');

        // Speiseplan (Menü je Öffnungstag & Bestellmodus)
        Route::get('speiseplan', [MenuController::class, 'index'])->name('menus.index');
        Route::post('speiseplan', [MenuController::class, 'store'])->name('menus.store');
        Route::post('speiseplan/freigabe', [MenuController::class, 'releaseWeek'])->name('menus.release');
        Route::delete('speiseplan/{menu}', [MenuController::class, 'destroy'])->name('menus.destroy');

        // Vorbestellung (für jeden eingeloggten Nutzer – sich selbst & seine Kinder)
        Route::get('bestellen', [OrderController::class, 'index'])->name('orders.index');
        Route::post('bestellen', [OrderController::class, 'store'])->name('orders.store');
        Route::post('bestellen/abo', [OrderController::class, 'subscription'])->name('orders.subscription');

        // Ausgabe & Betrieb (Phase 4) – Küchen-/Ausgabepersonal (Zugriff im Controller geprüft).
        Route::get('ausgabe', [ServingController::class, 'index'])->name('servings.index');
        Route::get('ausgabe/mengen', [ServingController::class, 'quantities'])->name('servings.quantities');
        Route::get('ausgabe/mengen/pdf', [ServingController::class, 'mengenPdf'])->name('servings.mengen.pdf');
        Route::get('ausgabe/no-shows', [ServingController::class, 'noShows'])->name('servings.noshows');
        Route::post('ausgabe/abhaken', [ServingController::class, 'toggle'])->name('servings.toggle');
        Route::post('ausgabe/lookup', [ServingController::class, 'lookup'])->name('servings.lookup');
        Route::post('ausgabe/lookup-esser', [ServingController::class, 'lookupEater'])->name('servings.lookup-eater');
        Route::post('ausgabe/ausgeben', [ServingController::class, 'serveConfirm'])->name('servings.confirm');
        Route::post('ausgabe/spontan', [ServingController::class, 'spontaneous'])->name('servings.spontaneous');
        Route::delete('ausgabe/{serving}', [ServingController::class, 'destroy'])->name('servings.destroy');

        // OGS-Sammelliste – OGS-Betreuer (Zugriff im Controller geprüft).
        Route::get('ogs-sammelliste', [ServingController::class, 'ogsList'])->name('servings.ogs');

        // Auswertung & Abrechnung (Phase 5) – nur Admin (im Controller geprüft).
        Route::get('auswertung', [ReportController::class, 'index'])->name('reports.index');
        Route::get('auswertung/csv', [ReportController::class, 'csv'])->name('reports.csv');
        Route::get('auswertung/person/{user}', [ReportController::class, 'show'])->name('reports.show');
        Route::get('auswertung/pdf', [ReportController::class, 'pdf'])->name('reports.pdf');
        Route::post('auswertung/{user}/bezahlt', [ReportController::class, 'markPaid'])->name('reports.paid');
        Route::delete('auswertung/{user}/bezahlt', [ReportController::class, 'unmarkPaid'])->name('reports.unpaid');

        // Bewertung / Feedback (Phase 6).
        // „Essen bewerten": jeder Nutzer bewertet sich + seine Kinder (Daumen, jederzeit änderbar).
        Route::get('bewertung', [RatingController::class, 'index'])->name('ratings.index');
        Route::post('bewertung/{serving}', [RatingController::class, 'rate'])->name('ratings.rate');
        Route::delete('bewertung/{serving}', [RatingController::class, 'destroy'])->name('ratings.destroy');
        // „Bewertungen" (aggregiert, anonym) – nur Küchen-/Ausgabepersonal (im Controller geprüft).
        Route::get('bewertungen', [RatingController::class, 'report'])->name('ratings.report');

        // „Meine Daten" (Selbstbedienung: ich + meine Kinder) – jeder Nutzer.
        Route::get('meine-sonderkost', [SonderkostController::class, 'index'])->name('sonderkost.index');
        Route::put('meine-sonderkost/{user}', [SonderkostController::class, 'update'])->name('sonderkost.update');
        // Budget (Spontankäufe) + Kategorie-Freigaben eines Kindes – nur Eltern.
        Route::post('meine-sonderkost/{user}/limits', [SonderkostController::class, 'saveLimits'])->name('sonderkost.limits');

        // Eigene NFC-Chips (Selbstbedienung: ich + meine Kinder) – jeder Nutzer.
        // Die Anzeige ist in „Meine Daten" (sonderkost) integriert; hier nur die Aktionen.
        // Registrieren erlaubt beliebig viele EIGENE Chips; Schul-Chips sind readonly.
        Route::post('meine-chips/{user}', [ChipController::class, 'register'])->name('chips.register');
        Route::delete('meine-chips/chip/{chip}', [ChipController::class, 'remove'])->name('chips.remove');

        // Teilnehmer (= Benutzer). Angelegt werden Benutzer über den Benutzer-Import;
        // hier pflegt man nur die Kantinen-Zusatzdaten (Gruppe je Saison, Sonderkost).
        Route::get('teilnehmer', [EaterController::class, 'index'])->name('eaters.index');
        Route::get('teilnehmer/{user}/bearbeiten', [EaterController::class, 'edit'])->name('eaters.edit');
        Route::put('teilnehmer/{user}', [EaterController::class, 'update'])->name('eaters.update');

        // Schul-Chips (nur Verwaltung): ausgeben (mit Pfand), zurücknehmen, entfernen.
        Route::post('teilnehmer/{user}/chip', [EaterController::class, 'issueChip'])->name('eaters.chip.issue');
        Route::post('teilnehmer/chip/{chip}/zurueck', [EaterController::class, 'returnChip'])->name('eaters.chip.return');
        Route::delete('teilnehmer/chip/{chip}', [EaterController::class, 'removeChip'])->name('eaters.chip.remove');
    });
