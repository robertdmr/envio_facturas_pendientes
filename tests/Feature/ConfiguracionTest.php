<?php

namespace Tests\Feature;

use App\Models\ParametroEfactura;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ConfiguracionTest extends TestCase
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

    public function test_edit_show_prefilled_form(): void
    {
        $this->get('/configuracion')
            ->assertOk()
            ->assertSee('Configuración')
            ->assertSee('12558948')
            ->assertSee('33');
    }

    public function test_update_persists_single_record(): void
    {
        $this->post('/configuracion', [
            'contribuyente_id' => 44,
            'pass' => 'abc123',
            'timbrado' => '99999999',
            'fec_inicio' => '2023-01-01T00:00:00',
            'sucursal' => 'Sucursal Norte',
        ])->assertRedirect();

        $this->assertDatabaseHas('parametros_efactura', [
            'contribuyente_id' => 44,
            'pass' => 'abc123',
            'timbrado' => '99999999',
            'sucursal' => 'Sucursal Norte',
        ]);
        $this->assertSame(1, ParametroEfactura::query()->count());
    }

    public function test_update_validation_fails_without_pass(): void
    {
        $this->from('/configuracion')
            ->post('/configuracion', [
                'contribuyente_id' => 33,
                'pass' => '',
                'timbrado' => '12558948',
                'fec_inicio' => '2021-08-25',
                'sucursal' => 'Central',
            ])
            ->assertRedirect('/configuracion')
            ->assertSessionHasErrors('pass');
    }
}
