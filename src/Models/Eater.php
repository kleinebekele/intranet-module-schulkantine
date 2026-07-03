<?php

namespace Intranet\Modules\Schulkantine\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Ein Esser / Teilnehmer.
 *
 *  - user_id (optional): eigenes Login, falls der Esser selbst bestellt.
 *  - guardians: Intranet-User (Eltern), die für diesen Esser bestellen dürfen.
 *  - customerGroups: Gruppen-Zugehörigkeit je Saison (Pivot enthält season_id).
 *  - allergens/diets: hinterlegte Sonderkost.
 */
class Eater extends Model
{
    protected $table = 'kantine_eaters';

    protected $fillable = [
        'external_id',
        'user_id',
        'name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** Eigenes Login (optional). */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Vormunde (Eltern), die für diesen Esser bestellen dürfen. */
    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'kantine_guardianships', 'eater_id', 'user_id');
    }

    /** Gruppen je Saison – der Pivot trägt die season_id. */
    public function customerGroups(): BelongsToMany
    {
        return $this->belongsToMany(CustomerGroup::class, 'kantine_eater_season_group', 'eater_id', 'customer_group_id')
            ->withPivot('season_id');
    }

    public function allergens(): BelongsToMany
    {
        return $this->belongsToMany(Allergen::class, 'kantine_eater_allergen', 'eater_id', 'allergen_id');
    }

    public function diets(): BelongsToMany
    {
        return $this->belongsToMany(Diet::class, 'kantine_eater_diet', 'eater_id', 'diet_id');
    }

    /** Die Kundengruppe dieses Essers in einer bestimmten Saison (oder null). */
    public function groupForSeason(?int $seasonId): ?CustomerGroup
    {
        if (! $seasonId) {
            return null;
        }

        return $this->customerGroups->firstWhere('pivot.season_id', $seasonId);
    }
}
