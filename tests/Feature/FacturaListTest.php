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
        $sinEnPagina = $this->nroEnPrimeraPagina(false);

        if ($enviado === null) {
            $this->markTestSkipped('No hay facturas enviadas en facturas_pendientes.');
        }
        if ($sinEnPagina === null) {
            $this->markTestSkipped('No hay facturas sin estado en la primera página del listado.');
        }

        $this->get('/?estado=enviado')
            ->assertOk()
            ->assertSee($enviado)
            ->assertDontSee($sinEnPagina);
    }

    public function test_index_filters_by_estado_pendiente(): void
    {
        $pendiente = $this->nroPendiente();
        $sinEnPagina = $this->nroEnPrimeraPagina(false);

        if ($pendiente === null) {
            $this->markTestSkipped('No hay facturas pendientes en facturas_pendientes.');
        }
        if ($sinEnPagina === null) {
            $this->markTestSkipped('No hay facturas sin estado en la primera página del listado.');
        }

        $this->get('/?estado=pendiente')
            ->assertOk()
            ->assertSee($pendiente)
            ->assertDontSee($sinEnPagina);
    }

    public function test_index_filters_by_estado_sin_estado(): void
    {
        $sinEstado = $this->nroSinEstado();
        $conRegistroEnPagina = $this->nroEnPrimeraPagina(true);

        $this->assertNotNull($sinEstado);
        if ($conRegistroEnPagina === null) {
            $this->markTestSkipped('No hay facturas con registro en la primera página del listado.');
        }

        $this->get('/?estado=sin')
            ->assertOk()
            ->assertSee($sinEstado)
            ->assertDontSee($conRegistroEnPagina);
    }

    private function nroSinEstado(): ?string
    {
        return DB::connection('puntopan')->table('facturas')
            ->whereNotIn('facturas.NroFactura', FacturaPendiente::query()->pluck('nrofactura')->all())
            ->orderByDesc('facturas.FechaFactura')
            ->orderByDesc('facturas.NroFactura')
            ->value('facturas.NroFactura');
    }

    private function nroEnviado(): ?string
    {
        return DB::connection('puntopan')->table('facturas')
            ->whereIn('facturas.NroFactura', FacturaPendiente::query()->where('enviado', true)->pluck('nrofactura')->all())
            ->orderByDesc('facturas.FechaFactura')
            ->orderByDesc('facturas.NroFactura')
            ->value('facturas.NroFactura');
    }

    private function nroPendiente(): ?string
    {
        return DB::connection('puntopan')->table('facturas')
            ->whereIn('facturas.NroFactura', FacturaPendiente::query()->where('enviado', false)->pluck('nrofactura')->all())
            ->orderByDesc('facturas.FechaFactura')
            ->orderByDesc('facturas.NroFactura')
            ->value('facturas.NroFactura');
    }

    private function tieneRegistro(string $nro): bool
    {
        return FacturaPendiente::query()->where('nrofactura', $nro)->exists();
    }

    private function nrosPrimeraPagina(): array
    {
        return DB::connection('puntopan')->table('facturas')
            ->orderByDesc('facturas.FechaFactura')
            ->orderByDesc('facturas.NroFactura')
            ->limit(10)
            ->pluck('NroFactura')
            ->all();
    }

    private function nroEnPrimeraPagina(bool $conRegistro): ?string
    {
        foreach ($this->nrosPrimeraPagina() as $nro) {
            if ($this->tieneRegistro($nro) === $conRegistro) {
                return $nro;
            }
        }

        return null;
    }
}
