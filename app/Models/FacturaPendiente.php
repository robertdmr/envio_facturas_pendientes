<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FacturaPendiente extends Model
{
    protected $table = 'facturas_pendientes';

    protected $fillable = ['nrofactura', 'payload', 'enviado', 'respuesta'];

    protected function casts(): array
    {
        return [
            'enviado' => 'boolean',
        ];
    }
}
