<?php

use App\Http\Controllers\ConfiguracionController;
use App\Http\Controllers\FacturaController;
use App\Http\Controllers\FacturaPendienteController;
use App\Http\Controllers\PuntopanConexionController;
use Illuminate\Support\Facades\Route;

/*
| Páginas que leen de `puntopan`: pasan por el middleware que decide entre el
| servidor remoto y la copia local (con aprobación explícita del operador).
*/

Route::middleware('puntopan.conexion')->group(function () {
    Route::get('/', [FacturaController::class, 'index'])->name('facturas.index');

    Route::get('/facturas/exportar', [FacturaController::class, 'exportar'])->name('facturas.exportar');

    Route::get('/facturas/{factura}', [FacturaController::class, 'show'])->name('facturas.show');

    Route::post('/facturas/{factura}/json', [FacturaController::class, 'generarJson'])->name('facturas.generarJson');

    Route::post('/pendientes/enviar', [FacturaController::class, 'enviarPendientes'])->name('pendientes.enviar');

    Route::get('/pendientes', [FacturaPendienteController::class, 'index'])->name('pendientes.index');

    Route::post('/pendientes/actualizar-cdc', [FacturaPendienteController::class, 'actualizarCdcLote'])->name('pendientes.actualizarCdcLote');

    Route::post('/pendientes/{nrofactura}/cdc', [FacturaPendienteController::class, 'actualizarCdc'])->name('pendientes.actualizarCdc');
});

/*
| Rutas que no leen de `puntopan` (solo de la BD de la app o del endpoint externo).
*/
Route::post('/facturas/{factura}/enviar', [FacturaController::class, 'enviar'])->name('facturas.enviar');

Route::get('/facturas/{factura}/respuesta', [FacturaController::class, 'respuesta'])->name('facturas.respuesta');

Route::post('/pendientes/estado', [FacturaController::class, 'estadoPendientes'])->name('pendientes.estado');

Route::get('/configuracion', [ConfiguracionController::class, 'edit'])->name('configuracion.edit');

Route::post('/configuracion', [ConfiguracionController::class, 'update'])->name('configuracion.update');

/*
| Flujo de aprobación de la copia local. Fuera del middleware: es justamente lo
| que permite salir del estado "requiere aprobación".
*/
Route::get('/puntopan/conexion-local', [PuntopanConexionController::class, 'editar'])->name('puntopan.conexion-local.editar');

Route::post('/puntopan/conexion-local', [PuntopanConexionController::class, 'aprobar'])->name('puntopan.conexion-local.aprobar');

Route::post('/puntopan/conexion-local/reintentar', [PuntopanConexionController::class, 'reintentar'])->name('puntopan.conexion-local.reintentar');

Route::post('/puntopan/conexion-local/volver', [PuntopanConexionController::class, 'volver'])->name('puntopan.conexion-local.volver');
