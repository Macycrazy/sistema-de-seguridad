@extends('layouts.app')

@section('titulo', 'Estacionamiento')
@section('seccion', 'Estacionamiento')

@section('contenido')
    <h1 class="text-3xl font-bold tracking-tight">Estacionamiento</h1>
    <p class="mt-1 text-sm text-slate-500">Qué vehículos hay dentro ahora mismo.</p>

    <div class="mt-6">
        <livewire:estacionamiento.panel />
    </div>
@endsection
