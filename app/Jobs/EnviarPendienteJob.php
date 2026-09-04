<?php

namespace App\Jobs;

use App\Models\Factura;
use App\Models\FacturaPendiente;
use App\Services\EnvioEfacturaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

class EnviarPendienteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public string $nrofactura) {}

    public function handle(EnvioEfacturaService $service): void
    {
        $factura = Factura::query()->where('NroFactura', $this->nrofactura)->first();

        if (! $factura) {
            return;
        }

        $pendiente = FacturaPendiente::query()->where('nrofactura', $this->nrofactura)->first();

        if ($pendiente && $pendiente->enviado) {
            return;
        }

        try {
            $service->preparar($factura);

            $pendiente = FacturaPendiente::query()->where('nrofactura', $this->nrofactura)->firstOrFail();

            if ($pendiente->enviado) {
                return;
            }

            $service->enviar($pendiente);
        } catch (RuntimeException) {
            // p. ej. falta api_url o la factura no tiene líneas válidas:
            // no se persiste nada y la factura queda pendiente para un próximo intento.
        }
    }
}
