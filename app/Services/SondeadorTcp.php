<?php

namespace App\Services;

/**
 * Comprobación barata (TCP) de que un host:puerto acepta conexiones.
 *
 * Se usa para el servidor remoto de `puntopan`: abre un socket con un timeout
 * corto y lo cierra, sin llegar a autenticar contra MySQL. Así la app falla en
 * `timeout_sondeo` segundos en lugar de quedarse colgada esperando al timeout
 * del sistema operativo.
 */
class SondeadorTcp
{
    public function disponible(string $host, int $puerto, int $timeout): bool
    {
        if ($host === '' || $puerto <= 0) {
            return false;
        }

        $socket = @fsockopen($host, $puerto, $errno, $errstr, max(1, $timeout));

        if ($socket === false) {
            return false;
        }

        fclose($socket);

        return true;
    }
}
