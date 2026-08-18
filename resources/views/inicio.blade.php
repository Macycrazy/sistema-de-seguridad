@extends('layouts.app')

@section('titulo', 'Inicio')
@section('seccion', 'Inicio')

@section('contenido')
    <div class="flex flex-wrap items-end justify-between gap-5">
        <h1 class="text-3xl font-bold tracking-tight">Hola, {{ auth()->user()->nombreCorto() }}</h1>

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
            </x-tarjeta>
        </a>

        <a href="{{ route('registro') }}" class="block transition hover:shadow-md">
            <x-tarjeta parte="2" class="h-full">
                <p class="text-lg font-semibold">Registro</p>
            </x-tarjeta>
        </a>

        @can('ver-registro')
            <a href="{{ route('reportes') }}" class="block transition hover:shadow-md">
                <x-tarjeta parte="2" class="h-full">
                    <p class="text-lg font-semibold">Reportes</p>
                    <p class="mt-1 text-sm text-slate-500">Las cuentas del registro por tramo de fechas.</p>
                </x-tarjeta>
            </a>

            @php $alertasActivas = app(\App\Services\Alertas\Alertas::class)->cuantas(); @endphp
            <a href="{{ route('alertas') }}" class="block transition hover:shadow-md">
                <x-tarjeta parte="2" class="h-full">
                    <p class="flex items-center gap-2 text-lg font-semibold">
                        Alertas
                        @if ($alertasActivas > 0)
                            <span class="inline-flex min-w-[1.25rem] justify-center rounded-full bg-alto px-1.5 py-0.5 text-[11px] font-bold leading-none text-white">{{ $alertasActivas }}</span>
                        @endif
                    </p>
                    <p class="mt-1 text-sm text-slate-500">Permanencias largas y aforo superado.</p>
                </x-tarjeta>
            </a>
        @endcan

        @can('gestionar-visitas')
            <a href="{{ route('visitas') }}" class="block transition hover:shadow-md">
                <x-tarjeta parte="1" class="h-full">
                    <p class="text-lg font-semibold">Visitas</p>
                    <p class="mt-1 text-sm text-slate-500">Quién se espera hoy, agendado antes de que llegue.</p>
                </x-tarjeta>
            </a>
        @endcan
    </div>

    {{-- ADMINISTRACIÓN · solo aparece si hay algo que administrar --}}
    @canany(['gestionar-personal', 'gestionar-edificio', 'gestionar-ajustes', 'gestionar-usuarios', 'gestionar-permisos', 'ver-auditoria'])
        <h2 class="mt-8 font-mono text-xs font-bold uppercase tracking-widest text-slate-500">Administración</h2>
        <div class="mt-3 grid gap-4 sm:grid-cols-2">
            @can('gestionar-personal')
                <a href="{{ route('trabajadores') }}" class="block transition hover:shadow-md">
                    <x-tarjeta parte="3" class="h-full">
                        <p class="text-lg font-semibold">Trabajadores</p>
                    </x-tarjeta>
                </a>

                <a href="{{ route('organigrama') }}" class="block transition hover:shadow-md">
                    <x-tarjeta parte="3" class="h-full">
                        <p class="text-lg font-semibold">Organigrama</p>
                        <p class="mt-1 text-sm text-slate-500">La estructura de unidades del CIIP.</p>
                    </x-tarjeta>
                </a>
            @endcan

            @can('gestionar-edificio')
                <a href="{{ route('edificio') }}" class="block transition hover:shadow-md">
                    <x-tarjeta parte="3" class="h-full">
                        <p class="text-lg font-semibold">Edificio</p>
                    </x-tarjeta>
                </a>
            @endcan

            @can('gestionar-ajustes')
                <a href="{{ route('ajustes') }}" class="block transition hover:shadow-md">
                    <x-tarjeta parte="3" class="h-full">
                        <p class="text-lg font-semibold">Ajustes</p>
                    </x-tarjeta>
                </a>
            @endcan

            @can('gestionar-usuarios')
                <a href="{{ route('usuarios') }}" class="block transition hover:shadow-md">
                    <x-tarjeta parte="3" class="h-full">
                        <p class="text-lg font-semibold">Usuarios</p>
                    </x-tarjeta>
                </a>
            @endcan

            @can('gestionar-permisos')
                <a href="{{ route('roles') }}" class="block transition hover:shadow-md">
                    <x-tarjeta parte="3" class="h-full">
                        <p class="text-lg font-semibold">Roles</p>
                    </x-tarjeta>
                </a>
            @endcan

            @can('ver-auditoria')
                <a href="{{ route('auditoria') }}" class="block transition hover:shadow-md">
                    <x-tarjeta parte="3" class="h-full">
                        <p class="text-lg font-semibold">Auditoría</p>
                    </x-tarjeta>
                </a>
            @endcan
        </div>
    @endcanany
@endsection
