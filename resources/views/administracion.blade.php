@extends('layouts.app')

@section('titulo', 'Administración')
@section('seccion', 'Administración')

@section('contenido')
    <div class="flex items-center gap-2.5">
        <h1 class="text-3xl font-bold tracking-tight">Administración</h1>
        <x-ayuda
            titulo="Administración"
            que="El sitio único desde donde se configura y se gestiona todo. Cada tarjeta abre un módulo, y cada módulo tiene su propia ayuda («?»)."
            :pasos="[
                'Solo ves las tarjetas de lo que <b>tu rol</b> puede administrar.',
                'Entra a cada módulo y toca su «?» para saber qué hace.',
                'Lo de todos los días (marcar, estacionamiento) está en el menú de arriba, no aquí.',
            ]" />
    </div>
    <p class="mt-1 text-sm text-slate-500">Todo lo que se configura y se gestiona, en un solo sitio.</p>

    @php
        // Cada tarjeta con su permiso: el panel muestra solo lo que cada quien puede administrar.
        // El permiso es el de ENTRAR (ver-*): quien solo puede ver un módulo también ve su tarjeta.
        // Como gestionar implica ver, quien pueda gestionarlo entra por el mismo permiso.
        $modulos = [
            ['ruta' => 'trabajadores', 'permiso' => 'ver-personal', 'titulo' => 'Trabajadores', 'texto' => 'Alta e importación del personal que se marca.'],
            ['ruta' => 'organigrama', 'permiso' => 'ver-organigrama', 'titulo' => 'Organigrama', 'texto' => 'La estructura de unidades del CIIP.'],
            ['ruta' => 'usuarios', 'permiso' => 'ver-usuarios', 'titulo' => 'Usuarios', 'texto' => 'Cuentas que entran al sistema, roles y claves.'],
            ['ruta' => 'edificio', 'permiso' => 'ver-edificio', 'titulo' => 'Edificio', 'texto' => 'Las oficinas que se ofrecen al marcar el piso.'],
            ['ruta' => 'puestos', 'permiso' => 'ver-puestos', 'titulo' => 'Puestos', 'texto' => 'Las plazas numeradas del estacionamiento.'],
            ['ruta' => 'pases', 'permiso' => 'ver-pases', 'titulo' => 'Pases de visitante', 'texto' => 'Las credenciales que se prestan en la puerta y quién lleva cada una.'],
            ['ruta' => 'rostros', 'permiso' => 'ver-personal', 'titulo' => 'Reconocimiento facial', 'texto' => 'Indexar las caras del personal para que la puerta proponga quién es.'],
            ['ruta' => 'ajustes', 'permiso' => 'ver-ajustes', 'titulo' => 'Ajustes', 'texto' => 'Reglas de tiempo, umbrales de alerta y retención.'],
            ['ruta' => 'auditoria', 'permiso' => 'ver-auditoria', 'titulo' => 'Auditoría', 'texto' => 'Quién consultó, exportó o cambió qué.'],
            ['ruta' => 'roles', 'permiso' => 'gestionar-permisos', 'titulo' => 'Roles', 'texto' => 'Qué puede hacer cada rol.'],
            ['ruta' => 'respaldos', 'permiso' => 'gestionar-respaldos', 'titulo' => 'Respaldos', 'texto' => 'Copias de seguridad de la base de datos.'],
            ['ruta' => 'asociacion', 'permiso' => 'gestionar-ajustes', 'titulo' => 'Asociación con carnets', 'texto' => 'Probar la conexión con el sistema de carnets y el QR.'],
        ];
    @endphp

    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($modulos as $m)
            @can($m['permiso'])
                <a href="{{ route($m['ruta']) }}" class="block transition hover:shadow-md">
                    <x-tarjeta parte="3" class="h-full">
                        <p class="text-lg font-semibold">{{ $m['titulo'] }}</p>
                        <p class="mt-1 text-sm text-slate-500">{{ $m['texto'] }}</p>

                        {{-- Cuánto tiene cargado. Un catálogo vacío y uno lleno se ven igual desde
                             aquí, y eso engaña el primer día: quien entra a un módulo en blanco no
                             sabe si está roto, si le falta permiso o si nadie ha cargado nada. --}}
                        @if (isset($inventario[$m['ruta']]))
                            @php $tiene = $inventario[$m['ruta']]; @endphp

                            <p class="mt-3 font-mono text-xs uppercase tracking-widest
                                      {{ $tiene['cuantos'] === 0 ? 'text-invitado' : 'text-slate-400' }}">
                                @if ($tiene['cuantos'] === 0)
                                    Sin cargar
                                @else
                                    {{ number_format($tiene['cuantos']) }} {{ $tiene['etiqueta'] }}
                                @endif
                            </p>
                        @endif
                    </x-tarjeta>
                </a>
            @endcan
        @endforeach
    </div>
@endsection
