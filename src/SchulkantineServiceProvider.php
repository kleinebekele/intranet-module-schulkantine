<?php

namespace Intranet\Modules\Schulkantine;

use App\Models\User;
use App\Modules\Support\ModuleManifest;
use App\Modules\Support\ModuleServiceProvider;
use Intranet\Modules\Schulkantine\Models\Allergen;
use Intranet\Modules\Schulkantine\Models\Diet;

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
    }

    public function manifest(): ModuleManifest
    {
        return ModuleManifest::make('schulkantine', 'Schulkantine', icon: 'restaurant')
            ->item('index', 'Übersicht', 'module.schulkantine.index')
            ->item('orders', 'Essen bestellen', 'module.schulkantine.orders.index')
            ->item('sonderkost', 'Meine Sonderkost', 'module.schulkantine.sonderkost.index')
            ->item('seasons', 'Saisons & Kalender', 'module.schulkantine.seasons.index')
            ->item('customer-groups', 'Kundengruppen', 'module.schulkantine.customer-groups.index')
            ->item('categories', 'Kategorien', 'module.schulkantine.categories.index')
            ->item('dishes', 'Gerichte', 'module.schulkantine.dishes.index')
            ->item('menus', 'Speiseplan', 'module.schulkantine.menus.index')
            ->item('eaters', 'Teilnehmer', 'module.schulkantine.eaters.index')
            ->item('settings', 'Einstellungen', 'module.schulkantine.settings.edit');
    }
}
