<?php

namespace Intranet\Modules\Schulkantine\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eine Gericht-Kategorie. Steuert spontane Abholung und die Reihenfolge.
 */
class Category extends Model
{
    protected $table = 'kantine_categories';

    protected $fillable = [
        'name',
        'allows_walkin',
        'sort_order',
        'color',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'allows_walkin' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
