@extends('layouts.app')

@section('title', 'Conexión a puntopan')

@section('content')
<div class="mx-auto max-w-2xl">
    <div class="mb-6">
        <h1 class="text-2xl font-semibold tracking-tight text-gray-900">El servidor remoto de puntopan no responde</h1>
        <p class="mt-1 text-sm text-gray-500">
            {{ $resumen['forzado'] ? 'La copia local está forzada por configuración.' : 'Antes de continuar necesitamos
            tu confirmación.' }}
        </p>
    </div>

    <div class="rounded-xl border border-amber-200 bg-amber-50 p-6 shadow-sm">
        <div class="flex gap-3">
            <svg class="mt-0.5 h-6 w-6 flex-none text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
            </svg>
            <div class="text-sm text-amber-900">
                <p class="font-semibold">No se pudo conectar a {{ $resumen['host_remoto'] }}:{{
                    $resumen['puerto_remoto'] }}.</p>
                <p class="mt-2">
                    Existe una copia local de <span class="font-medium">puntopan</span> en
                    {{ $resumen['host_local'] }}:{{ $resumen['puerto_local'] }} (base
                    <span class="font-medium">{{ $resumen['base_local'] }}</span>).
                    Podés trabajar contra ella, pero <span class="font-semibold">puede estar desactualizada</span>.
                </p>
                <p class="mt-2">
                    La aprobación dura {{ intdiv($resumen['ttl_aprobacion'], 60) }} minutos; después se vuelve a
                    intentar el
                    servidor remoto. Mientras esté activa vas a ver un aviso en la parte superior de la aplicación.
                </p>
            </div>
        </div>

        <dl class="mt-5 grid grid-cols-1 gap-3 border-t border-amber-200 pt-4 text-sm sm:grid-cols-2">
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-amber-700">Servidor remoto</dt>
                <dd class="mt-0.5 font-mono text-amber-900">{{ $resumen['host_remoto'] }}:{{ $resumen['puerto_remoto']
                    }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-amber-700">Copia local</dt>
                <dd class="mt-0.5 font-mono text-amber-900">{{ $resumen['host_local'] }}:{{ $resumen['puerto_local'] }}
                    / {{ $resumen['base_local'] }}</dd>
            </div>
        </dl>

        <div class="mt-6 flex flex-wrap items-center gap-3">
            <form method="POST" action="{{ route('puntopan.conexion-local.aprobar') }}">
                @csrf
                <button type="submit"
                    class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2">
                    Usar la copia local
                </button>
            </form>

            <form method="POST" action="{{ route('puntopan.conexion-local.reintentar') }}">
                @csrf
                <button type="submit"
                    class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    Reintentar el servidor remoto
                </button>
            </form>
        </div>
    </div>

    <p class="mt-4 text-xs text-gray-500">
        El respaldo se guarda solo en tu sesión: no cambia la configuración de la aplicación ni afecta a otros usuarios.
    </p>
</div>
@endsection