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

    public function cdc(): ?string
    {
        $datos = json_decode((string) $this->respuesta, true);

        if (! is_array($datos)) {
            return null;
        }

        $cdc = $datos['cdc'] ?? null;

        return is_string($cdc) && $cdc !== '' ? $cdc : null;
    }
}
