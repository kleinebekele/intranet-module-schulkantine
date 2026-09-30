<?php

namespace Intranet\Modules\Schulkantine\Support;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Intranet\Modules\Schulkantine\Models\TestVertrag;

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

        return self::arten($esser) !== [];
    }

    /**
     * Vertragsarten des Essers: aus Linear (Rollen) plus ggf. Testvertrag.
     *
     * @return list<int>
     */
    public static function arten(User $esser): array
    {
        $arten = [];
        foreach ($esser->roles as $rolle) {
            if (str_starts_with($rolle->role_id, self::PRAEFIX)) {
                $arten[] = (int) substr($rolle->role_id, strlen(self::PRAEFIX));
            }
        }
        if (($test = self::testVertrag($esser)) !== null) {
            $arten[] = $test;
        }

        return array_values(array_unique($arten));
    }

    /** Simulierter Vertrag (nur Konten ohne Linear-Herkunft), sonst null. */
    public static function testVertrag(User $esser): ?int
    {
        if (filled($esser->externe_id) || ! Schema::hasTable('kantine_test_contracts')) {
            return null;
        }
        $art = TestVertrag::where('user_id', $esser->id)->value('art');

        return $art !== null ? (int) $art : null;
    }

    public static function hinweis(User $esser): string
    {
        return sprintf(self::HINWEIS, $esser->name);
    }
}
