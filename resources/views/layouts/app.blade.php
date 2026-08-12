<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titulo', config('app.name'))</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-5xl items-center justify-between px-6 py-4">
            {{-- El logo vive en el propio proyecto, no en un CDN: el servidor donde esto
                 va a correr no tiene salida a Internet. --}}
            <a href="{{ route('inicio') }}" class="flex items-center gap-3">
                <img
                    src="{{ asset('img/logo-ciip.jpg') }}"
                    alt="Centro Internacional de Inversión Productiva"
                    width="322"
                    height="65"
                    class="h-8 w-auto"
                >
                <span class="border-l border-slate-200 pl-3 text-sm font-semibold tracking-tight">
                    {{ config('app.name') }}
                </span>
            </a>
            <span class="font-mono text-xs uppercase tracking-widest text-slate-400">
                @yield('seccion', 'Inicio')
            </span>
        </div>
    </header>

    <main class="mx-auto max-w-5xl px-6 py-10">
        @yield('contenido')
    </main>
</body>
</html>
