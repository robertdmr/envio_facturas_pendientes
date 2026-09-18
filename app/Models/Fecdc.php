<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Fecdc extends Model
{
    protected $connection = 'puntopan';

    protected $table = 'fecdc';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $guarded = [];
}
