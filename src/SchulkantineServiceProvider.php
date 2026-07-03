<?php

namespace Intranet\Modules\Schulkantine;

use App\Models\User;
use App\Modules\Support\ModuleManifest;
use App\Modules\Support\ModuleServiceProvider;
use Intranet\Modules\Schulkantine\Console\SyncEaters;
use Intranet\Modules\Schulkantine\Models\Eater;

/**
 * Anmelde-Klasse des Schulkantine-Moduls.
 *
 * Routen, Views und Migrationen lädt die Basisklasse automatisch anhand der
 * Ordnerstruktur – hier beschreiben wir nur das Manifest (Schlüssel, Name,
 * Icon und die Unterpunkte des linken Menüs).
 */
class SchulkantineServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        // Automatik: Jeder Intranet-Benutzer ist automatisch Teilnehmer (Esser).
        // So muss niemand die Teilnehmer doppelt pflegen – sie entstehen und
        // aktualisieren sich mit den Benutzern. Kinder ohne Account kommen
        // separat über den CSV-Import hinzu.
        User::created(function (User $user): void {
            Eater::firstOrCreate(
                ['user_id' => $user->id],
                ['name' => $user->name, 'is_active' => true],
            );
        });

        User::updated(function (User $user): void {
            if ($user->wasChanged('name')) {
                Eater::where('user_id', $user->id)->update(['name' => $user->name]);
            }
        });

        User::deleting(function (User $user): void {
            Eater::where('user_id', $user->id)->delete();
        });

        if ($this->app->runningInConsole()) {
            $this->commands([SyncEaters::class]);
        }
    }

    public function manifest(): ModuleManifest
    {
        return ModuleManifest::make('schulkantine', 'Schulkantine', icon: 'restaurant')
            ->item('index', 'Übersicht', 'module.schulkantine.index')
            ->item('seasons', 'Saisons & Kalender', 'module.schulkantine.seasons.index')
            ->item('customer-groups', 'Kundengruppen', 'module.schulkantine.customer-groups.index')
            ->item('categories', 'Kategorien', 'module.schulkantine.categories.index')
            ->item('dishes', 'Gerichte', 'module.schulkantine.dishes.index')
            ->item('eaters', 'Teilnehmer', 'module.schulkantine.eaters.index');
    }
}
