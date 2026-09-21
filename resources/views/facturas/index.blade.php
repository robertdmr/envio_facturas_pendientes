@extends('layouts.app')

@section('title', 'Listado de facturas')

@php
$money = fn ($value) => number_format((float) $value, 0, ',', '.');
$q = request()->query('q', '');
$cliente = request()->query('cliente', '');
$desde = request()->query('desde', '');
$hasta = request()->query('hasta', '');
$tipo = request()->query('tipo', '');
$estadoFiltro = request()->query('estado', '');
@endphp

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-semibold tracking-tight text-gray-900">Listado de facturas</h1>
    <p class="mt-1 text-sm text-gray-500">Facturas de <span class="font-medium text-gray-700">puntopan</span> — base en
        modo solo lectura.</p>
</div>

<form method="GET" action="{{ route('facturas.index') }}"
    class="mb-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-6">
        <div class="xl:col-span-2">
            <label for="q" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">N°
                factura</label>
            <input type="text" id="q" name="q" value="{{ $q }}" placeholder="001-001-0000000"
                class="block w-full rounded-lg border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div class="xl:col-span-2">
            <label for="tipo"
                class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">Tipo</label>
            <select id="tipo" name="tipo"
                class="block w-full rounded-lg border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Todos</option>
                @foreach ($tipos as $opcion)
                <option value="{{ $opcion }}" @selected($tipo===$opcion)>{{ $opcion }}</option>
                @endforeach
            </select>
        </div>
        <div class="xl:col-span-2">
            <label for="cliente"
                class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">Cliente</label>
            <input type="text" id="cliente" name="cliente" value="{{ $cliente }}" placeholder="Nombre del cliente"
                class="block w-full rounded-lg border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div class="xl:col-span-2">
            <label for="desde"
                class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">Desde</label>
            <input type="date" id="desde" name="desde" value="{{ $desde }}"
                class="block w-full rounded-lg border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div class="xl:col-span-2">
            <label for="hasta"
                class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">Hasta</label>
            <input type="date" id="hasta" name="hasta" value="{{ $hasta }}"
                class="block w-full rounded-lg border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div class="xl:col-span-2">
            <label for="estado"
                class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">Estado</label>
            <select id="estado" name="estado"
                class="block w-full rounded-lg border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Todos</option>
                @foreach ($estados as $valor => $etiqueta)
                <option value="{{ $valor }}" @selected($estadoFiltro===$valor)>{{ $etiqueta }}</option>
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
            <button type="submit" formaction="{{ route('facturas.exportar') }}"
                class="rounded-lg border border-emerald-600 bg-white px-4 py-2 text-sm font-medium text-emerald-700 shadow-sm hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
                Exportar a Excel
            </button>
        </div>
    </div>
</form>

<div
    class="mb-3 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-2.5">
    <p class="text-sm text-indigo-900">
        <span id="grupo-contador">0</span> factura(s) seleccionada(s) para enviar
    </p>
    <div class="flex items-center gap-2">
        <button id="limpiar-seleccion" type="button"
            class="rounded-lg border border-indigo-300 bg-white px-3 py-2 text-sm font-medium text-indigo-700 shadow-sm hover:bg-indigo-100">
            Limpiar selección
        </button>
        <button id="btn-enviar-grupo" type="button" disabled
            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50">
            Enviar seleccionadas
        </button>
    </div>
</div>
<p id="grupo-status" class="mb-3 hidden text-sm text-gray-600"></p>

