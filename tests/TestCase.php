<?php

namespace Tests;

use App\Services\PuntopanConexion;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Fuera de la red de la oficina el servidor remoto de `puntopan` es
        // inalcanzable. Con PUNTOPAN_FORCE_LOCAL=true (ver phpunit.xml) la suite
        // completa corre contra la copia local, incluidas las llamadas directas
        // a DB::connection('puntopan') que hacen los propios tests.
        if (config('database.puntopan_respaldo.forzado')) {
            $this->app->make(PuntopanConexion::class)->aplicarLocal();
        }
    }
}
