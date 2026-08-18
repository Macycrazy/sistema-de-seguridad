@extends('layouts.app')

@section('titulo', 'Organigrama')
@section('seccion', 'Organigrama')

@section('contenido')
    <h1 class="text-3xl font-bold tracking-tight">Organigrama</h1>
    <p class="mt-1 text-sm text-slate-500">La estructura de unidades del CIIP.</p>

    <div class="mt-6">
        <livewire:organigrama.arbol />
    </div>
@endsection
