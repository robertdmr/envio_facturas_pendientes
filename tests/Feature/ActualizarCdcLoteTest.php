<?php

namespace Tests\Feature;

use App\Models\FacturaPendiente;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ActualizarCdcLoteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::connection()->beginTransaction();
        DB::connection('puntopan')->beginTransaction();
    }

    protected function tearDown(): void
    {
        DB::connection('puntopan')->rollBack();
        DB::connection()->rollBack();
        parent::tearDown();
    }

    private function crearPendiente(string $nro, string $respuesta): void
    {
        FacturaPendiente::query()->create([
            'nrofactura' => $nro,
            'payload' => '{}',
            'enviado' => true,
            'respuesta' => $respuesta,
        ]);
    }

    public function test_actualiza_varias_facturas_y_omite_las_sin_cdc(): void
    {
        $conCdcA = 'TEST-999-0000020';
        $conCdcB = 'TEST-999-0000021';
        $sinCdc = 'TEST-999-0000022';

        $this->crearPendiente($conCdcA, '{"cdc":"CDC-A"}');
        $this->crearPendiente($conCdcB, '{"cdc":"CDC-B"}');
        $this->crearPendiente($sinCdc, '{"ok":true}');

        DB::connection('puntopan')->table('fecdc')->insert([
            ['nrofactura' => $conCdcA, 'cdc' => null, 'estado' => 'creado'],
            ['nrofactura' => $conCdcB, 'cdc' => 'VIEJO', 'estado' => 'enviado'],
        ]);

        $this->postJson('/pendientes/actualizar-cdc', [
            'nrofacturas' => [$conCdcA, $conCdcB, $sinCdc, 'TEST-999-9999999'],
        ])->assertOk()
            ->assertJsonPath('actualizadas', 2)
            ->assertJsonPath('omitidas', 2)
            ->assertJsonPath('filas', 2);

        $this->assertSame('CDC-A', DB::connection('puntopan')->table('fecdc')->where('nrofactura', $conCdcA)->value('cdc'));
        $this->assertSame('CDC-B', DB::connection('puntopan')->table('fecdc')->where('nrofactura', $conCdcB)->value('cdc'));
        $this->assertSame('aprobado', DB::connection('puntopan')->table('fecdc')->where('nrofactura', $conCdcA)->value('estado'));
    }

    public function test_valida_que_venga_al_menos_una_factura(): void
    {
        $this->postJson('/pendientes/actualizar-cdc', ['nrofacturas' => []])
            ->assertStatus(422);
    }

    public function test_lote_usa_una_sola_sentencia_update_al_remoto(): void
    {
        $nros = [];

        foreach (range(0, 4) as $i) {
            $nro = 'TEST-999-000003'.$i;
            $this->crearPendiente($nro, '{"cdc":"CDC-'.$i.'"}');
            DB::connection('puntopan')->table('fecdc')->insert([
                'nrofactura' => $nro,
                'cdc' => null,
                'estado' => 'creado',
            ]);
            $nros[] = $nro;
        }

        $updates = 0;

        DB::connection('puntopan')->listen(function ($query) use (&$updates) {
            if (str_starts_with(strtolower(ltrim($query->sql)), 'update')) {
                $updates++;
            }
        });

        $this->postJson('/pendientes/actualizar-cdc', ['nrofacturas' => $nros])
            ->assertOk()
            ->assertJsonPath('actualizadas', 5)
            ->assertJsonPath('filas', 5);

        $this->assertSame(1, $updates);
    }
}
