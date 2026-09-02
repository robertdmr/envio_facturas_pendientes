<?php

namespace App\Http\Controllers;

use App\Models\DetalleFactura;
use App\Models\Factura;
use Illuminate\Http\Request;

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
            ->paginate(20)
            ->withQueryString();

        return view('facturas.index', [
            'facturas' => $facturas,
            'tipos' => ['Contado', 'Credito'],
        ]);
    }

    private function filtroFechaValido(Request $request, string $campo): bool
    {
        return $request->filled($campo) && strtotime((string) $request->string($campo)) !== false;
    }
}
