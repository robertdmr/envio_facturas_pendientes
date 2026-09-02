<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Facturas Puntopan')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 text-gray-900 antialiased">
    <header class="bg-white shadow">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4">
            <a href="{{ route('facturas.index') }}" class="text-lg font-semibold text-gray-800">
                Facturas Puntopan
            </a>
        </div>
    </header>
    <main class="mx-auto max-w-7xl px-4 py-6">
        @yield('content')
    </main>
</body>
</html>
