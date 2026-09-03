<?php

namespace App\Http\Controllers;

use App\Models\DetalleFactura;
use App\Models\Factura;
use App\Models\FacturaPendiente;
use App\Models\ParametroEfactura;
use App\Services\EFacturaBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FacturaController extends Controller
{
    public function index(Request $request)
    {
        $facturas = Factura::query()
            ->leftJoinSub(
                DetalleFactura::query()
                    ->select('NroFactura')
                    ->selectRaw('COUNT(*) as items')
                    ->selectRaw('COALESCE(SUM(Cantidad * PrecioVenta - descuento), 0) as total')
                    ->whereNotNull('NroFactura')
                    ->groupBy('NroFactura'),
                'detalle',
                'facturas.NroFactura',
                '=',
                'detalle.NroFactura'
            )
            ->leftJoin('clientes', 'clientes.IdCliente', '=', 'facturas.IdCliente')
            ->when($request->filled('q'), function ($query) use ($request) {
                $query->where('facturas.NroFactura', 'like', '%'.$request->string('q').'%');
            })
            ->when($request->filled('cliente'), function ($query) use ($request) {
                $query->where('clientes.NombreEmpresa', 'like', '%'.$request->string('cliente').'%');
            })
            ->when($request->filled('tipo'), function ($query) use ($request) {
                $query->where('facturas.TipoFactura', $request->string('tipo'));
            })
            ->when($this->filtroFechaValido($request, 'desde'), function ($query) use ($request) {
                $query->whereDate('facturas.FechaFactura', '>=', $request->string('desde'));
            })
            ->when($this->filtroFechaValido($request, 'hasta'), function ($query) use ($request) {
                $query->whereDate('facturas.FechaFactura', '<=', $request->string('hasta'));
            })
            ->select(
                'facturas.*',
                'detalle.items',
                'detalle.total',
                'clientes.NombreEmpresa as nombre_cliente'
            )
            ->orderByDesc('facturas.FechaFactura')
            ->orderByDesc('facturas.NroFactura')
            ->paginate(10)
            ->withQueryString();

        $pendientes = FacturaPendiente::query()
            ->whereIn('nrofactura', $facturas->pluck('NroFactura'))
            ->get()
            ->keyBy('nrofactura');

        return view('facturas.index', [
            'facturas' => $facturas,
            'tipos' => ['Contado', 'Credito'],
            'pendientes' => $pendientes,
        ]);
    }

    public function show(string $factura)
    {
        $factura = Factura::query()
            ->leftJoin('clientes', 'clientes.IdCliente', '=', 'facturas.IdCliente')
            ->select(
                'facturas.*',
                'clientes.NombreEmpresa as nombre_cliente',
                'clientes.RUC as ruc_cliente'
            )
            ->where('facturas.NroFactura', $factura)
            ->first();

        abort_unless($factura, 404);

        $detalles = DetalleFactura::query()
            ->where('NroFactura', $factura->NroFactura)
            ->orderBy('IdItem')
            ->get();

        return view('facturas.show', [
            'factura' => $factura,
            'detalles' => $detalles,
        ]);
    }

    public function generarJson(string $factura)
    {
        $factura = Factura::query()->where('NroFactura', $factura)->first();
        abort_unless($factura, 404);

        try {
            $payload = EFacturaBuilder::build($factura);
        } catch (RuntimeException $e) {
            abort(422, $e->getMessage());
        }

        FacturaPendiente::query()->updateOrCreate(
            ['nrofactura' => $factura->NroFactura],
            [
                'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
                'enviado' => false,
                'respuesta' => null,
            ]
        );

        return response()->json([
            'nrofactura' => $factura->NroFactura,
            'payload' => $payload,
        ]);
    }

    public function enviar(string $factura)
    {
        $pendiente = FacturaPendiente::query()->where('nrofactura', $factura)->first();
        abort_unless($pendiente, 422, 'Primero generá el JSON de la factura.');

        $parametros = ParametroEfactura::registroUnico();
        abort_unless($parametros->api_url, 422, 'Configurá la API URL para el envío.');

        $payload = json_decode($pendiente->payload, true) ?: [];

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

        return response()->json([
            'nrofactura' => $factura,
            'enviado' => $enviado,
            'respuesta' => $cuerpo,
        ]);
    }

    public function respuesta(string $factura)
    {
        $pendiente = FacturaPendiente::query()->where('nrofactura', $factura)->first();
        abort_unless($pendiente, 404);

        return response()->json([
            'nrofactura' => $pendiente->nrofactura,
            'enviado' => (bool) $pendiente->enviado,
            'respuesta' => $pendiente->respuesta,
        ]);
    }

    private function filtroFechaValido(Request $request, string $campo): bool
    {
        return $request->filled($campo) && strtotime((string) $request->string($campo)) !== false;
    }
}
