<?php

namespace Intranet\Modules\Schulkantine\Support;

use App\Models\User;

/**
 * Zugriffsregeln für die Betriebs-Phase (Ausgabe & Betrieb).
 *
 * Die Rechte hängen an den Betriebs-Rollen (siehe Rollen-Migration). Der
 * Voll-Zugriff läuft weiterhin über das Core-Admin-Flag (User::isAdmin()).
 * Die Prüfungen sind bewusst query-basiert, damit sie unabhängig davon
 * funktionieren, ob die roles-Relation schon geladen ist.
 */
class Access
{
    public const ROLE_KOCH = 'kantine_koch';

    public const ROLE_KELLNER = 'kantine_kellner';

    public const ROLE_OGS_BETREUER = 'kantine_ogs_betreuer';

    /** Hat der Benutzer mindestens eine der genannten Rollen? */
    public static function hasAnyRole(?User $user, string ...$roleIds): bool
    {
        if (! $user) {
            return false;
        }

        // Spalte qualifizieren: die roles()-Relation joint user_roles, beide
        // Tabellen haben ein role_id (sonst „ambiguous column name").
        return $user->roles()->whereIn('roles.role_id', $roleIds)->exists();
    }

    /** Ausgabelisten & Mengen ansehen: Admin, Koch oder Kellner. */
    public static function canViewServings(?User $user): bool
    {
        return (bool) $user?->isAdmin()
            || self::hasAnyRole($user, self::ROLE_KOCH, self::ROLE_KELLNER);
    }

    /** Ausgabe abhaken / spontane Abholung erfassen: Admin oder Kellner. */
    public static function canServe(?User $user): bool
    {
        return (bool) $user?->isAdmin()
            || self::hasAnyRole($user, self::ROLE_KELLNER);
    }

    /**
     * Darf der Benutzer die Verwaltungsseite hinter diesem Menüpunkt öffnen?
     * Entscheidet wie im übrigen Intranet über die Rollen am Menüpunkt
     * (Verwaltung → Module), Admins immer. Explizit im Controller statt nur
     * über die Core-Prüfung, weil manche Routen keinem Menüpunkt zugeordnet
     * sind (z. B. Menü-Vorlagen gehören zum Speiseplan) und sonst nur die
     * Sichtbarkeit des ganzen Moduls zählen würde.
     */
    public static function darfMenuepunkt(?User $user, string $key): bool
    {
        if ((bool) $user?->isAdmin()) {
            return true;
        }
        if (! $user) {
            return false;
        }

        $item = \App\Models\ModuleMenuItem::query()
            ->whereHas('module', fn ($m) => $m->where('key', 'schulkantine')->where('is_enabled', true))
            ->where('key', $key)
            ->first();

        return $item !== null && $item->isVisibleTo($user);
    }

    /** OGS-Sammelliste ansehen: Admin oder OGS-Betreuer. */
    public static function canViewOgsList(?User $user): bool
    {
        return (bool) $user?->isAdmin()
            || self::hasAnyRole($user, self::ROLE_OGS_BETREUER);
    }
}
