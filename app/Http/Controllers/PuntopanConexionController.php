<?php

namespace App\Http\Controllers;

use App\Services\PuntopanConexion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Pide al operador que confirme (o rechace) el uso de la copia local de
 * `puntopan` cuando el servidor remoto no responde.
 *
 * Estas rutas quedan fuera del middleware AsegurarConexionPuntopan: son las que
 * permiten salir del estado `REQUIERE_APROBACION`.
 */
class PuntopanConexionController extends Controller
{
    public function __construct(private PuntopanConexion $conexion) {}

    public function editar(): View|Response
    {
        $resumen = $this->conexion->resumen();

        // Con el respaldo deshabilitado no hay nada que preguntar.
        if (! $resumen['permitido']) {
            return response()->view('puntopan.no-disponible', [
                'mensaje' => 'El servidor remoto de puntopan no responde y el uso de la copia local está deshabilitado.',
                'resumen' => $resumen,
            ], 503);
        }

        return view('puntopan.conexion-local', ['resumen' => $resumen]);
    }

    /**
     * Aprobación explícita del operador: a partir de aquí se lee la copia local
     * durante `ttl_aprobacion` segundos.
     */
    public function aprobar(Request $request): RedirectResponse
    {
        abort_unless($this->conexion->permitido(), 403, 'El uso de la copia local está deshabilitado.');

        if (! $this->conexion->aprobar()) {
            return redirect()->route('facturas.index')
                ->with('error', 'No hay sesión activa: no se pudo registrar la aprobación.');
        }

        return redirect()
            ->to($request->session()->pull(PuntopanConexion::CLAVE_URL_PRETENDIDA, route('facturas.index')))
            ->with('estado', 'Aprobado: trabajando con la copia local de puntopan.');
    }

    /**
     * Reintenta el servidor remoto descartando la aprobación vigente.
     */
    public function reintentar(Request $request): RedirectResponse
    {
        $this->conexion->revocar();
        $this->conexion->olvidarSondeo();

        if (! $this->conexion->remotoDisponible()) {
            return redirect()->route('puntopan.conexion-local.editar')
                ->with('error', 'El servidor remoto sigue sin responder.');
        }

        return redirect()
            ->to($request->session()->pull(PuntopanConexion::CLAVE_URL_PRETENDIDA, route('facturas.index')))
            ->with('estado', 'Conexión restablecida con el servidor remoto de puntopan.');
    }

    /**
     * Desde el banner: abandonar la copia local y volver al remoto.
     */
    public function volver(): RedirectResponse
    {
        $this->conexion->revocar();
        $this->conexion->olvidarSondeo();

        return redirect()->route('facturas.index')
            ->with('estado', 'Se retomó el servidor remoto de puntopan.');
    }
}
