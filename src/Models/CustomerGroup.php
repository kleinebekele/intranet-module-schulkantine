<?php

namespace Intranet\Modules\Schulkantine\Models;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * Eine der DREI festen Esser-Gruppen. Ihr Bestellmodus entscheidet, WIE bestellt
 * wird (ja_nein = „isst heute: ja/nein", menue = Auswahl aus dem Menüplan).
 *
 * Die Zuordnung eines Benutzers ergibt sich AUS SEINEN ROLLEN (siehe forUser()),
 * nicht aus einer manuellen Zuweisung. `role_id` ist die fest verknüpfte Rolle.
 */
class CustomerGroup extends Model
{
    protected $table = 'kantine_customer_groups';

    public const MODE_JA_NEIN = 'ja_nein';

    public const MODE_MENUE = 'menue';

    /**
     * Die Rollen der drei festen Gruppen – NACH PRIORITÄT (erste passende
     * gewinnt). Jeder Benutzer hat mindestens 'user' → landet mindestens in
     * „Sonstige". Wer zusätzlich 'kantine_ogs' hat, ist OGS (höchste Priorität).
     */
    public const ROLE_PRIORITY = ['kantine_ogs', 'kantine_student', 'user'];

    protected $fillable = [
        'name',
        'ordering_mode',
        'pickup_from',
        'pickup_to',
        'role_id',
    ];

    /** Die fest verknüpfte Rolle dieser Gruppe. */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id', 'role_id');
    }

    /**
     * Leitet die Kundengruppe eines Benutzers aus seinen Rollen ab
     * (Priorität OGS → Schüler → Sonstige). Für Listen die drei Gruppen einmal
     * vorab laden und als $groups (keyBy 'role_id') übergeben, um N+1 zu sparen.
     */
    public static function forUser(User $user, ?Collection $groups = null): ?self
    {
        $groups ??= self::all()->keyBy('role_id');
        $userRoleIds = $user->roles->pluck('role_id');

        foreach (self::ROLE_PRIORITY as $roleId) {
            if ($userRoleIds->contains($roleId) && $groups->has($roleId)) {
                return $groups->get($roleId);
            }
        }

        return null;
    }

    /** @return array<string, string> */
    public static function orderingModes(): array
    {
        return [
            self::MODE_JA_NEIN => 'Essen ja / nein',
            self::MODE_MENUE => 'Menü-Auswahl',
        ];
    }

    public function orderingModeLabel(): string
    {
        return self::orderingModes()[$this->ordering_mode] ?? $this->ordering_mode;
    }
}
