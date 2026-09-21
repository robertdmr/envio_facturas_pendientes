<?php

namespace Tests\Support;

use App\Services\SondeadorTcp;

/**
 * Doble de SondeadorTcp: permite decidir desde el test si el servidor remoto
 * "responde" o no, sin depender de la red.
 */
class SondeadorTcpFalso extends SondeadorTcp
{
    public function __construct(public bool $vivo = false) {}

    public function disponible(string $host, int $puerto, int $timeout): bool
    {
        return $this->vivo;
    }
}
