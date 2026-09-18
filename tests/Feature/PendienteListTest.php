<?php

namespace Tests\Feature;

use App\Models\FacturaPendiente;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PendienteListTest extends TestCase
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

    public function test_index_lists_pending_invoice_with_cdc(): void
    {
        $nro = 'TEST-999-0000001';

        FacturaPendiente::query()->create([
            'nrofactura' => $nro,
            'payload' => '{}',
            'enviado' => true,
            'respuesta' => '{"qr":"http://x","cdc":"TESTCDC123","tipo":"FE"}',
        ]);

        $this->get('/pendientes?q='.urlencode($nro))
            ->assertOk()
            ->assertSee($nro)
            ->assertSee('TESTCDC123');
    }

    public function test_index_shows_fecdc_cdc_for_linked_invoice(): void
    {
        $nro = 'TEST-999-0000002';

        FacturaPendiente::query()->create([
            'nrofactura' => $nro,
            'payload' => '{}',
            'enviado' => true,
            'respuesta' => '{"cdc":"RESPUESTACDC"}',
        ]);

        DB::connection('puntopan')->table('fecdc')->insert([
            'nrofactura' => $nro,
            'cdc' => 'FECDCVALUE',
            'estado' => 'creado',
        ]);

        $this->get('/pendientes?q='.urlencode($nro))
            ->assertOk()
            ->assertSee('FECDCVALUE');
    }
}
