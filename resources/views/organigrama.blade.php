@extends('layouts.app')

@section('titulo', 'Organigrama')
@section('seccion', 'Organigrama')

@section('contenido')
    <div class="flex items-center gap-2.5">
        <h1 class="text-3xl font-bold tracking-tight">Organigrama</h1>
        <x-ayuda
            titulo="Organigrama"
            que="La estructura de unidades del CIIP: gerencias, coordinaciones y a quién cuelga cada una. Es lo que agrupa a los trabajadores por área."
            :pasos="[
                'Agregas una unidad y la cuelgas de su <b>unidad madre</b> para armar el árbol.',
                'Puedes mover, renombrar o desactivar una unidad.',
                'La gerencia que se le pone a un trabajador se enlaza aquí para poder agrupar.',
            ]" />
    </div>
    <p class="mt-1 text-sm text-slate-500">La estructura de unidades del CIIP.</p>

    <div class="mt-6">
        <livewire:organigrama.arbol />
    </div>
@endsection
