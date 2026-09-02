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
    <h1 class="mb-4 text-2xl font-semibold">Listado de facturas</h1>

    <form method="GET" action="{{ route('facturas.index') }}" class="mb-4 flex flex-wrap items-end gap-3 rounded-lg bg-white p-4 shadow">
        <div>
            <label for="q" class="mb-1 block text-sm font-medium text-gray-700">N° factura</label>
            <input type="text" id="q" name="q" value="{{ $q }}"
                   class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div>
            <label for="desde" class="mb-1 block text-sm font-medium text-gray-700">Desde</label>
            <input type="date" id="desde" name="desde" value="{{ $desde }}"
                   class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div>
            <label for="hasta" class="mb-1 block text-sm font-medium text-gray-700">Hasta</label>
            <input type="date" id="hasta" name="hasta" value="{{ $hasta }}"
                   class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div>
            <label for="tipo" class="mb-1 block text-sm font-medium text-gray-700">Tipo</label>
            <select id="tipo" name="tipo"
                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Todos</option>
                @foreach ($tipos as $opcion)
                    <option value="{{ $opcion }}" @selected($tipo === $opcion)>{{ $opcion }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-center gap-2">
            <button type="submit"
                    class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                Filtrar
            </button>
            <a href="{{ route('facturas.index') }}"
               class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                Limpiar
            </a>
        </div>
    </form>

    <div class="overflow-x-auto rounded-lg bg-white shadow">
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
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($facturas as $factura)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2">
                            <a href="{{ route('facturas.show', $factura->NroFactura) }}"
                               class="font-medium text-indigo-600 hover:underline">
                                {{ $factura->NroFactura }}
                            </a>
                        </td>
                        <td class="px-4 py-2 whitespace-nowrap">{{ $factura->FechaFactura }}</td>
                        <td class="px-4 py-2">{{ $factura->nombre_cliente }}</td>
                        <td class="px-4 py-2">{{ $factura->TipoFactura }}</td>
                        <td class="px-4 py-2">{{ $factura->SituFactura }}</td>
                        <td class="px-4 py-2 text-right">{{ number_format((float) $factura->items) }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ $money($factura->total) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                            No hay facturas que coincidan con los filtros.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $facturas->links() }}
    </div>
@endsection
