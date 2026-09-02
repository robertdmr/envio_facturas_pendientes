<?php

namespace Tests\Feature;

use App\Models\Factura;
use App\Models\FacturaPendiente;
use App\Models\ParametroEfactura;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GenerarJsonTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::connection()->beginTransaction();
        ParametroEfactura::registroUnico()->update([
            'contribuyente_id' => 33,
            'pass' => 'c6e8a6f0d815e1fd9358d76b208799efbb40c6d8155be29903dcbe5b99125c07',
            'timbrado' => '12558948',
            'fec_inicio' => '2021-08-25 00:00:00',
            'sucursal' => 'Central',
        ]);
    }

    protected function tearDown(): void
    {
        DB::connection()->rollBack();
        parent::tearDown();
    }

    private function nroContado(): string
    {
        $nro = Factura::query()->where('TipoFactura', 'Contado')
            ->whereHas('detalles', fn ($q) => $q->where('Cantidad', '>', 0))
            ->orderByDesc('FechaFactura')
            ->orderByDesc('NroFactura')
            ->value('NroFactura');

        $this->assertNotNull($nro);

        return $nro;
    }

    public function test_generate_persists_and_returns_payload(): void
    {
        $nro = $this->nroContado();

        $this->postJson('/facturas/'.$nro.'/json')
            ->assertOk()
            ->assertJsonPath('nrofactura', $nro)
            ->assertJsonPath('payload.contribuyente.contribuyenteid', 33)
            ->assertJsonPath('payload.timbrado.documentoNro', substr($nro, 8));

        $this->assertSame(1, FacturaPendiente::query()->where('nrofactura', $nro)->count());
    }

    public function test_regenerate_replaces_payload_without_duplicate(): void
    {
        $nro = $this->nroContado();

        $this->postJson('/facturas/'.$nro.'/json')->assertOk();
        FacturaPendiente::query()->where('nrofactura', $nro)->update(['payload' => 'VALOR_VIEJO']);

        $this->postJson('/facturas/'.$nro.'/json')->assertOk();

        $payload = FacturaPendiente::query()->where('nrofactura', $nro)->value('payload');
        $this->assertNotSame('VALOR_VIEJO', $payload);
        $this->assertStringContainsString('contribuyenteid', $payload);
        $this->assertSame(1, FacturaPendiente::query()->where('nrofactura', $nro)->count());
    }

    public function test_generate_returns_404_for_unknown_invoice(): void
    {
        $this->postJson('/facturas/ZZ-999-9999999/json')->assertNotFound();
    }
}
