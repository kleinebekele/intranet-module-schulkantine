<?php

namespace Intranet\Modules\Schulkantine;

use App\Models\User;
use App\Modules\Support\ModuleManifest;
use App\Modules\Support\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Intranet\Modules\Schulkantine\Models\Allergen;
use Intranet\Modules\Schulkantine\Models\Diet;
use Intranet\Modules\Schulkantine\Models\UserInfo;

/**
 * Anmelde-Klasse des Schulkantine-Moduls.
 *
 * Routen, Views und Migrationen lädt die Basisklasse automatisch anhand der
 * Ordnerstruktur – hier beschreiben wir nur das Manifest (Schlüssel, Name,
 * Icon und die Unterpunkte des linken Menüs) sowie die Kantine-Beziehungen,
 * die dem Core-Benutzer angehängt werden.
 */
class SchulkantineServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        // Jeder Esser IST ein Benutzer (eigener Account). Die Sonderkost hängt
        // direkt am Benutzer – wir hängen die Relations dem Core-User-Model
        // dynamisch an, ohne dieses Modell selbst zu verändern.
        // (Die Gruppen-Zuordnung wird NICHT gespeichert, sondern aus den Rollen
        //  abgeleitet – siehe CustomerGroup::forUser().)
        User::resolveRelationUsing('kantineAllergens', fn (User $user) => $user
            ->belongsToMany(Allergen::class, 'kantine_user_allergen', 'user_id', 'allergen_id'));

        User::resolveRelationUsing('kantineDiets', fn (User $user) => $user
            ->belongsToMany(Diet::class, 'kantine_user_diet', 'user_id', 'diet_id'));

        // Freie Zusatz-Info (z. B. „Klasse 5") – kommt ausschließlich aus dem
        // CSV-Import und hängt an der Kantine, nicht am Core-Benutzer.
        User::resolveRelationUsing('kantineInfo', fn (User $user) => $user
            ->hasOne(UserInfo::class, 'user_id'));

        if ($this->app->runningInConsole()) {
            $this->commands([
                \Intranet\Modules\Schulkantine\Console\Commands\SeedTestUsers::class,
                \Intranet\Modules\Schulkantine\Console\Commands\SeedOgsTestkinder::class,
                \Intranet\Modules\Schulkantine\Console\Commands\SeedDishes::class,
                \Intranet\Modules\Schulkantine\Console\Commands\ImportInfos::class,
            ]);

            // Stündlich nach neuen Teilnehmer-Info-CSVs schauen. Modul-lokal
            // angemeldet (Insel-Prinzip, kein Eingriff in den Core-Scheduler).
            // Voraussetzung am Server: ein Cron, der minütlich `artisan schedule:run`
            // ruft – bis dahin greift der Button „Jetzt importieren" in der
            // Teilnehmerliste.
            $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
                $schedule->command('kantine:import-infos')
                    ->hourly()
                    ->timezone('Europe/Berlin')
                    ->withoutOverlapping();
            });
        }
    }

    public function manifest(): ModuleManifest
    {
        return ModuleManifest::make('schulkantine', 'Schulkantine', icon: 'restaurant')
            ->item('orders', 'Essen bestellen', 'module.schulkantine.orders.index', icon: 'cart')
            ->item('sonderkost', 'Meine Daten', 'module.schulkantine.sonderkost.index', icon: 'diet')
            ->item('ratings', 'Essen bewerten', 'module.schulkantine.ratings.index', icon: 'like')
            ->item('servings', 'Ausgabe', 'module.schulkantine.servings.index', icon: 'serving')
            ->item('servings-terminal', 'Ausgabe Terminal', 'module.schulkantine.servings.terminal', icon: 'grid')
            ->item('ogs-list', 'OGS-Sammelliste', 'module.schulkantine.servings.ogs', icon: 'list')
            ->item('reports', 'Auswertung', 'module.schulkantine.reports.index', icon: 'chart')
            ->item('seasons', 'Saisons & Kalender', 'module.schulkantine.seasons.index', icon: 'calendar')
            ->item('customer-groups', 'Kundengruppen', 'module.schulkantine.customer-groups.index', icon: 'users')
            ->item('categories', 'Kategorien', 'module.schulkantine.categories.index', icon: 'category')
            ->item('dishes', 'Gerichte', 'module.schulkantine.dishes.index', icon: 'dish')
            ->item('menus', 'Speiseplan', 'module.schulkantine.menus.index', icon: 'menu-card')
            ->item('eaters', 'Teilnehmer', 'module.schulkantine.eaters.index', icon: 'user')
            ->item('guide', 'Anleitung', 'module.schulkantine.guide.index', icon: 'book', adminsOnly: true);
    }
}
