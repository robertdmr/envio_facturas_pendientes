<?php

namespace Tests\Feature;

use App\Jobs\EnviarPendienteJob;
use App\Services\EnvioEfacturaService;
use App\Services\PuntopanConexion;
use App\Services\SondeadorTcp;
use RuntimeException;
use Tests\Support\SondeadorTcpFalso;
use Tests\TestCase;

/**
 * Flujo de aprobación del respaldo a la copia local de `puntopan`.
 *
 * El sondeo del remoto se sustituye por un doble controlable; la copia local se
 * comprueba de verdad (127.0.0.1/puntopan existe en este equipo).
 */
class PuntopanConexionLocalTest extends TestCase
{
    private SondeadorTcpFalso $sondeador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sondeador = new SondeadorTcpFalso;

        $this->app->instance(SondeadorTcp::class, $this->sondeador);

        // El TestCase arranca con la copia local forzada (PUNTOPAN_FORCE_LOCAL);
        // estos tests necesitan partir del escenario "servidor remoto".
        config(['database.puntopan_respaldo.forzado' => false]);
        app(PuntopanConexion::class)->revocar();
    }

    public function test_estado_es_remoto_cuando_el_sondeo_responde(): void
    {
        $this->sondeador->vivo = true;

        $this->assertSame(PuntopanConexion::REMOTO_OK, app(PuntopanConexion::class)->estado());
    }

    public function test_estado_requiere_aprobacion_cuando_el_remoto_no_responde(): void
    {
        $this->assertSame(PuntopanConexion::REQUIERE_APROBACION, app(PuntopanConexion::class)->estado());
    }

    public function test_el_remoto_caido_redirige_a_la_confirmacion(): void
    {
        $this->get('/')->assertRedirect(route('puntopan.conexion-local.editar'));

        $this->get(route('puntopan.conexion-local.editar'))
            ->assertOk()
            ->assertSee('Usar la copia local')
            ->assertSee(config('database.connections.puntopan_remoto.host'));
    }

    public function test_aprobar_deja_la_conexion_en_la_copia_local(): void
    {
        $this->get('/')->assertRedirect(route('puntopan.conexion-local.editar'));

        $this->post(route('puntopan.conexion-local.aprobar'))->assertRedirect();

        $this->get('/')->assertOk();

        $this->assertSame(
            config('database.connections.puntopan_fallback.host'),
            config('database.connections.puntopan.host'),
        );
        $this->assertSame(PuntopanConexion::LOCAL_APROBADO, app(PuntopanConexion::class)->estado());
    }

    public function test_la_aprobacion_caducada_vuelve_a_pedir_confirmacion(): void
    {
        $this->post(route('puntopan.conexion-local.aprobar'));

        session([PuntopanConexion::CLAVE_SESION => now()->subMinute()->getTimestamp()]);

        $this->get('/')->assertRedirect(route('puntopan.conexion-local.editar'));
    }

    public function test_el_banner_aparece_mientras_la_aprobacion_esta_vigente(): void
    {
        $this->post(route('puntopan.conexion-local.aprobar'));

        $this->get('/')
            ->assertOk()
            ->assertSee('copia local', false)
            ->assertSee('Volver al servidor remoto');
    }

    public function test_reintentar_retoma_el_remoto_cuando_responde(): void
    {
        $this->get('/')->assertRedirect(route('puntopan.conexion-local.editar'));

        $this->sondeador->vivo = true;

        $this->post(route('puntopan.conexion-local.reintentar'))->assertRedirect();

        $this->assertSame(
            config('database.connections.puntopan_remoto.host'),
            config('database.connections.puntopan.host'),
        );
        $this->assertNull(app(PuntopanConexion::class)->aprobadoHasta());
    }

    public function test_reintentar_avisa_cuando_el_remoto_sigue_caido(): void
    {
        $this->post(route('puntopan.conexion-local.reintentar'))
            ->assertRedirect(route('puntopan.conexion-local.editar'))
            ->assertSessionHas('error', 'El servidor remoto sigue sin responder.');
    }

    public function test_volver_al_remoto_descarta_la_aprobacion(): void
    {
        $this->post(route('puntopan.conexion-local.aprobar'));
        $this->assertNotNull(app(PuntopanConexion::class)->aprobadoHasta());

        $this->sondeador->vivo = true;

        $this->post(route('puntopan.conexion-local.volver'))
            ->assertRedirect(route('facturas.index'));

        $this->assertNull(app(PuntopanConexion::class)->aprobadoHasta());
    }

    public function test_con_el_respaldo_deshabilitado_no_se_ofrece_la_copia_local(): void
    {
        config(['database.puntopan_respaldo.permitido' => false]);

        $this->get('/')
            ->assertStatus(503)
            ->assertSee('puntopan no está disponible');

        $this->assertSame(PuntopanConexion::NO_DISPONIBLE, app(PuntopanConexion::class)->estado());
    }

    public function test_sin_copia_local_responde_503(): void
    {
        config(['database.connections.puntopan_fallback.database' => 'puntopan_que_no_existe']);

        $this->get('/')
            ->assertStatus(503)
            ->assertSee('copia local tampoco responde');
    }

    public function test_la_confirmacion_no_se_ofrece_con_el_respaldo_deshabilitado(): void
    {
        config(['database.puntopan_respaldo.permitido' => false]);

        $this->get(route('puntopan.conexion-local.editar'))->assertStatus(503);
    }

    public function test_el_job_falla_si_el_remoto_no_responde_y_no_hay_aprobacion(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('el servidor remoto de puntopan');

        (new EnviarPendienteJob('001-001-0000001'))->handle(app(EnvioEfacturaService::class));
    }

    public function test_la_respuesta_json_pide_la_aprobacion_sin_redirigir(): void
    {
        $this->postJson('/pendientes/enviar', ['nrofacturas' => ['001-001-0000001']])
            ->assertStatus(503)
            ->assertJsonPath('message', fn(string $mensaje) => str_contains($mensaje, 'copia local'));
    }
}
