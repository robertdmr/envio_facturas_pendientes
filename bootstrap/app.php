<?php

use App\Http\Middleware\AsegurarConexionPuntopan;
use App\Services\PuntopanConexion;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'puntopan.conexion' => AsegurarConexionPuntopan::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Defensa en profundidad: si el sondeo dijo que el remoto estaba vivo
        // pero la consulta falla igualmente, se pide la aprobación en lugar de
        // mostrar un 500.
        $exceptions->render(function (QueryException $e, Request $request) {
            if ($e->getConnectionName() !== 'puntopan') {
                return null;
            }

            $conexion = app(PuntopanConexion::class);

            // La copia local ya está aprobada (navegador o cola) y aun así falló:
            // es un error real, que se muestre tal cual.
            if ($conexion->aprobado()) {
                return null;
            }

            $conexion->olvidarSondeo();

            $mensaje = 'No se pudo conectar al servidor remoto de puntopan.';

            if (! $conexion->permitido() || ! $conexion->localDisponible()) {
                $mensaje .= ' La copia local no está disponible.';

                return $request->expectsJson()
                    ? response()->json(['message' => $mensaje], 503)
                    : response()->view('puntopan.no-disponible', [
                        'mensaje' => $mensaje,
                        'resumen' => $conexion->resumen(),
                    ], 503);
            }

            if ($request->isMethod('GET')) {
                $request->session()->put(PuntopanConexion::CLAVE_URL_PRETENDIDA, $request->fullUrl());
            }

            return $request->expectsJson()
                ? response()->json(['message' => $mensaje.' Confirmá el uso de la copia local.'], 503)
                : redirect()->route('puntopan.conexion-local.editar');
        });
    })->create();
