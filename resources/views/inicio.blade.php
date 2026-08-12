@extends('layouts.app')

@section('titulo', 'Registro de Entradas y Salidas')

@section('contenido')
    <h1 class="text-3xl font-bold tracking-tight">Registro de entradas y salidas</h1>
    <p class="mt-3 max-w-2xl text-slate-600">
        Base del proyecto. Si estás viendo esta página con estilos, el entorno quedó bien montado:
        Laravel responde, Vite compiló y Tailwind está aplicando clases.
    </p>

    <div class="mt-10 grid gap-4 sm:grid-cols-3">
        <div class="rounded border-t-4 border-indigo-800 bg-white p-5 shadow-sm">
            <p class="font-mono text-xs font-bold uppercase tracking-widest text-indigo-800">Parte 1</p>
            <p class="mt-2 text-lg font-semibold">Marcar e invitados</p>
            <p class="mt-1 text-sm text-slate-600">Pendiente</p>
        </div>
        <div class="rounded border-t-4 border-cyan-800 bg-white p-5 shadow-sm">
            <p class="font-mono text-xs font-bold uppercase tracking-widest text-cyan-800">Parte 2</p>
            <p class="mt-2 text-lg font-semibold">El registro</p>
            <p class="mt-1 text-sm text-slate-600">Pendiente</p>
        </div>
        <div class="rounded border-t-4 border-emerald-800 bg-white p-5 shadow-sm">
            <p class="font-mono text-xs font-bold uppercase tracking-widest text-emerald-800">Parte 3</p>
            <p class="mt-2 text-lg font-semibold">Usuarios y roles</p>
            <p class="mt-1 text-sm text-slate-600">Pendiente</p>
        </div>
    </div>

    <p class="mt-10 text-sm text-slate-500">
        Los pasos de instalación y el alcance de cada parte están en el
        <span class="font-mono">README.md</span>.
    </p>
@endsection
