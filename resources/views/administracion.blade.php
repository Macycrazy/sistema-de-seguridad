@extends('layouts.app')

@section('titulo', 'Administración')
@section('seccion', 'Administración')

@section('contenido')
    <h1 class="text-3xl font-bold tracking-tight">Administración</h1>
    <p class="mt-1 text-sm text-slate-500">Todo lo que se configura y se gestiona, en un solo sitio.</p>

    @php
        // Cada tarjeta con su permiso: el panel muestra solo lo que cada quien puede administrar.
        $modulos = [
            ['ruta' => 'trabajadores', 'permiso' => 'gestionar-personal', 'titulo' => 'Trabajadores', 'texto' => 'Alta e importación del personal que se marca.'],
            ['ruta' => 'organigrama', 'permiso' => 'gestionar-personal', 'titulo' => 'Organigrama', 'texto' => 'La estructura de unidades del CIIP.'],
            ['ruta' => 'usuarios', 'permiso' => 'gestionar-usuarios', 'titulo' => 'Usuarios', 'texto' => 'Cuentas que entran al sistema, roles y claves.'],
            ['ruta' => 'edificio', 'permiso' => 'gestionar-edificio', 'titulo' => 'Edificio', 'texto' => 'Las oficinas que se ofrecen al marcar el piso.'],
            ['ruta' => 'puestos', 'permiso' => 'gestionar-edificio', 'titulo' => 'Puestos', 'texto' => 'Las plazas numeradas del estacionamiento.'],
            ['ruta' => 'ajustes', 'permiso' => 'gestionar-ajustes', 'titulo' => 'Ajustes', 'texto' => 'Reglas de tiempo, umbrales de alerta y retención.'],
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
                    </x-tarjeta>
                </a>
            @endcan
        @endforeach
    </div>
@endsection
