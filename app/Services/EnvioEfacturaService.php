<?php

namespace App\Services;

use App\Models\Factura;
use App\Models\FacturaPendiente;
use App\Models\ParametroEfactura;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class EnvioEfacturaService
{
    public const ENCODE_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT;

    public function preparar(Factura $factura): array
    {
        $payload = EFacturaBuilder::build($factura);

        FacturaPendiente::query()->updateOrCreate(
            ['nrofactura' => $factura->NroFactura],
            [
                'payload' => json_encode($payload, self::ENCODE_FLAGS),
                'enviado' => false,
                'respuesta' => null,
            ]
        );

        return $payload;
    }

    public function enviar(FacturaPendiente $pendiente): array
    {
        $parametros = ParametroEfactura::registroUnico();

        if (empty($parametros->api_url)) {
            throw new RuntimeException('Configurá la API URL para el envío.');
        }

        $payload = json_decode((string) $pendiente->payload, true);

        if (! is_array($payload)) {
            throw new RuntimeException('El JSON pendiente de la factura no es válido.');
        }

        try {
            $respuesta = Http::timeout(30)
                ->acceptJson()
                ->asJson()
                ->post($parametros->api_url, $payload);
            $cuerpo = (string) $respuesta->body();
            $enviado = $respuesta->successful();
        } catch (\Throwable $e) {
            $cuerpo = $e->getMessage();
            $enviado = false;
        }

        $cuerpo = mb_strcut($cuerpo, 0, 60000);

        $pendiente->update([
            'enviado' => $enviado,
            'respuesta' => $cuerpo,
        ]);

        return [
            'nrofactura' => $pendiente->nrofactura,
            'enviado' => $enviado,
            'respuesta' => $cuerpo,
        ];
    }
}
