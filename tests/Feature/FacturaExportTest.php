<?php

namespace Tests\Feature;

use App\Models\DetalleFactura;
use App\Models\Factura;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FacturaExportTest extends TestCase
{
    public function test_export_returns_csv_with_expected_header(): void
    {
        $response = $this->get('/facturas/exportar');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename="facturas_', (string) $response->headers->get('Content-Disposition'));
        $response->assertSee('N° Factura', false);
        $response->assertSee('Situación', false);
    }

    public function test_export_respects_tipo_filter(): void
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

        $response = $this->get('/facturas/exportar?tipo=Credito');

        $response->assertOk();
        $this->assertStringContainsString('"'.$credito.'"', $response->getContent());
        $this->assertStringNotContainsString('"'.$contado.'"', $response->getContent());
    }

    public function test_export_is_not_limited_to_first_page(): void
    {
        $nro = Factura::query()
            ->orderByDesc('FechaFactura')
            ->orderByDesc('NroFactura')
            ->offset(15)
            ->value('NroFactura');

        $this->assertNotNull($nro);

        $response = $this->get('/facturas/exportar');

        $response->assertOk();
        $this->assertStringContainsString('"'.$nro.'"', $response->getContent());
    }

    public function test_export_escapes_client_names_with_commas(): void
    {
        $fila = DB::connection('puntopan')->table('facturas')
            ->join('clientes', 'clientes.IdCliente', '=', 'facturas.IdCliente')
            ->whereNotNull('clientes.NombreEmpresa')
            ->where('clientes.NombreEmpresa', 'like', '%,%')
            ->orderByDesc('facturas.FechaFactura')
            ->orderByDesc('facturas.NroFactura')
            ->first(['facturas.NroFactura', 'clientes.NombreEmpresa']);

        if ($fila === null) {
            $this->markTestSkipped('No hay clientes con coma en el nombre en la BD puntopan.');
        }

        $response = $this->get('/facturas/exportar?q='.urlencode($fila->NroFactura));

        $response->assertOk();
        $this->assertStringContainsString('"'.str_replace('"', '""', $fila->NombreEmpresa).'"', $response->getContent());
    }

    public function test_export_emits_items_and_total_as_unquoted_integers(): void
    {
        $detalle = DetalleFactura::query()
            ->select('NroFactura')
            ->selectRaw('COUNT(*) as items')
            ->selectRaw('COALESCE(SUM(Cantidad * PrecioVenta - descuento), 0) as total')
            ->whereNotNull('NroFactura')
            ->groupBy('NroFactura')
            ->orderByDesc('total')
            ->first();

        $this->assertNotNull($detalle);

        $fragmentoEsperado = ','.(int) $detalle->items.','.(int) round((float) $detalle->total).',';

        $response = $this->get('/facturas/exportar?q='.urlencode($detalle->NroFactura));

        $response->assertOk();
        $this->assertStringContainsString($fragmentoEsperado, $response->getContent());
    }
}
