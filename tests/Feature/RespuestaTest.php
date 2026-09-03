<?php

namespace Tests\Feature;

use App\Models\Factura;
use App\Models\FacturaPendiente;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RespuestaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::connection()->beginTransaction();
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

    public function test_obtener_respuesta_de_factura_enviada(): void
    {
        $nro = $this->nroContado();

        $this->postJson('/facturas/'.$nro.'/json')->assertOk();
        FacturaPendiente::query()->where('nrofactura', $nro)->update([
            'enviado' => true,
            'respuesta' => '{"ok":true,"cdc":"ABC123"}',
        ]);

        $this->getJson('/facturas/'.$nro.'/respuesta')
            ->assertOk()
            ->assertJsonPath('nrofactura', $nro)
            ->assertJsonPath('enviado', true)
            ->assertJsonPath('respuesta', '{"ok":true,"cdc":"ABC123"}')
            ->assertJsonMissingPath('payload');
    }

    public function test_obtener_respuesta_vacia_de_factura_no_enviada(): void
    {
        $nro = $this->nroContado();

        $this->postJson('/facturas/'.$nro.'/json')->assertOk();

        $this->getJson('/facturas/'.$nro.'/respuesta')
            ->assertOk()
            ->assertJsonPath('enviado', false)
            ->assertJsonPath('respuesta', null);
    }

    public function test_respuesta_404_sin_pendiente(): void
    {
        $conPendiente = FacturaPendiente::query()->pluck('nrofactura');

        $nro = Factura::query()->where('TipoFactura', 'Contado')
            ->when($conPendiente->isNotEmpty(), fn ($q) => $q->whereNotIn('NroFactura', $conPendiente))
            ->orderByDesc('FechaFactura')
            ->orderByDesc('NroFactura')
            ->value('NroFactura');
        $this->assertNotNull($nro);

        $this->getJson('/facturas/'.$nro.'/respuesta')->assertNotFound();
    }
}
