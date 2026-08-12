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

        <x-tarjeta parte="2" titulo="Parte 2">
            <p class="text-lg font-semibold">El registro</p>
            <p class="mt-1 text-sm text-slate-600">Pendiente</p>
        </x-tarjeta>

        <x-tarjeta parte="3" titulo="Parte 3">
            <p class="text-lg font-semibold">Usuarios y roles</p>
            <p class="mt-1 text-sm text-slate-600">Pendiente</p>
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
