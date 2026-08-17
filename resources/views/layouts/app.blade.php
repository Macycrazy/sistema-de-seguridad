<!DOCTYPE html>
{{--
    «translate="no"» no es un capricho: el traductor del navegador reescribe lo que ve, y aquí lo
    que se ve son datos. Pasó de verdad en un teléfono — el rótulo «A pie» se detectó como inglés
    y apareció «Un pastel» en la pantalla de la puerta. Con un nombre, una placa o un piso, el
    vigilante estaría leyendo algo que no es lo que hay guardado.
--}}
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" translate="no">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Lo mismo para Google Traductor, que no siempre respeta el atributo de arriba. --}}
    <meta name="google" content="notranslate">
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

            <div class="flex shrink-0 items-center gap-2 sm:gap-4">
                {{--
                    Menú de módulos. El módulo abierto se marca con una pastilla clara.

                    «Usuarios» y «Roles» ya existen, así que dejaron de estar apagados. En su
                    lugar, cada entrada declara el permiso que hace falta para abrirla y solo se
                    dibuja a quien lo tiene: un vigilante no ve «Registro», porque tocarlo le
                    daría un 403. Es cortesía, no seguridad — quien teclee la dirección a mano se
                    topa con el permiso igual.
                --}}
                @auth
                    @php
                        $modulos = collect([
                            ['ruta' => 'marcar', 'texto' => 'Marcar'],
                            ['ruta' => 'registro', 'texto' => 'Registro', 'permiso' => 'ver-registro'],
                            ['ruta' => 'usuarios', 'texto' => 'Usuarios', 'permiso' => 'gestionar-usuarios'],
                            ['ruta' => 'roles', 'texto' => 'Roles', 'permiso' => 'gestionar-permisos'],
                        ])->filter(fn ($m) => ! isset($m['permiso']) || auth()->user()->can($m['permiso']));
                    @endphp

                    <nav class="flex shrink-0 items-center gap-1 font-mono text-xs font-semibold uppercase tracking-widest sm:text-sm sm:tracking-wide"
                         aria-label="Módulos">
                        @foreach ($modulos as $m)
                            @php $activo = request()->routeIs($m['ruta']); @endphp
                            <a href="{{ route($m['ruta']) }}"
                               @if ($activo) aria-current="page" @endif
                               class="rounded px-3 py-2 transition
                                      {{ $activo
                                           ? 'bg-white text-marca'
                                           : 'text-white/80 hover:bg-white/10 hover:text-white' }}">
                                {{ $m['texto'] }}
                            </a>
                        @endforeach
                    </nav>

                    <span class="hidden h-9 w-px bg-white/25 sm:block"></span>

                    {{--
                        Quién tiene la sesión abierta. En el puesto se turnan varias personas en
                        la misma máquina, así que tiene que verse de un vistazo con qué usuario se
                        está marcando: media parte 3 se cae si alguien marca todo un turno con el
                        usuario del turno anterior.

                        Y por aquí se entra a cambiar la propia clave, que es lo único «mío».
                    --}}
                    <a href="{{ route('clave') }}"
                       title="Cambiar mi clave"
                       class="hidden text-right transition hover:opacity-80 sm:block">
                        <span class="block text-sm font-semibold leading-tight text-white">
                            {{ auth()->user()->nombreCorto() }}
                        </span>
                        <span class="block font-mono text-[10px] uppercase tracking-widest text-white/70">
                            {{ auth()->user()->rol->etiqueta() }}
                        </span>
                    </a>

                    {{-- Por POST: un GET lo dispara cualquier cosa que cargue una URL. --}}
                    <form method="POST" action="{{ route('salir') }}">
                        @csrf
                        <x-boton type="submit" variante="secundario" tamano="chico">Salir</x-boton>
                    </form>
                @else
                    {{-- Sin sesión solo se ve la puerta, y ahí no hay módulos que ofrecer. --}}
                    <span class="font-mono text-xs uppercase tracking-widest text-white/70">
                        @yield('seccion', 'Entrar')
                    </span>
                @endauth
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-5xl px-6 py-10">
        @yield('contenido')
    </main>
</body>
</html>
