<?php

namespace App\Http\Controllers;

use App\Models\ParametroEfactura;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConfiguracionController extends Controller
{
    public function edit(): View
    {
        return view('configuracion.edit', [
            'parametros' => ParametroEfactura::registroUnico(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'contribuyente_id' => ['required', 'integer'],
            'pass' => ['required', 'string', 'max:255'],
            'timbrado' => ['required', 'string', 'max:50'],
            'fec_inicio' => ['required', 'date'],
            'sucursal' => ['required', 'string', 'max:100'],
            'api_url' => ['nullable', 'url', 'max:255'],
        ]);

        ParametroEfactura::registroUnico()->update($validated);

        return redirect()->route('configuracion.edit')
            ->with('status', 'Configuración guardada correctamente.');
    }
}
