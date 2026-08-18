@extends('layouts.app')

@section('titulo', 'Registro de Entradas y Salidas')

@section('contenido')
    <h1 class="text-3xl font-bold tracking-tight">Registro de entradas y salidas</h1>
    <p class="mt-3 max-w-2xl text-slate-600">
        Base del proyecto. Si estás viendo esta página con estilos, el entorno quedó bien montado:
        Laravel responde, Vite compiló y Tailwind está aplicando clases.
    </p>

    <div class="mt-10 grid gap-4 sm:grid-cols-3">
        <x-tarjeta parte="1" titulo="Parte 1">
            <p class="text-lg font-semibold">Marcar e invitados</p>
            <a href="{{ route('marcar') }}" class="mt-1 inline-block text-sm font-semibold text-parte1 underline">
                Abrir la pantalla de marcar
            </a>
        </x-tarjeta>

        {{--
            El registro es la lista completa del personal, así que el vigilante no lo abre. La
            tarjeta se le muestra igual, sin enlace: esconderla del todo haría parecer que al
            sistema le falta algo. Esto es cortesía, no seguridad — quien teclee la dirección a
            mano se topa con un 403.
        --}}
        @can('ver-registro')
            <a href="{{ route('registro') }}" class="block transition hover:shadow-md">
                <x-tarjeta parte="2" titulo="Parte 2" class="h-full">
                    <p class="text-lg font-semibold">El registro</p>
                    <p class="mt-1 text-sm text-parte2">Ver la pantalla &rarr;</p>
                </x-tarjeta>
            </a>
        @else
            <x-tarjeta parte="2" titulo="Parte 2" class="h-full">
                <p class="text-lg font-semibold">El registro</p>
                <p class="mt-1 text-sm text-slate-500">Lo ve el supervisor</p>
            </x-tarjeta>
        @endcan

        {{--
            La tarjeta no va envuelta en un enlace porque lleva dos dentro, y un enlace dentro de
            otro no es marcado válido. Cada quien ve los que le abren.
        --}}
        <x-tarjeta parte="3" titulo="Parte 3" class="h-full">
            <p class="text-lg font-semibold">Usuarios y roles</p>

            <div class="mt-1 space-y-1 text-sm">
                @can('gestionar-usuarios')
                    <a href="{{ route('usuarios') }}" class="block font-semibold text-parte3 underline">
                        Gestionar los usuarios
                    </a>
                @endcan

                @can('gestionar-permisos')
                    <a href="{{ route('roles') }}" class="block font-semibold text-parte3 underline">
                        Roles y permisos
                    </a>
                @endcan

                @can('ver-auditoria')
                    <a href="{{ route('auditoria') }}" class="block font-semibold text-parte3 underline">
                        Auditoría
                    </a>
                @endcan

                @cannot('gestionar-usuarios')
                    <p class="text-slate-500">Los gestiona el supervisor</p>
                @endcannot
            </div>
        </x-tarjeta>
    </div>

    <div class="mt-10 flex flex-wrap items-center gap-4">
        <a href="{{ route('diseno') }}">
            <x-boton variante="secundario">Ver la base visual</x-boton>
        </a>
        <p class="text-sm text-slate-500">
            Los pasos de instalación y el alcance de cada parte están en el
            <span class="font-mono">README.md</span>.
        </p>
    </div>
@endsection
