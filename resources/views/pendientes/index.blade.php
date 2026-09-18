@extends('layouts.app')

@section('title', 'Facturas pendientes')

@php
    $q = request()->query('q', '');
    $truncar = fn ($value, $len = 24) => \Illuminate\Support\Str::limit((string) $value, $len, '…');
@endphp

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold tracking-tight text-gray-900">Facturas pendientes</h1>
        <p class="mt-1 text-sm text-gray-500">CDC de la respuesta del endpoint y su estado en <span class="font-medium text-gray-700">fecdc</span> (puntopan).</p>
    </div>

    <form method="GET" action="{{ route('pendientes.index') }}"
          class="mb-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-6">
            <div class="xl:col-span-2">
                <label for="q" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">N° factura</label>
                <input type="text" id="q" name="q" value="{{ $q }}" placeholder="001-001-0000000"
                       class="block w-full rounded-lg border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
        </div>

        <div class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 pt-4">
            <div class="flex items-center gap-2">
                <a href="{{ route('pendientes.index') }}"
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

    <div class="mb-3 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-2.5">
        <p class="text-sm text-indigo-900">
            <span id="grupo-contador">0</span> factura(s) seleccionada(s) para actualizar
        </p>
        <div class="flex items-center gap-2">
            <button id="limpiar-seleccion" type="button"
                    class="rounded-lg border border-indigo-300 bg-white px-3 py-2 text-sm font-medium text-indigo-700 shadow-sm hover:bg-indigo-100">
                Limpiar selección
            </button>
            <button id="btn-actualizar-grupo" type="button" disabled
                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50">
                Actualizar seleccionadas
            </button>
        </div>
    </div>

    <p id="cdc-status" class="mb-3 hidden rounded-xl border px-4 py-2.5 text-sm"></p>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="w-10 px-4 py-3">
                            <input type="checkbox" id="check-todas" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" aria-label="Seleccionar todas las de la página">
                        </th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-600">N° Factura</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-600">Estado envío</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-600">CDC (respuesta)</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-600">CDC en fecdc</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-600">Estado fecdc</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-600">Fecha envío fecdc</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($pendientes as $pendiente)
                        @php
                            $cdc = $pendiente->cdc();
                            $filasFecdc = $fecdcs->get($pendiente->nrofactura);
                            $fecdc = $filasFecdc?->first();
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <input type="checkbox" class="js-fila rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                       data-nro="{{ $pendiente->nrofactura }}" aria-label="Seleccionar {{ $pendiente->nrofactura }}">
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap font-medium text-gray-800">{{ $pendiente->nrofactura }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if ($pendiente->enviado)
                                    <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-800">Enviado</span>
                                @else
                                    <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800">Pendiente</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if ($cdc)
                                    <span class="font-mono text-xs text-gray-700" title="{{ $cdc }}">{{ $truncar($cdc, 28) }}</span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if ($fecdc)
                                    <span class="font-mono text-xs text-gray-700" title="{{ $fecdc->cdc }}">{{ $truncar($fecdc->cdc, 28) ?: '—' }}</span>
                                    @if (($filasFecdc?->count() ?? 0) > 1)
                                        <span class="ml-1 inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-medium text-slate-600">{{ $filasFecdc->count() }} filas</span>
                                    @endif
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-gray-600">{{ $fecdc->estado ?? '—' }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-gray-600">{{ $fecdc->fecha_envio ?? '—' }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="flex items-center justify-end">
                                    <button type="button" data-nro="{{ $pendiente->nrofactura }}"
                                            @disabled(! $cdc)
                                            title="{{ $cdc ? 'Actualizar cdc en fecdc' : 'La respuesta no contiene cdc' }}"
                                            class="js-actualizar-cdc inline-flex items-center gap-1.5 rounded-lg border border-indigo-200 bg-indigo-50 px-2.5 py-1.5 text-xs font-medium text-indigo-700 transition hover:border-indigo-300 hover:bg-indigo-100 disabled:cursor-not-allowed disabled:opacity-40">
                                        Actualizar CDC
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-10 text-center text-gray-500">
                                No hay facturas pendientes que coincidan con los filtros.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $pendientes->links() }}
    </div>

    <script>
        (() => {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
            const status = document.getElementById('cdc-status');

            const mostrar = (mensaje, ok) => {
                status.textContent = mensaje;
                status.className = 'mb-3 rounded-xl border px-4 py-2.5 text-sm ' + (ok
                    ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                    : 'border-red-200 bg-red-50 text-red-800');
                status.classList.remove('hidden');
            };

            document.querySelectorAll('.js-actualizar-cdc').forEach((btn) => {
                btn.addEventListener('click', async () => {
                    const nro = btn.dataset.nro;
                    btn.disabled = true;

                    try {
                        const res = await fetch('/pendientes/' + encodeURIComponent(nro) + '/cdc', {
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
                            throw new Error((data && data.message) || 'Error al actualizar el CDC (' + res.status + ')');
                        }
                        mostrar('CDC actualizado en ' + data.filas_actualizadas + ' fila(s) de fecdc · ' + data.nrofactura, true);
                        setTimeout(() => window.location.reload(), 900);
                    } catch (err) {
                        mostrar(err.message || 'Error inesperado', false);
                        btn.disabled = false;
                    }
                });
            });
        })();
    </script>

    <script>
        (() => {
            const STORAGE_KEY = 'pendientes-seleccionadas';
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
            const filas = Array.from(document.querySelectorAll('.js-fila'));
            const master = document.getElementById('check-todas');
            const contador = document.getElementById('grupo-contador');
            const boton = document.getElementById('btn-actualizar-grupo');
            const limpiar = document.getElementById('limpiar-seleccion');
            const status = document.getElementById('cdc-status');

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

            const mostrar = (mensaje, ok) => {
                status.textContent = mensaje;
                status.className = 'mb-3 rounded-xl border px-4 py-2.5 text-sm ' + (ok
                    ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                    : 'border-red-200 bg-red-50 text-red-800');
                status.classList.remove('hidden');
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
                status.classList.add('hidden');
                actualizar();
            });

            boton.addEventListener('click', async () => {
                const nros = Array.from(seleccion);
                if (nros.length === 0) return;

                boton.disabled = true;
                mostrar('Actualizando ' + nros.length + ' factura(s)…', true);

                try {
                    const res = await fetch('/pendientes/actualizar-cdc', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                        body: JSON.stringify({ nrofacturas: nros }),
                    });
                    let data = null;
                    try {
                        data = await res.json();
                    } catch (e) {
                        data = null;
                    }
                    if (!res.ok || !data) {
                        throw new Error((data && data.message) || 'Error al actualizar (' + res.status + ')');
                    }

                    seleccion = new Set();
                    guardar(seleccion);
                    actualizar();

                    mostrar('Actualizadas ' + data.actualizadas + ' factura(s) en ' + data.filas
                        + ' fila(s) de fecdc · ' + data.omitidas + ' omitida(s).', true);
                    setTimeout(() => window.location.reload(), 1200);
                } catch (err) {
                    mostrar(err.message || 'Error inesperado', false);
                    boton.disabled = false;
                }
            });

            actualizar();
        })();
    </script>
@endsection
