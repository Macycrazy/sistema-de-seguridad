@extends('layouts.app')

@section('titulo', 'Edificio')
@section('seccion', 'Edificio')

@section('contenido')
    <h1 class="text-3xl font-bold tracking-tight">Edificio</h1>

    <div class="mt-6">
        <livewire:edificio.lista-de-oficinas />
    </div>
@endsection
