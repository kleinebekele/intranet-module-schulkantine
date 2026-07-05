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
    ];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
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
