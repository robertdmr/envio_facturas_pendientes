<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Factura extends Model
{
    protected $table = 'facturas';

    protected $primaryKey = 'NroFactura';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $guarded = [];

    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleFactura::class, 'NroFactura', 'NroFactura');
    }
}
