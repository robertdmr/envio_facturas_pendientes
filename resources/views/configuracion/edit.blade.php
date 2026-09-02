@extends('layouts.app')

@section('title', 'Configuración')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold tracking-tight text-gray-900">Configuración</h1>
        <p class="mt-1 text-sm text-gray-500">Parámetros de la factura electrónica (SET/KUATIA).</p>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <ul class="list-inside list-disc">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('configuracion.update') }}"
          class="max-w-2xl rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        @csrf

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label for="contribuyente_id" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">Contribuyente ID</label>
                <input type="number" id="contribuyente_id" name="contribuyente_id" value="{{ old('contribuyente_id', $parametros->contribuyente_id) }}"
                       class="block w-full rounded-lg border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <label for="timbrado" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">Timbrado</label>
                <input type="text" id="timbrado" name="timbrado" value="{{ old('timbrado', $parametros->timbrado) }}"
                       class="block w-full rounded-lg border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div class="sm:col-span-2">
                <label for="pass" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">Pass</label>
                <input type="text" id="pass" name="pass" value="{{ old('pass', $parametros->pass) }}"
                       class="block w-full rounded-lg border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <label for="fec_inicio" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">Fecha inicio timbrado</label>
                <input type="datetime-local" id="fec_inicio" name="fec_inicio"
                       value="{{ old('fec_inicio', $parametros->fec_inicio?->format('Y-m-d\TH:i')) }}"
                       class="block w-full rounded-lg border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <label for="sucursal" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">Sucursal</label>
                <input type="text" id="sucursal" name="sucursal" value="{{ old('sucursal', $parametros->sucursal) }}"
                       class="block w-full rounded-lg border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div class="sm:col-span-2">
                <label for="api_url" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">API URL (envío de aceptación)</label>
                <input type="url" id="api_url" name="api_url" value="{{ old('api_url', $parametros->api_url) }}" placeholder="https://..."
                       class="block w-full rounded-lg border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
        </div>

        <div class="mt-6 flex items-center justify-end gap-2 border-t border-gray-100 pt-4">
            <button type="submit"
                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                Guardar configuración
            </button>
        </div>
    </form>
@endsection
