<?php

namespace App\Http\Controllers;

use App\Jobs\EnviarPendienteJob;
use App\Models\DetalleFactura;
use App\Models\Factura;
use App\Models\FacturaPendiente;
use App\Services\EnvioEfacturaService;
use App\Services\PuntopanConexion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use RuntimeException;

class FacturaController extends Controller
{
    public function index(Request $request)
    {
        $facturas = $this->consultaFiltrada($request)
            ->paginate(10)
            ->withQueryString();

        $pendientes = FacturaPendiente::query()
            ->whereIn('nrofactura', $facturas->pluck('NroFactura'))
            ->get()
            ->keyBy('nrofactura');

        return view('facturas.index', [
            'facturas' => $facturas,
            'tipos' => ['Contado', 'Credito'],
            'estados' => ['enviado' => 'Enviado', 'pendiente' => 'Pendiente', 'sin' => 'Sin estado'],
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
            $payload = app(EnvioEfacturaService::class)->preparar($factura);
        } catch (RuntimeException $e) {
            abort(422, $e->getMessage());
        }

        return response()->json([
            'nrofactura' => $factura->NroFactura,
            'payload' => $payload,
        ]);
    }

    public function enviar(string $factura)
    {
        $pendiente = FacturaPendiente::query()->where('nrofactura', $factura)->first();
        abort_unless($pendiente, 422, 'Primero generá el JSON de la factura.');

        try {
            $resultado = app(EnvioEfacturaService::class)->enviar($pendiente);
        } catch (RuntimeException $e) {
            abort(422, $e->getMessage());
        }

        return response()->json($resultado);
    }

    public function enviarPendientes(Request $request)
    {
        $validated = $request->validate([
            'nrofacturas' => ['required', 'array', 'min:1'],
            'nrofacturas.*' => ['required', 'string', 'max:255'],
            'confirmar_local' => ['nullable', 'boolean'],
        ]);

        $nros = array_values(array_unique($validated['nrofacturas']));

        $conexion = app(PuntopanConexion::class);
        $enCopiaLocal = $conexion->usandoCopiaLocal();

        // En la copia local los envíos necesitan consentimiento explícito: el
        // worker no tiene sesión y va a preparar los comprobantes leyendo una
        // base que puede estar desactualizada. Con `forzado` no se pregunta: esa
        // ya es una decisión explícita de configuración (desarrollo y tests).
        if ($enCopiaLocal && ! $conexion->forzado() && ! $request->boolean('confirmar_local')) {
            return response()->json([
                'message' => 'Estás trabajando con la copia local de puntopan: confirmá el envío contra la copia local para continuar.',
                'requiere_confirmacion_local' => true,
                'aprobado_hasta' => $conexion->aprobacionEfectivaHasta()?->format('H:i'),
            ], 409);
        }

        $enviadas = FacturaPendiente::query()
            ->whereIn('nrofactura', $nros)
            ->where('enviado', true)
            ->pluck('nrofactura')
            ->all();

        $existentes = Factura::query()
            ->whereIn('NroFactura', $nros)
            ->pluck('NroFactura')
            ->all();

        $aEncolar = array_values(array_intersect(array_values(array_diff($nros, $enviadas)), $existentes));

        $omitidas = count($nros) - count($aEncolar);

        if ($aEncolar !== []) {
            if ($enCopiaLocal && ! $conexion->forzado()) {
                // Sin esto el worker no puede leer puntopan: la aprobación del
                // navegador no cruza a procesos sin sesión.
                $conexion->aprobarParaLaCola();
            }

            Bus::chain(array_map(
                fn (string $nro) => new EnviarPendienteJob($nro),
                $aEncolar
            ))->dispatch();
        }

        return response()->json([
            'encoladas' => count($aEncolar),
            'omitidas' => $omitidas,
            'nros' => $aEncolar,
            'copia_local' => $enCopiaLocal,
        ]);
    }

    public function estadoPendientes(Request $request)
    {
        $validated = $request->validate([
            'nrofacturas' => ['required', 'array'],
            'nrofacturas.*' => ['required', 'string', 'max:255'],
        ]);

        $nros = array_values(array_unique($validated['nrofacturas']));

        $pendientes = FacturaPendiente::query()
            ->whereIn('nrofactura', $nros)
            ->where('enviado', false)
            ->whereNull('respuesta')
            ->count();

        return response()->json(['pendientes' => $pendientes]);
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

    private function consultaFiltrada(Request $request): Builder
    {
        return Factura::query()
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
            ->when($request->filled('estado'), function ($query) use ($request) {
                $estado = (string) $request->string('estado');

                if ($estado === 'enviado') {
                    $query->whereIn('facturas.NroFactura', FacturaPendiente::query()->where('enviado', true)->pluck('nrofactura')->all());
                } elseif ($estado === 'pendiente') {
                    $query->whereIn('facturas.NroFactura', FacturaPendiente::query()->where('enviado', false)->pluck('nrofactura')->all());
                } elseif ($estado === 'sin') {
                    $query->whereNotIn('facturas.NroFactura', FacturaPendiente::query()->pluck('nrofactura')->all());
                }
            })
            ->select(
                'facturas.*',
                'detalle.items',
                'detalle.total',
                'clientes.NombreEmpresa as nombre_cliente'
            )
            ->orderByDesc('facturas.FechaFactura')
            ->orderByDesc('facturas.NroFactura');
    }

    public function exportar(Request $request)
    {
        $facturas = $this->consultaFiltrada($request)->get();

        $pendientes = FacturaPendiente::query()
            ->whereIn('nrofactura', $facturas->pluck('NroFactura'))
            ->get()
            ->keyBy('nrofactura');

        $estado = function (Factura $factura) use ($pendientes): string {
            $pendiente = $pendientes[$factura->NroFactura] ?? null;

            if ($pendiente === null) {
                return '';
            }

            return $pendiente->enviado ? 'Enviado' : 'Pendiente';
        };

        $filas = $facturas->map(fn (Factura $factura) => [
            (string) $factura->NroFactura,
            substr((string) $factura->FechaFactura, 0, 10),
            (string) $factura->nombre_cliente,
            (string) $factura->TipoFactura,
            (string) $factura->SituFactura,
            (int) $factura->items,
            (int) round((float) $factura->total),
            $estado($factura),
        ]);

        $celda = static fn ($valor) => is_int($valor)
            ? (string) $valor
            : '"'.str_replace('"', '""', (string) $valor).'"';

        $csv = "\xEF\xBB\xBF";
        $csv .= implode(',', array_map($celda, [
            'N° Factura',
            'Fecha',
            'Cliente',
            'Tipo',
            'Situación',
            'Ítems',
            'Total',
            'Estado',
        ]))."\r\n";

        foreach ($filas as $fila) {
            $csv .= implode(',', array_map($celda, $fila))."\r\n";
        }

        $nombre = 'facturas_'.date('Ymd_His').'.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$nombre.'"',
        ]);
    }

    private function filtroFechaValido(Request $request, string $campo): bool
    {
        return $request->filled($campo) && strtotime((string) $request->string($campo)) !== false;
    }
}
