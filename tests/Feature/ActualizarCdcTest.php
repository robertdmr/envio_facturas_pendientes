<?php

namespace Tests\Feature;

use App\Models\FacturaPendiente;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ActualizarCdcTest extends TestCase
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

    public function test_actualiza_cdc_fecha_y_estado_en_todas_las_filas(): void
    {
        $nro = 'TEST-999-0000010';
        $this->crearPendiente($nro, '{"cdc":"CDCXYZ","tipo":"FE"}');

        DB::connection('puntopan')->table('fecdc')->insert([
            ['nrofactura' => $nro, 'cdc' => null, 'estado' => 'creado'],
            ['nrofactura' => $nro, 'cdc' => 'VIEJO', 'estado' => 'enviado'],
        ]);

        $this->postJson('/pendientes/'.$nro.'/cdc')
            ->assertOk()
            ->assertJsonPath('nrofactura', $nro)
            ->assertJsonPath('cdc', 'CDCXYZ')
            ->assertJsonPath('filas_actualizadas', 2);

        $filas = DB::connection('puntopan')->table('fecdc')->where('nrofactura', $nro)->get();
        $this->assertCount(2, $filas);

        foreach ($filas as $fila) {
            $this->assertSame('CDCXYZ', $fila->cdc);
            $this->assertSame('aprobado', $fila->estado);
            $this->assertMatchesRegularExpression(
                '#^\d{1,2}/\d{1,2}/\d{4} \d{1,2}:\d{2}:\d{2}$#',
                (string) $fila->fecha_actualizacion
            );
        }
    }

    public function test_devuelve_422_si_la_respuesta_no_tiene_cdc(): void
    {
        $nro = 'TEST-999-0000011';
        $this->crearPendiente($nro, '{"ok":true}');

        $this->postJson('/pendientes/'.$nro.'/cdc')->assertStatus(422);
    }

    public function test_devuelve_404_si_no_hay_pendiente(): void
    {
        $this->postJson('/pendientes/TEST-999-9999999/cdc')->assertNotFound();
    }
}
