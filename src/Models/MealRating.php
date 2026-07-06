<?php

namespace Intranet\Modules\Schulkantine\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eine Essens-Bewertung (Daumen hoch/runter) zu genau einer Ausgabe.
 *
 *   rating: 1 = Daumen runter   |   2 = Daumen hoch
 *
 * Siehe Migration für die Datenschutz-Regel: für das Personal wird nur
 * aggregiert je Gericht ausgewertet, nie personenbezogen.
 */
class MealRating extends Model
{
    protected $table = 'kantine_meal_ratings';

    public const DOWN = 1;

    public const UP = 2;

    protected $fillable = [
        'serving_id',
        'user_id',
        'dish_id',
        'date',
        'rating',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'rating' => 'integer',
        ];
    }

    public function serving(): BelongsTo
    {
        return $this->belongsTo(Serving::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function dish(): BelongsTo
    {
        return $this->belongsTo(Dish::class);
    }

    public function isUp(): bool
    {
        return $this->rating === self::UP;
    }

    public function isDown(): bool
    {
        return $this->rating === self::DOWN;
    }
}
