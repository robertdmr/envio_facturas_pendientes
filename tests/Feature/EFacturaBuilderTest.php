<?php

namespace Tests\Feature;

use App\Models\DetalleFactura;
use App\Models\Factura;
use App\Models\ParametroEfactura;
use App\Services\EFacturaBuilder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EFacturaBuilderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::connection()->beginTransaction();
        ParametroEfactura::registroUnico();
    }

    protected function tearDown(): void
    {
        DB::connection()->rollBack();
        parent::tearDown();
    }

    private function facturaMuestra(string $joinRaw): Factura
    {
        $nro = DB::connection('puntopan')->table('facturas')
            ->leftJoin('clientes', 'clientes.IdCliente', '=', 'facturas.IdCliente')
            ->where('facturas.TipoFactura', 'Contado')
            ->whereRaw($joinRaw)
            ->orderByDesc('facturas.FechaFactura')
            ->orderByDesc('facturas.NroFactura')
            ->value('facturas.NroFactura');

        $this->assertNotNull($nro);

        return Factura::query()->where('NroFactura', $nro)->firstOrFail();
    }

    public function test_contado_cedula_payload(): void
    {
        $factura = $this->facturaMuestra("clientes.RUC IS NOT NULL AND TRIM(clientes.RUC) <> '' AND clientes.RUC NOT LIKE '%-%' AND clientes.RUC REGEXP '^[0-9]+$' AND TRIM(COALESCE(clientes.NombreEmpresa, '')) <> ''");

        $payload = EFacturaBuilder::build($factura);

        $this->assertSame(33, $payload['contribuyente']['contribuyenteid']);
        $this->assertSame(1, $payload['condicionOperacion']['condicion']);
        $this->assertSame('1', $payload['receptor']['tipoDocumento']);
        $this->assertSame([['tipoPagoCodigo' => 1, 'monto' => $payload['totalComprobante']]], $payload['condicionOperacion']['tiposPagos']);

        [$est, $pto, $doc] = explode('-', $factura->NroFactura);
        $this->assertSame($est, $payload['timbrado']['establecimiento']);
        $this->assertSame($pto, $payload['timbrado']['puntoExpedicion']);
        $this->assertSame($doc, $payload['timbrado']['documentoNro']);
    }

    public function test_contado_ruc_payload(): void
    {
        $factura = $this->facturaMuestra("clientes.RUC LIKE '%-%'");

        $payload = EFacturaBuilder::build($factura);

        $this->assertArrayNotHasKey('tipoDocumento', $payload['receptor']);
        $this->assertArrayHasKey('dv', $payload['receptor']);
        $this->assertSame(1, $payload['condicionOperacion']['condicion']);
    }

    public function test_contado_innominado_payload(): void
    {
        $nro = DB::connection('puntopan')->table('facturas')
            ->leftJoin('clientes', 'clientes.IdCliente', '=', 'facturas.IdCliente')
            ->where('facturas.TipoFactura', 'Contado')
            ->where(function ($q) {
                $q->whereNull('clientes.NombreEmpresa')
                    ->orWhereRaw("TRIM(COALESCE(clientes.NombreEmpresa, '')) = ''")
                    ->orWhereRaw("clientes.NombreEmpresa IN ('SELECCIONAR CLIENTE', 'SIN NOMBRE', 'XXX SIN NOMBRE')");
            })
            ->orderByDesc('facturas.FechaFactura')
            ->orderByDesc('facturas.NroFactura')
            ->value('facturas.NroFactura');

        $this->assertNotNull($nro);
        $factura = Factura::query()->where('NroFactura', $nro)->firstOrFail();

        $payload = EFacturaBuilder::build($factura);

        $this->assertSame('5', $payload['receptor']['tipoDocumento']);
        $this->assertSame('0', $payload['receptor']['docNro']);
        $this->assertSame('XXX SIN NOMBRE', $payload['receptor']['razonSocial']);
    }

    public function test_credito_payload(): void
    {
        $nro = Factura::query()->where('TipoFactura', 'Credito')
            ->orderByDesc('FechaFactura')
            ->orderByDesc('NroFactura')
            ->value('NroFactura');

        $this->assertNotNull($nro);
        $factura = Factura::query()->where('NroFactura', $nro)->firstOrFail();

        $payload = EFacturaBuilder::build($factura);

        $this->assertSame(2, $payload['condicionOperacion']['condicion']);
        $this->assertSame(1, $payload['condicionOperacion']['operacionTipo']);
        $this->assertSame('30 dias', $payload['condicionOperacion']['plazoCredito']);
    }

    public function test_total_matches_lines_and_tasa_iva(): void
    {
        $factura = $this->facturaMuestra("clientes.RUC IS NOT NULL AND TRIM(clientes.RUC) <> ''");
        $lineas = DetalleFactura::query()->where('NroFactura', $factura->NroFactura)->get();

        $payload = EFacturaBuilder::build($factura);

        $esperado = (int) round($lineas->sum(fn ($l) => (float) $l->Cantidad * (float) $l->PrecioVenta - (float) $l->descuento));
        $this->assertSame($esperado, $payload['totalComprobante']);
        $this->assertSame(10, $payload['detalles'][0]['tasaIVA']);
        $this->assertSame(100, $payload['detalles'][0]['proporcionIVA']);
        $this->assertCount($lineas->where('Cantidad', '>', 0)->count(), $payload['detalles']);
    }
}
