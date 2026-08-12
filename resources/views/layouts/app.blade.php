<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titulo', config('app.name')) · CIIP</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    {{--
        Encabezado del sistema. El logo es un archivo del propio proyecto
        (public/imagenes/logo-ciip.png): el servidor donde esto va a correr no tiene salida a
        Internet, así que nada de imágenes ni tipografías traídas de fuera.
    --}}
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-5xl items-center justify-between gap-4 px-6 py-3">
            <a href="{{ route('inicio') }}" class="flex min-w-0 items-center gap-3 sm:gap-4">
                <img src="{{ asset('imagenes/logo-ciip.png') }}"
                     alt="CIIP · Centro Internacional de Inversión Productiva"
                     class="h-14 w-auto shrink-0">

                {{-- En pantallas estrechas se queda solo el logo: el nombre ocuparía el ancho
                     que necesita la sección. --}}
                <span class="hidden h-9 w-px shrink-0 bg-slate-200 sm:block"></span>
                <span class="hidden min-w-0 truncate text-sm font-semibold tracking-tight text-slate-900 sm:block">
                    Registro de Entradas y Salidas
                </span>
            </a>

            <span class="shrink-0 font-mono text-xs uppercase tracking-widest text-slate-400">
                @yield('seccion', 'Inicio')
            </span>
        </div>
    </header>

    <main class="mx-auto max-w-5xl px-6 py-10">
        @yield('contenido')
    </main>
</body>
</html>
