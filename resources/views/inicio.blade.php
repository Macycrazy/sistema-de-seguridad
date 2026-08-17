@extends('layouts.app')

@section('titulo', 'Inicio')
@section('seccion', 'Inicio')

@section('contenido')
    <div class="flex flex-wrap items-end justify-between gap-5">
        <div>
            <h1 class="text-3xl font-bold tracking-tight">Hola, {{ auth()->user()->nombreCorto() }}</h1>
            <p class="mt-2 text-slate-600">Registro de entradas y salidas del edificio.</p>
        </div>

        {{-- El pulso del edificio, de un vistazo. Es el mismo número que gobierna marcar y el
             registro: se reusa el contador, sin tocar esos módulos. --}}
        <x-contador :numero="$dentro" class="w-full sm:w-auto" />
    </div>

    {{-- OPERACIÓN · lo del día a día --}}
    <h2 class="mt-10 font-mono text-xs font-bold uppercase tracking-widest text-slate-500">Operación</h2>
    <div class="mt-3 grid gap-4 sm:grid-cols-2">
        <a href="{{ route('marcar') }}" class="block transition hover:shadow-md">
            <x-tarjeta parte="1" class="h-full">
                <p class="text-lg font-semibold">Marcar</p>
                <p class="mt-1 text-sm text-slate-600">La pantalla de la puerta: entrada, salida e invitados.</p>
            </x-tarjeta>
        </a>

        <a href="{{ route('registro') }}" class="block transition hover:shadow-md">
            <x-tarjeta parte="2" class="h-full">
                <p class="text-lg font-semibold">Registro</p>
                <p class="mt-1 text-sm text-slate-600">La lista del día, la búsqueda y la exportación a Excel.</p>
            </x-tarjeta>
        </a>
    </div>

    {{-- ADMINISTRACIÓN · solo aparece si hay algo que administrar --}}
    @canany(['gestionar-usuarios', 'gestionar-permisos'])
        <h2 class="mt-8 font-mono text-xs font-bold uppercase tracking-widest text-slate-500">Administración</h2>
        <div class="mt-3 grid gap-4 sm:grid-cols-2">
            @can('gestionar-usuarios')
                <a href="{{ route('usuarios') }}" class="block transition hover:shadow-md">
                    <x-tarjeta parte="3" class="h-full">
                        <p class="text-lg font-semibold">Usuarios</p>
                        <p class="mt-1 text-sm text-slate-600">Quién entra al sistema y con qué alcance.</p>
                    </x-tarjeta>
                </a>
            @endcan

            @can('gestionar-permisos')
                <a href="{{ route('roles') }}" class="block transition hover:shadow-md">
                    <x-tarjeta parte="3" class="h-full">
                        <p class="text-lg font-semibold">Roles</p>
                        <p class="mt-1 text-sm text-slate-600">Qué puede hacer cada rol, configurable.</p>
                    </x-tarjeta>
                </a>
            @endcan
        </div>
    @endcanany
@endsection
