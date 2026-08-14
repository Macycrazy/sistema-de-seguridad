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

    {{-- Va antes del encabezado: si hay cambios de otra parte sin aplicar, es lo primero que hay
         que ver. Solo sale en desarrollo, y solo si de verdad falta correr algo. --}}
    <x-aviso-actualizar />

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

                {{-- El nombre solo cabe en pantalla ancha; en tableta cede el sitio al menú. --}}
                <span class="hidden h-9 w-px shrink-0 bg-white/25 lg:block"></span>
                <span class="hidden min-w-0 truncate text-lg font-semibold tracking-tight text-white lg:block">
                    Registro de Entradas y Salidas
                </span>
            </a>

            {{--
                Menú de módulos. El módulo abierto se marca con una pastilla clara.
                «Usuarios» todavía no existe (es la parte 3): se muestra apagado, para que se
                vea que viene, sin llevar a ningún lado.

                Cada enlace se declara una vez en $modulos. Para sumar el de usuarios cuando
                la parte 3 tenga pantalla, se le quita 'pronto' => true y se le pone su ruta.
            --}}
            @php
                $modulos = [
                    ['ruta' => 'marcar', 'texto' => 'Marcar'],
                    ['ruta' => 'registro', 'texto' => 'Registro'],
                    ['ruta' => null, 'texto' => 'Usuarios', 'pronto' => true],
                ];
            @endphp
            <nav class="flex shrink-0 items-center gap-1 font-mono text-xs font-semibold uppercase tracking-widest sm:text-sm sm:tracking-wide"
                 aria-label="Módulos">
                @foreach ($modulos as $m)
                    @if ($m['pronto'] ?? false)
                        <span class="cursor-default rounded px-3 py-2 text-white/40"
                              title="Aún no disponible">{{ $m['texto'] }}</span>
                    @else
                        @php $activo = request()->routeIs($m['ruta']); @endphp
                        <a href="{{ route($m['ruta']) }}"
                           @if ($activo) aria-current="page" @endif
                           class="rounded px-3 py-2 transition
                                  {{ $activo
                                       ? 'bg-white text-marca'
                                       : 'text-white/80 hover:bg-white/10 hover:text-white' }}">
                            {{ $m['texto'] }}
                        </a>
                    @endif
                @endforeach
            </nav>
        </div>
    </header>

    <main class="mx-auto max-w-5xl px-6 py-10">
        @yield('contenido')
    </main>
</body>
</html>