<div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="w-10 px-4 py-3">
                        <input type="checkbox" id="check-todas"
                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            aria-label="Seleccionar todas las de la página">
                    </th>
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
                    <td class="px-4 py-3">
                        <input type="checkbox"
                            class="js-fila rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            data-nro="{{ $factura->NroFactura }}" aria-label="Seleccionar {{ $factura->NroFactura }}">
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        <a href="{{ route('facturas.show', $factura->NroFactura) }}"
                            class="font-medium text-indigo-600 hover:underline">
                            {{ $factura->NroFactura }}
                        </a>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-gray-600">{{ substr((string) $factura->FechaFactura, 0,
                        10) }}</td>
                    <td class="px-4 py-3 text-gray-700">{{ $factura->nombre_cliente }}</td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        <span
                            class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $factura->TipoFactura === 'Credito' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800' }}">
                            {{ $factura->TipoFactura }}
                        </span>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-gray-600">{{ $factura->SituFactura }}</td>
                    <td class="px-4 py-3 text-right tabular-nums text-gray-700">{{ number_format((float)
                        $factura->items) }}</td>
                    <td class="px-4 py-3 text-right font-medium tabular-nums text-gray-900">{{ $money($factura->total)
                        }}</td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        @if ($pendientes[$factura->NroFactura] ?? null)
                        @if ($pendientes[$factura->NroFactura]->enviado)
                        <span
                            class="inline-flex rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-800">Enviado</span>
                        @else
                        <span
                            class="inline-flex rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800">Pendiente</span>
                        @endif
                        @endif
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        <div class="flex items-center justify-end gap-2">
                            @if (($pendientes[$factura->NroFactura] ?? null))
                            <button type="button" title="Ver respuesta del endpoint"
                                data-factura="{{ $factura->NroFactura }}"
                                class="js-ver-respuesta inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-xs font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-100">
                                Ver respuesta
                            </button>
                            @endif
                            <button type="button" title="Generar JSON" data-factura="{{ $factura->NroFactura }}"
                                class="js-generar-json inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-700 transition hover:border-gray-300 hover:bg-gray-50">
                                Generar JSON
                            </button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="10" class="px-4 py-10 text-center text-gray-500">
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
    <div
        class="relative z-10 flex max-h-[85vh] w-full max-w-3xl flex-col overflow-hidden rounded-xl bg-white shadow-xl">
        <div class="flex items-center gap-3 border-b border-gray-200 px-5 py-3">
            <h2 class="text-base font-semibold text-gray-900">JSON generado</h2>
            <div class="flex-1"></div>
            <button id="json-enviar" type="button"
                class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50">
                Enviar
            </button>
            <button type="button" class="rounded-md p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                data-close-json aria-label="Cerrar">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <p id="json-status" class="border-b border-gray-100 px-5 py-2 text-sm text-gray-600"></p>
        <pre id="json-content"
            class="flex-1 overflow-auto bg-gray-900 px-5 py-4 text-xs leading-relaxed text-emerald-300"></pre>
        <pre id="json-respuesta"
            class="hidden max-h-48 overflow-auto border-t border-gray-200 bg-gray-100 px-5 py-3 text-xs leading-relaxed text-gray-800"></pre>
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
    <div
        class="relative z-10 flex max-h-[85vh] w-full max-w-3xl flex-col overflow-hidden rounded-xl bg-white shadow-xl">
        <div class="flex items-center gap-3 border-b border-gray-200 px-5 py-3">
            <h2 class="text-base font-semibold text-gray-900">Respuesta del endpoint</h2>
            <span id="respuesta-nro" class="font-mono text-sm text-gray-500"></span>
            <div class="flex-1"></div>
            <button type="button" class="rounded-md p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                data-close-respuesta aria-label="Cerrar">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <p id="respuesta-estado" class="border-b border-gray-100 px-5 py-2 text-sm text-gray-600"></p>
        <pre id="respuesta-content"
            class="flex-1 overflow-auto bg-gray-900 px-5 py-4 text-xs leading-relaxed text-gray-100"></pre>
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

