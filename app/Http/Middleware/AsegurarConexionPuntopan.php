<?php

namespace App\Http\Middleware;

use App\Services\PuntopanConexion;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Se ejecuta antes de cualquier ruta que lea de `puntopan`.
 *
 * - Remoto disponible: no toca nada.
 * - Copia local aprobada: reescribe la conexión `puntopan` hacia el MySQL local.
 * - Remoto caído y copia local disponible: manda a la pantalla de confirmación,
 *   guardando la URL original para volver a ella tras aprobar.
 * - Sin alternativa (o respaldo prohibido): 503 con un mensaje claro.
 */
class AsegurarConexionPuntopan
{
    public function __construct(private PuntopanConexion $conexion) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET')) {
            $request->session()->put(PuntopanConexion::CLAVE_URL_PRETENDIDA, $request->fullUrl());
        }

        return match ($this->conexion->prepararConexion()) {
            PuntopanConexion::LOCAL_APROBADO,
            PuntopanConexion::REMOTO_OK => $next($request),

            PuntopanConexion::REQUIERE_APROBACION => $this->pedirAprobacion($request),

            default => $this->noDisponible($request),
        };
    }

    private function pedirAprobacion(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'El servidor remoto de puntopan no responde. Confirmá el uso de la copia local en '
                    .route('puntopan.conexion-local.editar').'.',
            ], 503);
        }

        return redirect()->route('puntopan.conexion-local.editar');
    }

    private function noDisponible(Request $request): Response
    {
        $mensaje = $this->conexion->permitido()
            ? 'No se pudo conectar al servidor remoto de puntopan y la copia local tampoco responde.'
            : 'El servidor remoto de puntopan no responde y el uso de la copia local está deshabilitado.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $mensaje], 503);
        }

        return response()->view('puntopan.no-disponible', [
            'mensaje' => $mensaje,
            'resumen' => $this->conexion->resumen(),
        ], 503);
    }
}
