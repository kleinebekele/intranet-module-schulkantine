<?php

use Illuminate\Support\Facades\Route;
use Intranet\Modules\Schulkantine\Http\Controllers\CategoryController;
use Intranet\Modules\Schulkantine\Http\Controllers\CustomerGroupController;
use Intranet\Modules\Schulkantine\Http\Controllers\DashboardController;
use Intranet\Modules\Schulkantine\Http\Controllers\DishController;
use Intranet\Modules\Schulkantine\Http\Controllers\EaterController;
use Intranet\Modules\Schulkantine\Http\Controllers\MenuController;
use Intranet\Modules\Schulkantine\Http\Controllers\SeasonController;

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
        Route::delete('speiseplan/{menu}', [MenuController::class, 'destroy'])->name('menus.destroy');

        // Teilnehmer (= Benutzer). Angelegt werden Benutzer über den Benutzer-Import;
        // hier pflegt man nur die Kantinen-Zusatzdaten (Gruppe je Saison, Sonderkost).
        Route::get('teilnehmer', [EaterController::class, 'index'])->name('eaters.index');
        Route::get('teilnehmer/{user}/bearbeiten', [EaterController::class, 'edit'])->name('eaters.edit');
        Route::put('teilnehmer/{user}', [EaterController::class, 'update'])->name('eaters.update');
    });
