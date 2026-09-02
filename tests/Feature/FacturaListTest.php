<?php

namespace Tests\Feature;

use App\Models\Factura;
use Tests\TestCase;

class FacturaListTest extends TestCase
{
    public function test_index_shows_the_latest_invoice_number(): void
    {
        $nro = Factura::query()->orderByDesc('FechaFactura')->value('NroFactura');

        $this->get('/')
            ->assertOk()
            ->assertSee($nro);
    }

    public function test_index_shows_columns_from_comandadet_and_clientes(): void
    {
        $fila = \DB::table('facturas')
            ->join('clientes', 'clientes.IdCliente', '=', 'facturas.IdCliente')
            ->join('comandadet', 'comandadet.NroFactura', '=', 'facturas.NroFactura')
            ->whereNotNull('clientes.NombreEmpresa')
            ->orderByDesc('facturas.FechaFactura')
            ->first(['facturas.NroFactura', 'clientes.NombreEmpresa']);

        $this->get('/')
            ->assertOk()
            ->assertSee($fila->NroFactura)
            ->assertSee($fila->NombreEmpresa, false)
            ->assertSee('Total')
            ->assertSee('Ítems');
    }

    public function test_index_filters_by_invoice_number(): void
    {
        $nro = Factura::query()
            ->whereNotNull('FechaFactura')
            ->where('FechaFactura', '>', '2000-01-01 00:00:00')
            ->orderBy('FechaFactura')
            ->value('NroFactura');

        $this->get('/?q='.urlencode($nro))
            ->assertOk()
            ->assertSee($nro);
    }

    public function test_index_filters_by_tipo_factura(): void
    {
        $credito = Factura::query()->where('TipoFactura', 'Credito')
            ->orderByDesc('FechaFactura')->value('NroFactura');
        $contado = Factura::query()->where('TipoFactura', 'Contado')
            ->orderByDesc('FechaFactura')->value('NroFactura');

        $this->get('/?tipo=Credito')
            ->assertOk()
            ->assertSee($credito)
            ->assertDontSee($contado);
    }
}
