<?php

namespace Intranet\Modules\Schulkantine\Models;

use App\Models\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eine Kundengruppe. Ihr Bestellmodus entscheidet, WIE bestellt wird:
 *  - ja_nein: nur „isst heute: ja/nein" (z. B. OGS-Kinder)
 *  - menue:   Auswahl aus dem Menüplan (z. B. ab Klasse 5, Personal)
 *
 * `role_id` ist die Standard-Rolle: Benutzer mit dieser Rolle werden dieser
 * Gruppe zugeordnet. Eine Rolle gehört zu höchstens einer Gruppe.
 */
class CustomerGroup extends Model
{
    protected $table = 'kantine_customer_groups';

    public const MODE_JA_NEIN = 'ja_nein';

    public const MODE_MENUE = 'menue';

    protected $fillable = [
        'name',
        'ordering_mode',
        'pickup_from',
        'pickup_to',
        'is_active',
        'role_id',
    ];

    /** Die Standard-Rolle dieser Gruppe (optional). */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id', 'role_id');
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
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
