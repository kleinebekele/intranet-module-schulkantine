<?php

namespace Intranet\Modules\Schulkantine\Models;

use Illuminate\Database\Eloquent\Model;

/** Ein EU-Allergen (Referenzliste, siehe Migration). */
class Allergen extends Model
{
    protected $table = 'kantine_allergens';

    public $timestamps = false;

    protected $fillable = ['code', 'name'];
}
