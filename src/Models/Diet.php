<?php

namespace Intranet\Modules\Schulkantine\Models;

use Illuminate\Database\Eloquent\Model;

/** Eine Diät/Ernährungsform, für die ein Gericht geeignet sein kann. */
class Diet extends Model
{
    protected $table = 'kantine_diets';

    public $timestamps = false;

    protected $fillable = ['name'];
}
