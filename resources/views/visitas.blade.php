@extends('layouts.app')

@section('titulo', 'Visitas')
@section('seccion', 'Visitas')

@section('contenido')
    <div class="flex items-center gap-2.5">
        <h1 class="text-3xl font-bold tracking-tight">Visitas esperadas</h1>
        <x-ayuda
            titulo="Visitas esperadas"
            que="Se agenda a quién va a venir antes de que llegue, para que en la puerta ya lo esperen y sea más rápido."
            :pasos="[
                'Agendas la visita con su nombre, a quién va y cuándo.',
                'Cuando llega, en la puerta ya sale que se le esperaba.',
                'Ayuda a que el vigilante no tenga que averiguar nada en el momento.',
            ]" />
    </div>
    <p class="mt-1 text-sm text-slate-500">Quién se espera hoy, agendado antes de que llegue.</p>

    <div class="mt-6">
        <livewire:visitas.agenda />
    </div>
@endsection
