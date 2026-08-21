@extends('layouts.app')

@section('titulo', 'Trabajadores')
@section('seccion', 'Trabajadores')

@section('contenido')
    <div class="flex items-center gap-2.5">
        <h1 class="text-3xl font-bold tracking-tight">Trabajadores</h1>
        <x-ayuda
            titulo="Trabajadores"
            que="El personal del edificio. Aquí se registra a quién se le marca en la puerta —los que aparecen por su cédula— y se corrigen sus datos."
            :pasos="[
                'Con <b>Trabajadores | Invitados</b> eliges a quién ves.',
                '<b>Nuevo trabajador</b> lo da de alta a mano; <b>Importar</b> sube una lista de Excel.',
                '<b>Editar</b> corrige nombre, gerencia, piso o ente (la cédula no se cambia).',
                'Los filtros de gerencia, ente y estado, y la búsqueda por cédula o nombre, achican la lista.',
            ]"
            nota="Un trabajador no se borra: se <b>desactiva</b> (deja de poder marcarse) y su histórico se conserva. Se puede reactivar." />
    </div>
    <p class="mt-1 text-sm text-slate-500">El personal que se marca en la puerta: alta manual o por Excel.</p>

    <div class="mt-6">
        <livewire:trabajadores.lista-de-trabajadores />
    </div>
@endsection
