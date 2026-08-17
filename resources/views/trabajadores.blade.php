@extends('layouts.app')

@section('titulo', 'Trabajadores')
@section('seccion', 'Trabajadores')

@section('contenido')
    <h1 class="text-3xl font-bold tracking-tight">Trabajadores</h1>

    <div class="mt-6">
        <livewire:trabajadores.lista-de-trabajadores />
    </div>
@endsection
