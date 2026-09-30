<?php

namespace Intranet\Modules\Schulkantine\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Simulierter Essensvertrag zum Testen (nur Konten ohne Linear-Herkunft).
 * Wirkt wie die Rolle `kantine_vertrag_<art>` aus dem Linear-Import.
 */
class TestVertrag extends Model
{
    protected $table = 'kantine_test_contracts';

    protected $fillable = ['user_id', 'art'];

    protected function casts(): array
    {
        return ['art' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
