<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    protected $table = 'clientes';

    protected $primaryKey = 'IdCliente';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];
}
