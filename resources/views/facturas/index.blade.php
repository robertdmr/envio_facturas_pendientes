@extends('layouts.app')

@section('title', 'Listado de facturas')

@php
    $money = fn ($value) => number_format((float) $value, 0, ',', '.');
    $q = request()->query('q', '');
    $desde = request()->query('desde', '');
    $hasta = request()->query('hasta', '');
    $tipo = request()->query('tipo', '');
@endphp

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold tracking-tight text-gray-900">Listado de facturas</h1>
        <p class="mt-1 text-sm text-gray-500">Facturas de <span class="font-medium text-gray-700">puntopan</span> — base en modo solo lectura.</p>
    </div>

    <form method="GET" action="{{ route('facturas.index') }}"
          class="mb-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">
            <div class="xl:col-span-2">
                <label for="q" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">N° factura</label>
                <input type="text" id="q" name="q" value="{{ $q }}" placeholder="001-001-0000000"
                       class="block w-full rounded-lg border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <label for="desde" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">Desde</label>
                <input type="date" id="desde" name="desde" value="{{ $desde }}"
                       class="block w-full rounded-lg border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <label for="hasta" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">Hasta</label>
                <input type="date" id="hasta" name="hasta" value="{{ $hasta }}"
                       class="block w-full rounded-lg border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <label for="tipo" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">Tipo</label>
                <select id="tipo" name="tipo"
                        class="block w-full rounded-lg border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Todos</option>
                    @foreach ($tipos as $opcion)
                        <option value="{{ $opcion }}" @selected($tipo === $opcion)>{{ $opcion }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 pt-4">
            <div class="flex items-center gap-2">
                <a href="{{ route('facturas.index') }}"
                   class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">
                    Limpiar
                </a>
                <button type="submit"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    Filtrar
                </button>
            </div>
        </div>
    </form>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-gray-600">N° Factura</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-600">Fecha</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-600">Cliente</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-600">Tipo</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-600">Situación</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">Ítems</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">Total</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-600">Estado</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($facturas as $factura)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 whitespace-nowrap">
                                <a href="{{ route('facturas.show', $factura->NroFactura) }}"
                                   class="font-medium text-indigo-600 hover:underline">
                                    {{ $factura->NroFactura }}
                                </a>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-gray-600">{{ substr((string) $factura->FechaFactura, 0, 10) }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $factura->nombre_cliente }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $factura->TipoFactura === 'Credito' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800' }}">
                                    {{ $factura->TipoFactura }}
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-gray-600">{{ $factura->SituFactura }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-gray-700">{{ number_format((float) $factura->items) }}</td>
                            <td class="px-4 py-3 text-right font-medium tabular-nums text-gray-900">{{ $money($factura->total) }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if ($pendientes[$factura->NroFactura] ?? null)
                                    @if ($pendientes[$factura->NroFactura]->enviado)
                                        <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-800">Enviado</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800">Pendiente</span>
                                    @endif
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="flex items-center justify-end gap-2">
                                    @if (!empty(($pendientes[$factura->NroFactura]->respuesta ?? null)))
                                        <button type="button" title="Ver respuesta del endpoint" data-factura="{{ $factura->NroFactura }}"
                                                class="js-ver-respuesta inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-xs font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-100">
                                            Ver respuesta
                                        </button>
                                    @endif
                                    <button type="button" title="Reenviar factura (próximamente)" data-factura="{{ $factura->NroFactura }}"
                                            class="inline-flex items-center gap-1.5 rounded-lg border border-indigo-200 bg-indigo-50 px-2.5 py-1.5 text-xs font-medium text-indigo-700 transition hover:border-indigo-300 hover:bg-indigo-100">
                                        Reenviar
                                    </button>
                                    <button type="button" title="Generar JSON" data-factura="{{ $factura->NroFactura }}"
                                            class="js-generar-json inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-700 transition hover:border-gray-300 hover:bg-gray-50">
                                        Generar JSON
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-10 text-center text-gray-500">
                                No hay facturas que coincidan con los filtros.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $facturas->links() }}
    </div>

    <div id="json-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4">
        <div class="absolute inset-0 bg-gray-900/60" data-close-json></div>
        <div class="relative z-10 flex max-h-[85vh] w-full max-w-3xl flex-col overflow-hidden rounded-xl bg-white shadow-xl">
            <div class="flex items-center gap-3 border-b border-gray-200 px-5 py-3">
                <h2 class="text-base font-semibold text-gray-900">JSON generado</h2>
                <div class="flex-1"></div>
                <button id="json-enviar" type="button"
                        class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50">
                    Enviar
                </button>
                <button type="button" class="rounded-md p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600" data-close-json aria-label="Cerrar">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>
            <p id="json-status" class="border-b border-gray-100 px-5 py-2 text-sm text-gray-600"></p>
            <pre id="json-content" class="flex-1 overflow-auto bg-gray-900 px-5 py-4 text-xs leading-relaxed text-emerald-300"></pre>
            <pre id="json-respuesta" class="hidden max-h-48 overflow-auto border-t border-gray-200 bg-gray-100 px-5 py-3 text-xs leading-relaxed text-gray-800"></pre>
        </div>
    </div>

    <script>
        (() => {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
            const modal = document.getElementById('json-modal');
            const content = document.getElementById('json-content');
            const status = document.getElementById('json-status');
            const respuesta = document.getElementById('json-respuesta');
            const enviarBtn = document.getElementById('json-enviar');
            let nroActual = null;
            let cambio = false;

            const close = () => {
                modal.classList.add('hidden');
                if (cambio) {
                    window.location.reload();
                    return;
                }
                nroActual = null;
                enviarBtn.disabled = true;
            };
            modal.querySelectorAll('[data-close-json]').forEach((el) => el.addEventListener('click', close));

            const mostrarError = (msg) => {
                content.textContent = '';
                status.textContent = msg || 'Error inesperado';
            };

            document.querySelectorAll('.js-generar-json').forEach((btn) => {
                btn.addEventListener('click', async () => {
                    nroActual = btn.dataset.factura;
                    cambio = false;
                    content.textContent = 'Generando…';
                    status.textContent = '';
                    respuesta.classList.add('hidden');
                    enviarBtn.disabled = true;
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');

                    try {
                        const res = await fetch('/facturas/' + encodeURIComponent(nroActual) + '/json', {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                        });
                        let data = null;
                        try {
                            data = await res.json();
                        } catch (e) {
                            data = null;
                        }
                        if (!res.ok || !data) {
                            throw new Error((data && data.message) || 'Error al generar el JSON (' + res.status + ')');
                        }
                        content.textContent = JSON.stringify(data.payload, null, 2);
                        status.textContent = 'Guardado como pendiente de envío · ' + data.nrofactura;
                        enviarBtn.disabled = false;
                        cambio = true;
                    } catch (err) {
                        mostrarError(err.message);
                    }
                });
            });

            enviarBtn.addEventListener('click', async () => {
                if (!nroActual) return;
                enviarBtn.disabled = true;
                status.textContent = 'Enviando…';
                try {
                    const res = await fetch('/facturas/' + encodeURIComponent(nroActual) + '/enviar', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    });
                    let data = null;
                    try {
                        data = await res.json();
                    } catch (e) {
                        data = null;
                    }
                    if (!res.ok || !data) {
                        throw new Error((data && data.message) || 'Error al enviar (' + res.status + ')');
                    }
                    status.textContent = data.enviado
                        ? 'Enviado correctamente · ' + data.nrofactura
                        : 'Respuesta del endpoint (no aceptado) · ' + data.nrofactura;
                    respuesta.textContent = data.respuesta || '(sin respuesta)';
                    respuesta.classList.remove('hidden');
                    cambio = true;
                } catch (err) {
                    status.textContent = err.message || 'Error inesperado';
                    enviarBtn.disabled = false;
                }
            });
        })();
    </script>

    <div id="respuesta-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4">
        <div class="absolute inset-0 bg-gray-900/60" data-close-respuesta></div>
        <div class="relative z-10 flex max-h-[85vh] w-full max-w-3xl flex-col overflow-hidden rounded-xl bg-white shadow-xl">
            <div class="flex items-center gap-3 border-b border-gray-200 px-5 py-3">
                <h2 class="text-base font-semibold text-gray-900">Respuesta del endpoint</h2>
                <span id="respuesta-nro" class="font-mono text-sm text-gray-500"></span>
                <div class="flex-1"></div>
                <button type="button" class="rounded-md p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600" data-close-respuesta aria-label="Cerrar">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>
            <p id="respuesta-estado" class="border-b border-gray-100 px-5 py-2 text-sm text-gray-600"></p>
            <pre id="respuesta-content" class="flex-1 overflow-auto bg-gray-900 px-5 py-4 text-xs leading-relaxed text-gray-100"></pre>
        </div>
    </div>

    <script>
        (() => {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
            const modal = document.getElementById('respuesta-modal');
            const content = document.getElementById('respuesta-content');
            const estado = document.getElementById('respuesta-estado');
            const nroLabel = document.getElementById('respuesta-nro');

            const close = () => modal.classList.add('hidden');
            modal.querySelectorAll('[data-close-respuesta]').forEach((el) => el.addEventListener('click', close));

            document.querySelectorAll('.js-ver-respuesta').forEach((btn) => {
                btn.addEventListener('click', async () => {
                    const nro = btn.dataset.factura;
                    nroLabel.textContent = '· ' + nro;
                    estado.textContent = 'Cargando…';
                    content.textContent = '';
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');

                    try {
                        const res = await fetch('/facturas/' + encodeURIComponent(nro) + '/respuesta', {
                            headers: { 'Accept': 'application/json' },
                        });
                        let data = null;
                        try {
                            data = await res.json();
                        } catch (e) {
                            data = null;
                        }
                        if (!res.ok || !data) {
                            throw new Error((data && data.message) || 'Error al obtener la respuesta (' + res.status + ')');
                        }
                        estado.textContent = data.enviado
                            ? 'Estado: Enviado'
                            : 'Estado: Pendiente';
                        estado.className = 'border-b px-5 py-2 text-sm ' + (data.enviado
                            ? 'border-emerald-100 bg-emerald-50 text-emerald-800'
                            : 'border-amber-100 bg-amber-50 text-amber-800');
                        const texto = data.respuesta || '(sin respuesta)';
                        try {
                            content.textContent = JSON.stringify(JSON.parse(texto), null, 2);
                        } catch (e) {
                            content.textContent = texto;
                        }
                    } catch (err) {
                        estado.className = 'border-b border-red-100 bg-red-50 px-5 py-2 text-sm text-red-800';
                        estado.textContent = err.message || 'Error inesperado';
                        content.textContent = '';
                    }
                });
            });
        })();
    </script>
@endsection
