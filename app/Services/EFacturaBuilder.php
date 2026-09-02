<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\DetalleFactura;
use App\Models\Factura;
use App\Models\ParametroEfactura;
use Carbon\Carbon;
use Illuminate\Support\Str;
use RuntimeException;

class EFacturaBuilder
{
    public static function build(Factura $factura): array
    {
        $parametros = ParametroEfactura::registroUnico();
        $cliente = $factura->IdCliente
            ? Cliente::query()->where('IdCliente', $factura->IdCliente)->first()
            : null;

        $lineas = DetalleFactura::query()
            ->where('NroFactura', $factura->NroFactura)
            ->where('Cantidad', '>', 0)
            ->orderBy('IdItem')
            ->get();

        if ($lineas->isEmpty()) {
            throw new RuntimeException('La factura no tiene líneas válidas.');
        }

        [$establecimiento, $puntoExpedicion, $documentoNro] = array_pad(explode('-', $factura->NroFactura), 3, '0');

        $total = (int) round($lineas->sum(
            fn ($l) => (float) $l->Cantidad * (float) $l->PrecioVenta - (float) $l->descuento
        ));

        return [
            'contribuyente' => [
                'contribuyenteid' => (int) $parametros->contribuyente_id,
                'pass' => $parametros->pass,
            ],
            'timbrado' => [
                'timbrado' => $parametros->timbrado,
                'establecimiento' => $establecimiento,
                'puntoExpedicion' => $puntoExpedicion,
                'documentoNro' => $documentoNro,
                'fecIni' => $parametros->fec_inicio?->format('Y-m-d\TH:i:s-04:00'),
            ],
            'sucursal' => $parametros->sucursal,
            'receptor' => self::receptor($cliente),
            'fecha' => self::fechaComprobante($factura->FechaFactura),
            'condicionOperacion' => $factura->TipoFactura === 'Credito'
                ? ['condicion' => 2, 'operacionTipo' => 1, 'plazoCredito' => '30 dias']
                : ['condicion' => 1, 'tiposPagos' => [['tipoPagoCodigo' => 1, 'monto' => $total]]],
            'detalles' => $lineas->map(fn ($l) => [
                'itemCodigo' => $l->IdMercaderia,
                'itemDescripcion' => trim((string) $l->Descripcion),
                'cantidad' => self::numero($l->Cantidad),
                'precioUnitario' => (int) round((float) $l->PrecioVenta),
                'afectacionTributaria' => 1,
                'proporcionIVA' => 100,
                'tasaIVA' => 10,
            ])->values()->all(),
            'totalComprobante' => $total,
        ];
    }

    private static function receptor(?Cliente $cliente): array
    {
        $nombre = $cliente ? trim((string) $cliente->NombreEmpresa) : '';
        $doc = $cliente ? trim((string) $cliente->RUC) : '';

        $esInnominado = $nombre === ''
            || in_array(Str::upper($nombre), ['SELECCIONAR CLIENTE', 'SIN NOMBRE', 'XXX SIN NOMBRE'], true)
            || $doc === ''
            || $doc === '0';

        if ($esInnominado) {
            return [
                'tipoDocumento' => '5',
                'docNro' => '0',
                'razonSocial' => 'XXX SIN NOMBRE',
            ];
        }

        if (Str::contains($doc, '-')) {
            [$base, $dv] = array_pad(explode('-', $doc, 2), 2, '');

            return [
                'docNro' => $base,
                'dv' => $dv,
                'razonSocial' => $nombre,
            ];
        }

        return [
            'tipoDocumento' => '1',
            'docNro' => $doc,
            'razonSocial' => $nombre,
        ];
    }

    private static function fechaComprobante(mixed $fecha): string
    {
        $valor = $fecha && (string) $fecha !== '0000-00-00 00:00:00'
            ? Carbon::parse($fecha)->format('Y-m-d\TH:i:s')
            : Carbon::now()->format('Y-m-d\TH:i:s');

        return $valor.'-04:00';
    }

    private static function numero(mixed $valor): int|float
    {
        $n = (float) $valor;

        return $n == (int) $n ? (int) $n : $n;
    }
}
