<?php

namespace App\Http\Controllers;

use App\Models\FacturaPendiente;
use App\Models\Fecdc;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class FacturaPendienteController extends Controller
{
    public function index(Request $request)
    {
        $pendientes = FacturaPendiente::query()
            ->when($request->filled('q'), function ($query) use ($request) {
                $query->where('nrofactura', 'like', '%'.$request->string('q').'%');
            })
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $fecdcs = Fecdc::query()
            ->whereIn('nrofactura', $pendientes->pluck('nrofactura'))
            ->orderByDesc('id')
            ->get()
            ->groupBy('nrofactura');

        return view('pendientes.index', [
            'pendientes' => $pendientes,
            'fecdcs' => $fecdcs,
        ]);
    }

    public function actualizarCdc(string $nrofactura)
    {
        $pendiente = FacturaPendiente::query()->where('nrofactura', $nrofactura)->first();
        abort_unless($pendiente, 404);

        $cdc = $pendiente->cdc();
        abort_if($cdc === null, 422, 'La respuesta no contiene un cdc.');

        try {
            $filas = $this->actualizarFecdc([$nrofactura => $cdc]);
        } catch (Throwable $e) {
            abort(503, 'No se pudo actualizar fecdc: '.$e->getMessage());
        }

        return response()->json([
            'nrofactura' => $nrofactura,
            'cdc' => $cdc,
            'filas_actualizadas' => $filas,
        ]);
    }

    public function actualizarCdcLote(Request $request)
    {
        $validated = $request->validate([
            'nrofacturas' => ['required', 'array', 'min:1', 'max:2000'],
            'nrofacturas.*' => ['required', 'string', 'max:255'],
        ]);

        $nros = array_values(array_unique($validated['nrofacturas']));

        $pendientes = FacturaPendiente::query()
            ->whereIn('nrofactura', $nros)
            ->get()
            ->keyBy('nrofactura');

        $cdcPorNro = [];
        $omitidas = 0;

        foreach ($nros as $nro) {
            $cdc = $pendientes->get($nro)?->cdc();

            if ($cdc === null) {
                $omitidas++;

                continue;
            }

            $cdcPorNro[$nro] = $cdc;
        }

        $filas = 0;

        foreach (array_chunk($cdcPorNro, 200, true) as $chunk) {
            try {
                $filas += $this->actualizarFecdc($chunk);
            } catch (Throwable) {
                $omitidas += count($chunk);
            }
        }

        return response()->json([
            'actualizadas' => count($cdcPorNro),
            'omitidas' => $omitidas,
            'filas' => $filas,
        ]);
    }

    /**
     * Actualiza el cdc en fecdc en la menor cantidad de sentencias posibles
     * contra el servidor remoto (cada round-trip cuesta cientos de ms).
     *
     * Primero resuelve los `id` con un solo SELECT (la columna `nrofactura` no
     * tiene índice, así que un UPDATE por `nrofactura` haría un full scan lento
     * y con locks amplios). Luego actualiza por clave primaria con un único
     * UPDATE usando CASE, limitando los locks a las filas modificadas.
     *
     * @param  array<string, string>  $cdcPorNro
     */
    private function actualizarFecdc(array $cdcPorNro): int
    {
        if ($cdcPorNro === []) {
            return 0;
        }

        $filas = Fecdc::query()
            ->whereIn('nrofactura', array_keys($cdcPorNro))
            ->get(['id', 'nrofactura']);

        if ($filas->isEmpty()) {
            return 0;
        }

        $ahora = now()->format('j/n/Y G:i:s');
        $total = 0;

        foreach ($filas->chunk(200) as $chunk) {
            $casos = [];
            $ids = [];
            $bindings = [];

            foreach ($chunk as $fila) {
                $cdc = $cdcPorNro[$fila->nrofactura] ?? null;

                if ($cdc === null) {
                    continue;
                }

                $ids[] = $fila->id;
                $casos[] = 'WHEN ? THEN ?';
                $bindings[] = $fila->id;
                $bindings[] = $cdc;
            }

            if ($ids === []) {
                continue;
            }

            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql = 'UPDATE `fecdc` SET `cdc` = CASE `id` '.implode(' ', $casos).' ELSE `cdc` END, '
                .'`fecha_actualizacion` = ?, `estado` = ? WHERE `id` IN ('.$placeholders.')';

            $bindings[] = $ahora;
            $bindings[] = 'aprobado';

            foreach ($ids as $id) {
                $bindings[] = $id;
            }

            $total += DB::connection('puntopan')->update($sql, $bindings);
        }

        return $total;
    }
}
