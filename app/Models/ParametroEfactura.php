<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParametroEfactura extends Model
{
    protected $table = 'parametros_efactura';

    protected $fillable = ['contribuyente_id', 'pass', 'timbrado', 'fec_inicio', 'sucursal'];

    protected function casts(): array
    {
        return [
            'fec_inicio' => 'datetime',
        ];
    }

    public static function registroUnico(): self
    {
        return static::query()->firstOrCreate([], [
            'contribuyente_id' => 33,
            'pass' => 'c6e8a6f0d815e1fd9358d76b208799efbb40c6d8155be29903dcbe5b99125c07',
            'timbrado' => '12558948',
            'fec_inicio' => '2021-08-25 00:00:00',
            'sucursal' => 'Central',
        ]);
    }
}
