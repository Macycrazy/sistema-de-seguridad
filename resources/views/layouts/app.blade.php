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
        Encabezado del sistema, en el azul del CIIP (--color-marca en app.css).

        Sobre el azul va el logo BLANCO: el azul no se leería sobre su propio color. Los dos
        archivos viven en el proyecto porque el servidor donde esto va a correr no tiene salida
        a Internet — nada de imágenes ni tipografías traídas de fuera.

        OJO al tocar este archivo: al integrar las partes 1 y 2, git fusionó sin dar conflicto
        dos encabezados distintos y dejó uno dentro del otro, con etiquetas sin cerrar. Aquí va
        una sola etiqueta de encabezado. Si aparecen dos, se repitió aquel merge mal resuelto.
    --}}
    <header class="bg-marca">
        <div class="mx-auto flex max-w-5xl items-center justify-between gap-4 px-6 py-3">
            <a href="{{ route('inicio') }}" class="flex min-w-0 items-center gap-3 sm:gap-4">
                <img src="{{ asset('imagenes/logo-ciip-blanco.png') }}"
                     alt="CIIP · Centro Internacional de Inversión Productiva"
                     class="h-14 w-auto shrink-0">

                {{-- En pantallas estrechas se queda solo el logo: el nombre ocuparía el ancho
                     que necesita la sección. --}}
                <span class="hidden h-9 w-px shrink-0 bg-white/25 sm:block"></span>
                {{-- En tableta va algo menor que en computadora: a 640 px, el nombre a tamaño
                     completo se quedaría sin sitio y saldría recortado. --}}
                <span class="hidden min-w-0 truncate text-lg font-semibold tracking-tight text-white sm:block lg:text-xl">
                    Registro de Entradas y Salidas
                </span>
            </a>

            <span class="shrink-0 font-mono text-xs uppercase tracking-widest text-white/70">
                @yield('seccion', 'Inicio')
            </span>
        </div>
    </header>

    <main class="mx-auto max-w-5xl px-6 py-10">
        @yield('contenido')
    </main>
</body>
</html>
