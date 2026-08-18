@extends('layouts.app')

@section('titulo', 'Ajustes')
@section('seccion', 'Ajustes')

@section('contenido')
    <h1 class="text-3xl font-bold tracking-tight">Ajustes</h1>

    <div class="mt-6">
        <livewire:ajustes.lista-de-tiempos />
    </div>
@endsection
