<?php

namespace Tests\Feature;

use App\Jobs\EnviarPendienteJob;
use App\Models\Factura;
use App\Models\FacturaPendiente;
use App\Models\ParametroEfactura;
use App\Services\EnvioEfacturaService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EnvioGrupoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::connection()->beginTransaction();
        Http::preventStrayRequests();
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

    private function nroContadoConLineas(?string $excluir = null): string
    {
        $conPendiente = FacturaPendiente::query()->pluck('nrofactura');

        $nro = Factura::query()->where('TipoFactura', 'Contado')
            ->whereHas('detalles', fn ($q) => $q->where('Cantidad', '>', 0))
            ->when($excluir, fn ($q, $n) => $q->where('NroFactura', '!=', $n))
            ->when($conPendiente->isNotEmpty(), fn ($q) => $q->whereNotIn('NroFactura', $conPendiente))
            ->orderByDesc('FechaFactura')
            ->orderByDesc('NroFactura')
            ->value('NroFactura');

        $this->assertNotNull($nro);

        return $nro;
    }

    public function test_lote_encola_solo_no_enviadas_y_omite_resto(): void
    {
        $yaEnviada = $this->nroContadoConLineas();
        $sinPendiente = $this->nroContadoConLineas($yaEnviada);

        FacturaPendiente::query()->create([
            'nrofactura' => $yaEnviada,
            'payload' => '{"ya":"enviada"}',
            'enviado' => true,
            'respuesta' => '{"ok":true}',
        ]);

        Bus::fake();

        $this->postJson('/pendientes/enviar', [
            'nrofacturas' => [$yaEnviada, $sinPendiente, 'ZZ-999-9999999'],
        ])->assertOk()
            ->assertJson(['encoladas' => 1, 'omitidas' => 2])
            ->assertJsonPath('nros.0', $sinPendiente);

        Bus::assertChained([new EnviarPendienteJob($sinPendiente)]);
    }

    public function test_estado_cuenta_solo_pendientes_sin_resolver(): void
    {
        $conPendiente = FacturaPendiente::query()->pluck('nrofactura');

        $candidatos = Factura::query()->where('TipoFactura', 'Contado')
            ->whereHas('detalles', fn ($q) => $q->where('Cantidad', '>', 0))
            ->when($conPendiente->isNotEmpty(), fn ($q) => $q->whereNotIn('NroFactura', $conPendiente))
            ->orderByDesc('FechaFactura')
            ->orderByDesc('NroFactura')
            ->limit(3)
            ->pluck('NroFactura')
            ->all();

        $this->assertCount(3, $candidatos);
        [$sinResolver, $enviada, $fallida] = $candidatos;

        FacturaPendiente::query()->create(['nrofactura' => $sinResolver, 'payload' => '{}', 'enviado' => false, 'respuesta' => null]);
        FacturaPendiente::query()->create(['nrofactura' => $enviada, 'payload' => '{}', 'enviado' => true, 'respuesta' => '{}']);
        FacturaPendiente::query()->create(['nrofactura' => $fallida, 'payload' => '{}', 'enviado' => false, 'respuesta' => '{"e":1}']);

        $this->postJson('/pendientes/estado', [
            'nrofacturas' => [$sinResolver, $enviada, $fallida],
        ])->assertOk()
            ->assertJson(['pendientes' => 1]);
    }

    public function test_lote_no_despacha_cuando_todo_ya_fue_enviado(): void
    {
        $nro = $this->nroContadoConLineas();
        FacturaPendiente::query()->create([
            'nrofactura' => $nro,
            'payload' => '{"ya":"enviada"}',
            'enviado' => true,
            'respuesta' => '{"ok":true}',
        ]);

        Bus::fake();

        $this->postJson('/pendientes/enviar', ['nrofacturas' => [$nro]])
            ->assertOk()
            ->assertJson(['encoladas' => 0, 'omitidas' => 1]);

        Bus::assertNothingDispatched(EnviarPendienteJob::class);
    }

    public function test_job_genera_json_si_falta_y_envia(): void
    {
        Http::fake([
            'https://api.example.test/aceptar' => Http::response('{"ok":true}', 200, ['Content-Type' => 'application/json']),
        ]);

        $nro = $this->nroContadoConLineas();
        $this->assertNull(FacturaPendiente::query()->where('nrofactura', $nro)->first());

        $job = new EnviarPendienteJob($nro);
        $job->handle(app(EnvioEfacturaService::class));

        $fila = FacturaPendiente::query()->where('nrofactura', $nro)->first();
        $this->assertNotNull($fila);
        $this->assertTrue($fila->enviado);
        $this->assertStringContainsString('contribuyenteid', $fila->payload);
        $this->assertStringContainsString('ok', $fila->respuesta);
        Http::assertSentCount(1);
    }

    public function test_job_salta_factura_ya_enviada(): void
    {
        Http::fake();

        $nro = $this->nroContadoConLineas();
        FacturaPendiente::query()->create([
            'nrofactura' => $nro,
            'payload' => '{"actual":"json"}',
            'enviado' => true,
            'respuesta' => '{"ya":true}',
        ]);

        $job = new EnviarPendienteJob($nro);
        $job->handle(app(EnvioEfacturaService::class));

        Http::assertNothingSent();
        $fila = FacturaPendiente::query()->where('nrofactura', $nro)->first();
        $this->assertTrue($fila->enviado);
        $this->assertSame('{"ya":true}', $fila->respuesta);
    }

    public function test_job_persiste_error_http_sin_marcar_enviado(): void
    {
        Http::fake([
            'https://api.example.test/aceptar' => Http::response('{"error":"boom"}', 422, ['Content-Type' => 'application/json']),
        ]);

        $nro = $this->nroContadoConLineas();
        FacturaPendiente::query()->create([
            'nrofactura' => $nro,
            'payload' => '{"a":1}',
            'enviado' => false,
            'respuesta' => null,
        ]);

        $job = new EnviarPendienteJob($nro);
        $job->handle(app(EnvioEfacturaService::class));

        $fila = FacturaPendiente::query()->where('nrofactura', $nro)->first();
        $this->assertFalse($fila->enviado);
        $this->assertStringContainsString('boom', $fila->respuesta);
    }
}