<script>
    (() => {
            const STORAGE_KEY = 'facturas-seleccionadas';
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
            const filas = Array.from(document.querySelectorAll('.js-fila'));
            const master = document.getElementById('check-todas');
            const contador = document.getElementById('grupo-contador');
            const boton = document.getElementById('btn-enviar-grupo');
            const limpiar = document.getElementById('limpiar-seleccion');
            const estado = document.getElementById('grupo-status');

            const cargar = () => {
                try {
                    const guardadas = JSON.parse(sessionStorage.getItem(STORAGE_KEY) || '[]');
                    return Array.isArray(guardadas) ? new Set(guardadas.filter((n) => typeof n === 'string')) : new Set();
                } catch (e) {
                    return new Set();
                }
            };
            const guardar = (set) => sessionStorage.setItem(STORAGE_KEY, JSON.stringify(Array.from(set)));

            let seleccion = cargar();

            const sincronizarFilas = () => {
                filas.forEach((c) => { c.checked = seleccion.has(c.dataset.nro); });
            };

            const actualizar = () => {
                sincronizarFilas();
                const n = seleccion.size;
                contador.textContent = String(n);
                boton.disabled = n === 0;
                if (master) {
                    master.checked = filas.length > 0 && filas.every((c) => seleccion.has(c.dataset.nro));
                }
            };

            filas.forEach((c) => {
                c.addEventListener('change', () => {
                    if (c.checked) {
                        seleccion.add(c.dataset.nro);
                    } else {
                        seleccion.delete(c.dataset.nro);
                    }
                    guardar(seleccion);
                    actualizar();
                });
            });

            master?.addEventListener('change', () => {
                filas.forEach((c) => {
                    if (master.checked) {
                        seleccion.add(c.dataset.nro);
                    } else {
                        seleccion.delete(c.dataset.nro);
                    }
                });
                guardar(seleccion);
                actualizar();
            });

            limpiar?.addEventListener('click', () => {
                seleccion = new Set();
                guardar(seleccion);
                estado.classList.add('hidden');
                actualizar();
            });

            const PROGRESO_KEY = 'facturas-envio-proceso';
            let enProceso = [];
            let intentos = 0;
            const MAX_INTENTOS = 45;

            const leerProgreso = () => {
                try {
                    const v = JSON.parse(sessionStorage.getItem(PROGRESO_KEY) || '[]');
                    enProceso = Array.isArray(v) ? v.filter((n) => typeof n === 'string') : [];
                } catch (e) {
                    enProceso = [];
                }
            };
            const guardarProgreso = (nros) => sessionStorage.setItem(PROGRESO_KEY, JSON.stringify(nros));

            const consultarEstado = async () => {
                if (enProceso.length === 0) return;

                try {
                    const res = await fetch('/pendientes/estado', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                        body: JSON.stringify({ nrofacturas: enProceso }),
                    });
                    let data = null;
                    try {
                        data = await res.json();
                    } catch (e) {
                        data = null;
                    }
                    if (!res.ok || !data) {
                        throw new Error((data && data.message) || 'Error al consultar estado (' + res.status + ')');
                    }

                    if (data.pendientes === 0) {
                        sessionStorage.removeItem(PROGRESO_KEY);
                        enProceso = [];
                        window.location.reload();
                        return;
                    }

                    estado.classList.remove('hidden');
                    estado.textContent = 'Procesando ' + data.pendientes + ' factura(s) pendiente(s)… se actualizará el listado al terminar.';
                    intentos += 1;
                    if (intentos >= MAX_INTENTOS) {
                        sessionStorage.removeItem(PROGRESO_KEY);
                        enProceso = [];
                        estado.textContent = 'El procesamiento sigue en curso. Recargá la página para ver las respuestas.';
                        return;
                    }
                } catch (err) {
                    estado.classList.remove('hidden');
                    estado.textContent = err.message || 'Error inesperado';
                    intentos += 1;
                }
                setTimeout(consultarEstado, 2500);
            };

            boton.addEventListener('click', async () => {
                const nros = Array.from(seleccion);
                if (nros.length === 0) return;

                boton.disabled = true;
                estado.classList.remove('hidden');
                estado.textContent = 'Encolando ' + nros.length + ' factura(s)…';

                const encolar = async (confirmarLocal) => {
                    const res = await fetch('/pendientes/enviar', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                        body: JSON.stringify({ nrofacturas: nros, confirmar_local: confirmarLocal }),
                    });
                    let data = null;
                    try {
                        data = await res.json();
                    } catch (e) {
                        data = null;
                    }

                    return { res, data };
                };

                try {
                    let { res, data } = await encolar(false);

                    if (res.status === 409 && data && data.requiere_confirmacion_local) {
                        const varios = nros.length > 1;
                        const aviso = 'Estás trabajando con la copia local de puntopan'
                            + (data.aprobado_hasta ? ' (aprobada hasta las ' + data.aprobado_hasta + ')' : '')
                            + '.\n\n'
                            + (varios
                                ? 'Los ' + nros.length + ' comprobantes se van a preparar leyendo esa copia,'
                                    + ' que puede estar desactualizada, y se van a enviar a la SET.'
                                : 'El comprobante se va a preparar leyendo esa copia,'
                                    + ' que puede estar desactualizada, y se va a enviar a la SET.')
                            + '\n\n¿Confirmás el envío contra la copia local?';

                        if (!window.confirm(aviso)) {
                            estado.textContent = 'Envío cancelado: no se encoló nada.';
                            return;
                        }

                        ({ res, data } = await encolar(true));
                    }

                    if (!res.ok || !data) {
                        throw new Error((data && data.message) || 'Error al encolar (' + res.status + ')');
                    }

                    seleccion = new Set();
                    guardar(seleccion);
                    actualizar();

                    if (data.encoladas > 0) {
                        intentos = 0;
                        enProceso = data.nros || [];
                        guardarProgreso(enProceso);
                        estado.textContent = (data.copia_local ? 'Encoladas contra la copia local. ' : '')
                            + 'Se encolaron ' + data.encoladas
                            + ' factura(s) · ' + data.omitidas + ' omitida(s). Ejecutá: php artisan queue:work --stop-when-empty';
                        setTimeout(consultarEstado, 2500);
                    } else {
                        estado.textContent = 'No se encolaron facturas (' + data.omitidas + ' omitida(s): ya enviadas o inexistentes).';
                    }
                } catch (err) {
                    estado.textContent = err.message || 'Error inesperado';
                } finally {
                    boton.disabled = seleccion.size === 0;
                }
            });

            actualizar();
            leerProgreso();
            if (enProceso.length > 0) {
                estado.classList.remove('hidden');
                estado.textContent = 'Revisando facturas en proceso…';
                consultarEstado();
            }
        })();
</script>
@endsection