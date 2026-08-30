<?php

namespace Intranet\Modules\Schulkantine\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * OGS-Saison-Abo. Existenz = „isst standardmäßig an allen Öffnungstagen".
 * Tages-Teilnahmen werden abgeleitet, nicht materialisiert (siehe Migration).
 */
class Subscription extends Model
{
    protected $table = 'kantine_subscriptions';

    protected $fillable = [
        'season_id',
        'user_id',
        'active',
        'weekdays',
    ];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'weekdays' => 'array'];
    }

    /**
     * Isst das Kind an diesem ISO-Wochentag (1 = Mo … 7 = So) standardmäßig?
     * Leeres/kein Muster = alle Öffnungstage (bisheriges Verhalten).
     */
    public function eatsWeekday(int $iso): bool
    {
        $wd = $this->weekdays;

        return empty($wd) || in_array($iso, $wd, true);
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
