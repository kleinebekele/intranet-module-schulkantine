<?php

namespace Intranet\Modules\Schulkantine\Support;

use App\Models\Role;
use App\Models\User;

/**
 * Essen darf nur, wer in Linear einen laufenden Essensvertrag hat.
 *
 * Die Verträge liefert der Linear-Abgleich (Modul Verwaltung) als Rollen
 * `kantine_vertrag_<art>` am Esser. Solange keine dieser Rollen Mitglieder hat
 * (Abgleich noch nie scharf gelaufen), greift die Sperre nicht – sonst dürfte
 * nach dem Einspielen niemand mehr bestellen.
 */
class Essensvertrag
{
    public const PRAEFIX = 'kantine_vertrag_';

    public const HINWEIS = 'Für %s liegt kein Essensvertrag vor. Bitte im Sekretariat nachfragen – '
        .'danach kann bestellt werden.';

    private static ?bool $aktiv = null;

    /** Gibt es überhaupt schon Verträge aus Linear? */
    public static function aktiv(): bool
    {
        return self::$aktiv ??= Role::where('role_id', 'like', self::PRAEFIX.'%')->whereHas('users')->exists();
    }

    public static function hat(User $esser): bool
    {
        if (! self::aktiv()) {
            return true;
        }

        return $esser->roles->contains(fn (Role $r) => str_starts_with($r->role_id, self::PRAEFIX));
    }

    public static function hinweis(User $esser): string
    {
        return sprintf(self::HINWEIS, $esser->name);
    }
}
