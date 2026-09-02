<?php

namespace Tests\Feature;

use App\Models\Factura;
use App\Models\FacturaPendiente;
use App\Models\ParametroEfactura;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EnviarJsonTest extends TestCase
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
            'api_url' => 'https://api.example.test/aceptar',
        ]);
    }

    protected function tearDown(): void
    {
        DB::connection()->rollBack();
        parent::tearDown();
    }

    private function crearPendiente(): string
    {
        $nro = Factura::query()->where('TipoFactura', 'Contado')
            ->whereHas('detalles', fn ($q) => $q->where('Cantidad', '>', 0))
            ->orderByDesc('FechaFactura')
            ->orderByDesc('NroFactura')
            ->value('NroFactura');

        $this->assertNotNull($nro);
        $this->postJson('/facturas/'.$nro.'/json')->assertOk();

        return $nro;
    }

    public function test_envia_y_marca_enviado_en_2xx(): void
    {
        Http::fake([
            'https://api.example.test/aceptar' => Http::response('{"ok":true}', 200, ['Content-Type' => 'application/json']),
        ]);

        $nro = $this->crearPendiente();

        $this->postJson('/facturas/'.$nro.'/enviar')
            ->assertOk()
            ->assertJsonPath('nrofactura', $nro)
            ->assertJsonPath('enviado', true);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.example.test/aceptar');
        $fila = FacturaPendiente::query()->where('nrofactura', $nro)->first();
        $this->assertTrue($fila->enviado);
        $this->assertStringContainsString('ok', $fila->respuesta);
    }

    public function test_no_marca_enviado_en_error_http_y_guarda_respuesta(): void
    {
        Http::fake([
            'https://api.example.test/aceptar' => Http::response('{"error":"boom"}', 422, ['Content-Type' => 'application/json']),
        ]);

        $nro = $this->crearPendiente();

        $this->postJson('/facturas/'.$nro.'/enviar')
            ->assertOk()
            ->assertJsonPath('enviado', false);

        $fila = FacturaPendiente::query()->where('nrofactura', $nro)->first();
        $this->assertFalse($fila->enviado);
        $this->assertStringContainsString('boom', $fila->respuesta);
    }

    public function test_error_si_no_hay_api_url(): void
    {
        ParametroEfactura::registroUnico()->update(['api_url' => '']);

        $nro = $this->crearPendiente();

        $this->postJson('/facturas/'.$nro.'/enviar')->assertStatus(422);
    }

    public function test_error_si_no_hay_pendiente(): void
    {
        $nro = Factura::query()->where('TipoFactura', 'Contado')
            ->orderByDesc('FechaFactura')->orderByDesc('NroFactura')
            ->value('NroFactura');
        $this->assertNotNull($nro);

        $this->postJson('/facturas/'.$nro.'/enviar')->assertStatus(422);
    }
}
