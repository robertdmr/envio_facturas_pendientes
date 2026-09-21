<?php

namespace App\Jobs;

use App\Models\Factura;
use App\Models\FacturaPendiente;
use App\Services\EnvioEfacturaService;
use App\Services\PuntopanConexion;
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
        // En cola no hay sesión, así que no se puede pedir aprobación: si el
        // remoto está caído el job falla con un mensaje explícito en lugar de
        // leer la copia local por su cuenta.
        $conexion = app(PuntopanConexion::class);

        if (! in_array($conexion->prepararConexion(), [PuntopanConexion::REMOTO_OK, PuntopanConexion::LOCAL_APROBADO], true)) {
            throw new RuntimeException(sprintf(
                'No se puede preparar la factura %s: el servidor remoto de puntopan (%s) no responde. %s',
                $this->nrofactura,
                $conexion->hostRemoto(),
                $conexion->permitido()
                    ? 'Aprobá el uso de la copia local desde la web (o usá PUNTOPAN_FORCE_LOCAL=true en el worker).'
                    : 'La copia local está deshabilitada por configuración.',
            ));
        }

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
