<?php

namespace Tests\Feature;

use App\Models\Factura;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FacturaListTest extends TestCase
{
    public function test_index_shows_the_latest_invoice_number(): void
    {
        $nro = Factura::query()
            ->orderByDesc('FechaFactura')
            ->orderByDesc('NroFactura')
            ->value('NroFactura');

        $this->assertNotNull($nro);

        $this->get('/')
            ->assertOk()
            ->assertSee($nro);
    }

    public function test_index_shows_columns_from_comandadet_and_clientes(): void
    {
        $fila = DB::connection('puntopan')->table('facturas')
            ->join('clientes', 'clientes.IdCliente', '=', 'facturas.IdCliente')
            ->join('comandadet', 'comandadet.NroFactura', '=', 'facturas.NroFactura')
            ->whereNotNull('clientes.NombreEmpresa')
            ->orderByDesc('facturas.FechaFactura')
            ->orderByDesc('facturas.NroFactura')
            ->first(['facturas.NroFactura', 'clientes.NombreEmpresa']);

        $this->assertNotNull($fila);

        $this->get('/?q='.urlencode($fila->NroFactura))
            ->assertOk()
            ->assertSee($fila->NroFactura)
            ->assertSee($fila->NombreEmpresa)
            ->assertSee('Total')
            ->assertSee('Ítems');
    }

    public function test_index_filters_by_invoice_number(): void
    {
        $fila = DB::connection('puntopan')->table('facturas')
            ->join('clientes', 'clientes.IdCliente', '=', 'facturas.IdCliente')
            ->whereNotNull('clientes.NombreEmpresa')
            ->where('clientes.NombreEmpresa', '!=', 'SELECCIONAR CLIENTE')
            ->whereNotNull('facturas.FechaFactura')
            ->where('facturas.FechaFactura', '>', '2000-01-01 00:00:00')
            ->orderBy('facturas.FechaFactura')
            ->orderBy('facturas.NroFactura')
            ->first(['facturas.NroFactura', 'clientes.NombreEmpresa']);

        $this->assertNotNull($fila);

        $masReciente = Factura::query()
            ->orderByDesc('FechaFactura')
            ->orderByDesc('NroFactura')
            ->value('NroFactura');

        $this->get('/?q='.urlencode($fila->NroFactura))
            ->assertOk()
            ->assertSee($fila->NroFactura)
            ->assertSee($fila->NombreEmpresa)
            ->assertDontSee($masReciente);
    }

    public function test_index_filters_by_tipo_factura(): void
    {
        $credito = Factura::query()->where('TipoFactura', 'Credito')
            ->orderByDesc('FechaFactura')
            ->orderByDesc('NroFactura')
            ->value('NroFactura');
        $contado = Factura::query()->where('TipoFactura', 'Contado')
            ->orderByDesc('FechaFactura')
            ->orderByDesc('NroFactura')
            ->value('NroFactura');

        $this->assertNotNull($credito);
        $this->assertNotNull($contado);

        $this->get('/?tipo=Credito')
            ->assertOk()
            ->assertSee($credito)
            ->assertDontSee($contado);
    }
}
