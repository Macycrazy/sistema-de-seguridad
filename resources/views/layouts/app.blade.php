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
    {{-- viewport-fit=cover: que el color llegue hasta el borde en teléfonos con notch. --}}
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Lo mismo para Google Traductor, que no siempre respeta el atributo de arriba. --}}
    <meta name="google" content="notranslate">
    <title>@yield('titulo', config('app.name')) · CIIP</title>

    {{-- Se instala en la pantalla de inicio y abre a pantalla completa, como una app. El azul del
         CIIP tiñe la barra de estado del teléfono para que no se note el navegador. --}}
    <meta name="theme-color" content="#004090">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Registro CIIP">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-[100dvh] flex-col bg-slate-50 text-slate-900 antialiased">

    {{-- Va antes del encabezado: si hay cambios de otra parte sin aplicar, es lo primero que hay
         que ver. Solo sale en desarrollo, y solo si de verdad falta correr algo. --}}
    <x-aviso-actualizar />

    @php
        $puede = fn ($permiso) => auth()->check() && (! $permiso || auth()->user()->can($permiso));
    @endphp

    {{--
        Encabezado del sistema, en el azul del CIIP (--color-marca en app.css). Fijo arriba y con
        espacio para el notch, para que se sienta una app y no una página.

        Sobre el azul va el logo BLANCO: el azul no se leería sobre su propio color. Los archivos
        viven en el proyecto porque el servidor no tiene salida a Internet.

        OJO: al integrar las partes 1 y 2, git fusionó sin conflicto dos encabezados y dejó uno
        dentro del otro. Aquí va una sola etiqueta de encabezado; si aparecen dos, se repitió aquello.
    --}}
    <header class="sticky top-0 z-40 bg-marca" style="padding-top: env(safe-area-inset-top)">
        <div class="mx-auto flex max-w-5xl items-center justify-between gap-4 px-4 py-2 sm:px-6 sm:py-3">
            <a href="{{ route('inicio') }}" class="flex min-w-0 items-center gap-3 sm:gap-4">
                <img src="{{ asset('imagenes/logo-ciip-blanco.png') }}"
                     alt="CIIP · Centro Internacional de Inversión Productiva"
                     class="h-10 w-auto shrink-0 sm:h-14">

                {{-- El nombre solo cabe en pantalla ancha; en el resto cede el sitio. --}}
                <span class="hidden h-9 w-px shrink-0 bg-white/25 lg:block"></span>
                <span class="hidden min-w-0 truncate text-lg font-semibold tracking-tight text-white lg:block">
                    Registro de Entradas y Salidas
                </span>
            </a>

            <div class="flex shrink-0 items-center gap-2 sm:gap-4">
                @auth
                    {{--
                        Menú de módulos, en la barra de arriba SOLO en pantalla ancha. En móvil y
                        tableta el menú vive abajo, al alcance del pulgar (ver la barra inferior).

                        Dos oficios distintos: el turno (Operación) y la administración. Van en dos
                        grupos con una línea entre ellos; cada entrada aparece solo a quien tiene el
                        permiso, y el grupo entero desaparece si no queda ninguna.
                    --}}
                    @php
                        $operacion = collect([
                            ['ruta' => 'marcar', 'texto' => 'Marcar', 'permiso' => null],
                            ['ruta' => 'registro', 'texto' => 'Registro', 'permiso' => 'ver-registro'],
                            ['ruta' => 'reportes', 'texto' => 'Reportes', 'permiso' => 'ver-registro'],
                        ])->filter(fn ($m) => $puede($m['permiso']));

                        $administracion = collect([
                            ['ruta' => 'trabajadores', 'texto' => 'Trabajadores', 'permiso' => 'gestionar-personal'],
                            ['ruta' => 'usuarios', 'texto' => 'Usuarios', 'permiso' => 'gestionar-usuarios'],
                            ['ruta' => 'edificio', 'texto' => 'Edificio', 'permiso' => 'gestionar-edificio'],
                            ['ruta' => 'ajustes', 'texto' => 'Ajustes', 'permiso' => 'gestionar-ajustes'],
                            ['ruta' => 'roles', 'texto' => 'Roles', 'permiso' => 'gestionar-permisos'],
                            ['ruta' => 'auditoria', 'texto' => 'Auditoría', 'permiso' => 'ver-auditoria'],
                        ])->filter(fn ($m) => $puede($m['permiso']));

                        $grupos = collect([$operacion, $administracion])->filter->isNotEmpty()->values();
                    @endphp

                    <nav class="hidden shrink-0 items-center gap-1 font-mono text-sm font-semibold uppercase tracking-wide lg:flex"
                         aria-label="Módulos">
                        @foreach ($grupos as $i => $grupo)
                            @if ($i > 0)
                                <span class="mx-1.5 h-5 w-px bg-white/25" aria-hidden="true"></span>
                            @endif

                            @foreach ($grupo as $m)
                                @php $activo = request()->routeIs($m['ruta']); @endphp
                                <a href="{{ route($m['ruta']) }}"
                                   @if ($activo) aria-current="page" @endif
                                   class="rounded px-3 py-2 transition
                                          {{ $activo ? 'bg-white text-marca' : 'text-white/80 hover:bg-white/10 hover:text-white' }}">
                                    {{ $m['texto'] }}
                                </a>
                            @endforeach
                        @endforeach
                    </nav>

                    <span class="hidden h-9 w-px bg-white/25 lg:block"></span>

                    {{--
                        Quién tiene la sesión abierta. En el puesto se turnan varias personas en la
                        misma máquina, así que tiene que verse con qué usuario se está marcando. Se
                        muestra también en móvil, compacto: es de lo que más importa en la puerta.
                        Por aquí se entra a cambiar la propia clave.
                    --}}
                    <a href="{{ route('clave') }}"
                       title="Cambiar mi clave"
                       class="min-w-0 text-right leading-tight transition hover:opacity-80">
                        <span class="block max-w-[8rem] truncate text-sm font-semibold text-white sm:max-w-none">
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

    {{-- pb amplio en móvil para que el contenido no quede tapado por la barra inferior. --}}
    <main class="mx-auto w-full max-w-5xl grow px-4 pb-28 pt-8 sm:px-6 lg:pb-12">
        @yield('contenido')
    </main>

    @auth
        @php
            // La misma navegación, pero abajo y plana, para el pulgar. Incluye «Inicio» solo para
            // quien tiene un tablero (el vigilante no: su inicio ES marcar). Si a alguien le queda
            // un solo destino —el vigilante—, la barra no aparece: está siempre en su pantalla.
            $tabs = collect([
                ['ruta' => 'inicio', 'texto' => 'Inicio', 'permiso' => 'ver-registro', 'icono' => 'inicio'],
                ['ruta' => 'marcar', 'texto' => 'Marcar', 'permiso' => null, 'icono' => 'marcar'],
                ['ruta' => 'registro', 'texto' => 'Registro', 'permiso' => 'ver-registro', 'icono' => 'registro'],
                ['ruta' => 'reportes', 'texto' => 'Reportes', 'permiso' => 'ver-registro', 'icono' => 'reportes'],
                ['ruta' => 'trabajadores', 'texto' => 'Personal', 'permiso' => 'gestionar-personal', 'icono' => 'personal'],
                ['ruta' => 'usuarios', 'texto' => 'Usuarios', 'permiso' => 'gestionar-usuarios', 'icono' => 'usuarios'],
                ['ruta' => 'edificio', 'texto' => 'Edificio', 'permiso' => 'gestionar-edificio', 'icono' => 'edificio'],
                ['ruta' => 'ajustes', 'texto' => 'Ajustes', 'permiso' => 'gestionar-ajustes', 'icono' => 'ajustes'],
                ['ruta' => 'roles', 'texto' => 'Roles', 'permiso' => 'gestionar-permisos', 'icono' => 'roles'],
                ['ruta' => 'auditoria', 'texto' => 'Auditoría', 'permiso' => 'ver-auditoria', 'icono' => 'auditoria'],
            ])->filter(fn ($t) => $puede($t['permiso']))->values();

            $icono = fn ($clave) => match ($clave) {
                'inicio' => '<path d="M3 10.8 12 4l9 6.8"/><path d="M5.5 9.5V20h13V9.5"/>',
                'marcar' => '<rect x="3" y="5" width="18" height="14" rx="2.5"/><circle cx="8.5" cy="11" r="2"/><path d="M13 9.7h5M13 13h5M5.6 15.6c.6-1.5 3.2-1.5 3.8 0"/>',
                'registro' => '<path d="M8 6h12M8 12h12M8 18h12"/><circle cx="4" cy="6" r="1.1"/><circle cx="4" cy="12" r="1.1"/><circle cx="4" cy="18" r="1.1"/>',
                'reportes' => '<path d="M4 20V4"/><path d="M4 20h16"/><rect x="7" y="12" width="3" height="5"/><rect x="12" y="8" width="3" height="9"/><rect x="17" y="5" width="3" height="12"/>',
                'personal' => '<path d="M4 20c0-3.2 2.7-5 6-5s6 1.8 6 5"/><circle cx="10" cy="8" r="3.2"/><path d="M17 13.5c1.9.5 3 2 3 4.5"/>',
                'edificio' => '<path d="M4 21V6l7-3v18"/><path d="M11 8h6v13"/><path d="M7 9h0M7 12h0M7 15h0M14 12h0M14 16h0"/>',
                'ajustes' => '<circle cx="12" cy="12" r="3"/><path d="M12 3v2M12 19v2M5 5l1.5 1.5M17.5 17.5 19 19M3 12h2M19 12h2M5 19l1.5-1.5M17.5 6.5 19 5"/>',
                'usuarios' => '<circle cx="9" cy="8" r="3"/><path d="M3.8 20c0-3 2.4-5 5.2-5s5.2 2 5.2 5"/><path d="M16 6.6a3 3 0 0 1 0 5.6M20.5 20c0-2.4-1.5-4.2-3.6-4.8"/>',
                'roles' => '<path d="M12 3.2 19 6v5c0 4.4-3 7.4-7 8.8-4-1.4-7-4.4-7-8.8V6z"/><path d="M9 11.8l2 2 4-4"/>',
                'auditoria' => '<path d="M4 5h16v14H4z"/><path d="M8 9h8M8 13h8M8 17h5"/>',
                default => '',
            };
        @endphp

        @if ($tabs->count() >= 2)
            <nav class="sticky bottom-0 z-40 border-t border-slate-200 bg-white/95 backdrop-blur lg:hidden"
                 style="padding-bottom: env(safe-area-inset-bottom)"
                 aria-label="Módulos">
                <div class="mx-auto flex max-w-lg items-stretch justify-around">
                    @foreach ($tabs as $t)
                        @php $activo = request()->routeIs($t['ruta']); @endphp
                        <a href="{{ route($t['ruta']) }}"
                           @if ($activo) aria-current="page" @endif
                           class="flex flex-1 flex-col items-center gap-1 py-2.5 text-[10px] font-semibold tracking-wide transition
                                  {{ $activo ? 'text-marca' : 'text-slate-500 hover:text-slate-800' }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                 stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6">
                                {!! $icono($t['icono']) !!}
                            </svg>
                            {{ $t['texto'] }}
                        </a>
                    @endforeach
                </div>
            </nav>
        @endif
    @endauth
</body>
</html>
