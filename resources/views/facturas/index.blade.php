@extends('layouts.app')

@section('title', 'Listado de facturas')

@php
    $money = fn ($value) => number_format((float) $value, 2, ',', '.');
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
                       class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <label for="desde" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">Desde</label>
                <input type="date" id="desde" name="desde" value="{{ $desde }}"
                       class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <label for="hasta" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">Hasta</label>
                <input type="date" id="hasta" name="hasta" value="{{ $hasta }}"
                       class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <label for="tipo" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">Tipo</label>
                <select id="tipo" name="tipo"
                        class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
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
                                <div class="flex items-center justify-end gap-2">
                                    <button type="button" title="Reenviar factura (próximamente)" data-factura="{{ $factura->NroFactura }}"
                                            class="inline-flex items-center gap-1.5 rounded-lg border border-indigo-200 bg-indigo-50 px-2.5 py-1.5 text-xs font-medium text-indigo-700 transition hover:border-indigo-300 hover:bg-indigo-100">
                                        Reenviar
                                    </button>
                                    <button type="button" title="Generar JSON (próximamente)" data-factura="{{ $factura->NroFactura }}"
                                            class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-700 transition hover:border-gray-300 hover:bg-gray-50">
                                        Generar JSON
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-10 text-center text-gray-500">
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
@endsection
