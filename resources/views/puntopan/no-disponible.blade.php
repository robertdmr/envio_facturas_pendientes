@extends('layouts.app')

@section('title', 'puntopan no disponible')

@section('content')
<div class="mx-auto max-w-2xl">
    <div class="rounded-xl border border-red-200 bg-red-50 p-6 shadow-sm">
        <div class="flex gap-3">
            <svg class="mt-0.5 h-6 w-6 flex-none text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
            </svg>
            <div class="text-sm text-red-900">
                <h1 class="text-lg font-semibold">puntopan no está disponible</h1>
                <p class="mt-2">{{ $mensaje }}</p>

                <dl class="mt-4 grid grid-cols-1 gap-3 border-t border-red-200 pt-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-red-700">Servidor remoto</dt>
                        <dd class="mt-0.5 font-mono">{{ $resumen['host_remoto'] }}:{{ $resumen['puerto_remoto'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-red-700">Copia local</dt>
                        <dd class="mt-0.5 font-mono">
                            {{ $resumen['host_local'] }}:{{ $resumen['puerto_local'] }} / {{ $resumen['base_local'] }}
                            @unless ($resumen['permitido'])
                            <span class="ml-1 font-sans text-xs">(deshabilitada por configuración)</span>
                            @endunless
                        </dd>
                    </div>
                </dl>

                <div class="mt-5 flex flex-wrap items-center gap-3">
                    <form method="POST" action="{{ route('puntopan.conexion-local.reintentar') }}">
                        @csrf
                        <button type="submit"
                            class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                            Reintentar
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection