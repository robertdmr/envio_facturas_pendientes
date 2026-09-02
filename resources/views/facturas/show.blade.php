@extends('layouts.app')

@section('title', 'Factura '.$factura->NroFactura)

@php
    $money = fn ($value) => number_format((float) $value, 0, ',', '.');
    $qty = fn ($value) => rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.');
    $fecha = fn ($value) => ($value && $value !== '0000-00-00 00:00:00')
        ? \Carbon\Carbon::parse($value)->format('d/m/Y H:i')
        : '—';
@endphp

@section('content')
    <a href="{{ route('facturas.index') }}" class="mb-4 inline-block text-sm font-medium text-indigo-600 hover:underline">
        ← Volver al listado
    </a>

    <div class="mb-6 rounded-lg bg-white p-5 shadow">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold">Factura {{ $factura->NroFactura }}</h1>
                <p class="mt-1 text-sm text-gray-600">
                    Fecha: {{ $fecha($factura->FechaFactura) }} ·
                    Tipo: {{ $factura->TipoFactura }} ·
                    Situación: {{ $factura->SituFactura }}
                </p>
            </div>
            <div class="text-right text-sm">
                <p class="font-semibold">{{ $factura->nombre_cliente }}</p>
                @if ($factura->ruc_cliente)
                    <p class="text-gray-600">RUC: {{ $factura->ruc_cliente }}</p>
                @endif
            </div>
        </div>
    </div>

    <div class="overflow-x-auto rounded-lg bg-white shadow">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Código</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Descripción</th>
                    <th class="px-4 py-3 text-right font-semibold text-gray-600">Cantidad</th>
                    <th class="px-4 py-3 text-right font-semibold text-gray-600">Precio</th>
                    <th class="px-4 py-3 text-right font-semibold text-gray-600">Descuento</th>
                    <th class="px-4 py-3 text-right font-semibold text-gray-600">IVA incl.</th>
                    <th class="px-4 py-3 text-right font-semibold text-gray-600">Subtotal</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($detalles as $linea)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2 font-mono text-xs">{{ $linea->IdMercaderia }}</td>
                        <td class="px-4 py-2">{{ $linea->Descripcion }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ $qty($linea->Cantidad) }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ $money($linea->PrecioVenta) }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ $money($linea->descuento) }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ $money($linea->Impuesto) }}</td>
                        <td class="px-4 py-2 text-right font-medium tabular-nums">
                            {{ $money($linea->Cantidad * $linea->PrecioVenta - $linea->descuento) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                            Esta factura no tiene líneas registradas en comandadet.
                        </td>
                    </tr>
                @endforelse
            </tbody>
            @if ($detalles->isNotEmpty())
                <tfoot class="bg-gray-50">
                    <tr>
                        <td colspan="6" class="px-4 py-3 text-right font-semibold">Total factura</td>
                        <td class="px-4 py-3 text-right font-semibold tabular-nums">
                            {{ $money($detalles->sum(fn ($l) => $l->Cantidad * $l->PrecioVenta - $l->descuento)) }}
                        </td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
@endsection
