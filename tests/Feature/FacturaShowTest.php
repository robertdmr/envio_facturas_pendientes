<?php

namespace Tests\Feature;

use App\Models\DetalleFactura;
use App\Models\Factura;
use Tests\TestCase;

class FacturaShowTest extends TestCase
{
    public function test_show_displays_header_and_detail_lines(): void
    {
        $nro = Factura::query()
            ->orderByDesc('FechaFactura')
            ->orderByDesc('NroFactura')
            ->value('NroFactura');
        $descripcion = DetalleFactura::query()
            ->where('NroFactura', $nro)
            ->orderBy('IdItem')
            ->value('Descripcion');

        $this->assertNotNull($nro);
        $this->assertNotNull($descripcion);

        $this->get('/facturas/'.$nro)
            ->assertOk()
            ->assertSee($nro)
            ->assertSee($descripcion);
    }

    public function test_show_returns_404_for_unknown_invoice(): void
    {
        $this->get('/facturas/ZZ-999-9999999')->assertNotFound();
    }
}
