<?php

use App\Http\Controllers\ConfiguracionController;
use App\Http\Controllers\FacturaController;
use Illuminate\Support\Facades\Route;

Route::get('/', [FacturaController::class, 'index'])->name('facturas.index');

Route::get('/facturas/{factura}', [FacturaController::class, 'show'])->name('facturas.show');

Route::get('/configuracion', [ConfiguracionController::class, 'edit'])->name('configuracion.edit');

Route::post('/configuracion', [ConfiguracionController::class, 'update'])->name('configuracion.update');
