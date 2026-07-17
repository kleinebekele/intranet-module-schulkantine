<?php

namespace Intranet\Modules\Schulkantine\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Freie Zusatz-Info eines Teilnehmers (z. B. „Klasse 5").
 *
 * Die Info wird NICHT im Intranet gepflegt, sondern kommt aus dem CSV-Import
 * (InfoImporter). In der Teilnehmerliste ist sie reine Anzeige.
 */
class UserInfo extends Model
{
    protected $table = 'kantine_user_infos';

    protected $fillable = ['user_id', 'info'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
