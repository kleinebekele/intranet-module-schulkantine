<?php

namespace Intranet\Modules\Schulkantine\Models;

use Illuminate\Database\Eloquent\Model;

/** Ein kennzeichnungspflichtiger Zusatzstoff (Referenzliste, siehe Migration). */
class Additive extends Model
{
    protected $table = 'kantine_additives';

    public $timestamps = false;

    protected $fillable = ['code', 'name'];
}
