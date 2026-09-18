<?php

use App\Http\Controllers\ConfiguracionController;
use App\Http\Controllers\FacturaController;
use App\Http\Controllers\FacturaPendienteController;
use Illuminate\Support\Facades\Route;

Route::get('/', [FacturaController::class, 'index'])->name('facturas.index');

Route::get('/facturas/exportar', [FacturaController::class, 'exportar'])->name('facturas.exportar');

Route::get('/facturas/{factura}', [FacturaController::class, 'show'])->name('facturas.show');

Route::post('/facturas/{factura}/json', [FacturaController::class, 'generarJson'])->name('facturas.generarJson');

Route::post('/facturas/{factura}/enviar', [FacturaController::class, 'enviar'])->name('facturas.enviar');

Route::get('/facturas/{factura}/respuesta', [FacturaController::class, 'respuesta'])->name('facturas.respuesta');

Route::post('/pendientes/enviar', [FacturaController::class, 'enviarPendientes'])->name('pendientes.enviar');

Route::post('/pendientes/estado', [FacturaController::class, 'estadoPendientes'])->name('pendientes.estado');

Route::get('/pendientes', [FacturaPendienteController::class, 'index'])->name('pendientes.index');

Route::post('/pendientes/actualizar-cdc', [FacturaPendienteController::class, 'actualizarCdcLote'])->name('pendientes.actualizarCdcLote');

Route::post('/pendientes/{nrofactura}/cdc', [FacturaPendienteController::class, 'actualizarCdc'])->name('pendientes.actualizarCdc');

Route::get('/configuracion', [ConfiguracionController::class, 'edit'])->name('configuracion.edit');

Route::post('/configuracion', [ConfiguracionController::class, 'update'])->name('configuracion.update');
