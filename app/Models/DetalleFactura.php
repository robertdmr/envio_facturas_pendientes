<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetalleFactura extends Model
{
    protected $table = 'comandadet';

    protected $primaryKey = 'IdItem';

    public $timestamps = false;

    protected $guarded = [];

    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class, 'NroFactura', 'NroFactura');
    }
}
