@extends('layouts.app')

@section('titulo', 'Alertas')
@section('seccion', 'Alertas')

@section('contenido')
    <div class="flex items-center gap-2.5">
        <h1 class="text-3xl font-bold tracking-tight">Alertas</h1>
        <x-ayuda
            titulo="Alertas"
            que="Lo que ahora mismo merece que alguien mire: quien lleva demasiado tiempo dentro, o cuando se pasa un aforo."
            :pasos="[
                'Una <b>permanencia larga</b> casi siempre es que nadie le marcó la salida: «Ya salió» registra la salida que faltó, sin borrar nada. Si de verdad sigue dentro, «Sigue dentro» calla el aviso hasta mañana.',
                'Cada alerta dice <b>qué pasa</b> y a quién afecta.',
                'Las <b>urgentes</b> van primero.',
                'Los límites (horas dentro, aforos) se ajustan en <b>Ajustes → Umbrales de alerta</b>.',
            ]" />
    </div>
    <p class="mt-1 text-sm text-slate-500">Lo que ahora mismo merece que alguien mire.</p>

    <x-nav-registro class="mt-4" />

    <div class="mt-6">
        <livewire:alertas.panel />
    </div>
@endsection
