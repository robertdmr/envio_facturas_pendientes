<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Facturas Puntopan')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 text-gray-900 antialiased">
    @php
        $enFacturas = request()->routeIs('facturas.*');
        $enConfiguracion = request()->routeIs('configuracion*');
    @endphp

    <div id="sidebar-overlay" class="fixed inset-0 z-30 hidden bg-gray-900/60 lg:hidden"></div>

    <aside id="sidebar" class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col bg-slate-900 text-slate-100 shadow-xl transition-transform duration-200 lg:translate-x-0">
        <div class="flex h-16 items-center justify-between border-b border-slate-700/60 px-5">
            <a href="{{ route('facturas.index') }}" class="text-base font-semibold tracking-tight text-white">
                Facturas Puntopan
            </a>
            <button id="sidebar-close" type="button" class="rounded-md p-1 text-slate-400 hover:text-white lg:hidden" aria-label="Cerrar menú">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>

        <p class="px-5 pt-5 pb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">Menú</p>
        <nav class="flex-1 space-y-1 px-3">
            <a href="{{ route('facturas.index') }}"
               class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium {{ $enFacturas ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' }}">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17h6m-6-4h6m-6-4h6M5 4h14a1 1 0 011 1v14a1 1 0 01-1 1H5a1 1 0 01-1-1V5a1 1 0 011-1z" /></svg>
                Listado de facturas
            </a>
            <a href="{{ route('configuracion.edit') }}"
               class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium {{ $enConfiguracion ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white' }}">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                Configuración
            </a>
        </nav>
        <div class="border-t border-slate-700/60 px-5 py-4 text-xs text-slate-500">
            Conexión de solo lectura · puntopan
        </div>
    </aside>

    <div class="flex min-h-screen flex-col lg:pl-64">
        <header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-gray-200 bg-white px-4 lg:hidden">
            <button id="sidebar-open" type="button" class="rounded-md p-2 text-gray-600 hover:bg-gray-100" aria-label="Abrir menú">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" /></svg>
            </button>
            <a href="{{ route('facturas.index') }}" class="text-base font-semibold text-gray-800">Facturas Puntopan</a>
        </header>

        <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
            @yield('content')
        </main>
    </div>

    <script>
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        const openSidebar = () => {
            sidebar.classList.remove('-translate-x-full');
            overlay.classList.remove('hidden');
        };
        const closeSidebar = () => {
            sidebar.classList.add('-translate-x-full');
            overlay.classList.add('hidden');
        };
        document.getElementById('sidebar-open')?.addEventListener('click', openSidebar);
        document.getElementById('sidebar-close')?.addEventListener('click', closeSidebar);
        overlay?.addEventListener('click', closeSidebar);
    </script>
</body>
</html>
