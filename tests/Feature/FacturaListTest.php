<?php

namespace Tests\Feature;

use App\Models\Factura;
use App\Models\FacturaPendiente;
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

    public function test_index_filters_by_client_name(): void
    {
        $fila = DB::connection('puntopan')->table('facturas')
            ->join('clientes', 'clientes.IdCliente', '=', 'facturas.IdCliente')
            ->whereNotNull('clientes.NombreEmpresa')
            ->where('clientes.NombreEmpresa', '!=', 'SELECCIONAR CLIENTE')
            ->orderByDesc('facturas.FechaFactura')
            ->orderByDesc('facturas.NroFactura')
            ->first(['facturas.NroFactura', 'clientes.NombreEmpresa']);

        $this->assertNotNull($fila);

        $otro = DB::connection('puntopan')->table('facturas')
            ->join('clientes', 'clientes.IdCliente', '=', 'facturas.IdCliente')
            ->whereNotNull('clientes.NombreEmpresa')
            ->where('clientes.NombreEmpresa', '!=', 'SELECCIONAR CLIENTE')
            ->whereRaw('clientes.NombreEmpresa NOT LIKE ?', ['%'.$fila->NombreEmpresa.'%'])
            ->orderByDesc('facturas.FechaFactura')
            ->orderByDesc('facturas.NroFactura')
            ->first(['facturas.NroFactura', 'clientes.NombreEmpresa']);

        $this->assertNotNull($otro);

        $this->get('/?cliente='.urlencode($fila->NombreEmpresa))
            ->assertOk()
            ->assertSee($fila->NombreEmpresa)
            ->assertSee($fila->NroFactura)
            ->assertDontSee($otro->NroFactura);
    }

    public function test_index_shows_filter_fields_in_expected_order(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSeeInOrder([
                'name="q"',
                'name="tipo"',
                'name="cliente"',
                'name="desde"',
                'name="hasta"',
                'name="estado"',
            ], false);
    }

    public function test_index_shows_export_button_to_export_route(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Exportar a Excel')
            ->assertSee(route('facturas.exportar'), false);
    }

    public function test_index_filters_by_estado_enviado(): void
    {
        $enviado = $this->nroEnviado();
        $sinEstado = $this->nroSinEstado();

        $this->assertNotNull($sinEstado);
        if ($enviado === null) {
            $this->markTestSkipped('No hay facturas enviadas en la BD puntopan.');
        }

        $this->get('/?estado=enviado')
            ->assertOk()
            ->assertSee($enviado)
            ->assertDontSee($sinEstado);
    }

    public function test_index_filters_by_estado_sin_estado(): void
    {
        $sinEstado = $this->nroSinEstado();

        $this->assertNotNull($sinEstado);

        $conRegistro = DB::connection('puntopan')->table('facturas')
            ->join($this->bdPendientes().'.facturas_pendientes as fp', 'fp.nrofactura', '=', 'facturas.NroFactura')
            ->orderByDesc('facturas.FechaFactura')
            ->orderByDesc('facturas.NroFactura')
            ->value('facturas.NroFactura');

        if ($conRegistro === null) {
            $this->markTestSkipped('No hay facturas con registro en facturas_pendientes en la BD de la app.');
        }

        $this->get('/?estado=sin')
            ->assertOk()
            ->assertSee($sinEstado)
            ->assertDontSee($conRegistro);
    }

    private function bdPendientes(): string
    {
        return (string) FacturaPendiente::query()->getConnection()->getDatabaseName();
    }

    private function nroSinEstado(): ?string
    {
        return DB::connection('puntopan')->table('facturas')
            ->leftJoin($this->bdPendientes().'.facturas_pendientes as fp', 'fp.nrofactura', '=', 'facturas.NroFactura')
            ->whereNull('fp.nrofactura')
            ->orderByDesc('facturas.FechaFactura')
            ->orderByDesc('facturas.NroFactura')
            ->value('facturas.NroFactura');
    }

    private function nroEnviado(): ?string
    {
        return DB::connection('puntopan')->table('facturas')
            ->join($this->bdPendientes().'.facturas_pendientes as fp', 'fp.nrofactura', '=', 'facturas.NroFactura')
            ->where('fp.enviado', true)
            ->orderByDesc('facturas.FechaFactura')
            ->orderByDesc('facturas.NroFactura')
            ->value('facturas.NroFactura');
    }
}
