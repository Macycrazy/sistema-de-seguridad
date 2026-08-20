@extends('layouts.app')

@section('titulo', 'Puestos')
@section('seccion', 'Puestos')

@section('contenido')
    <h1 class="text-3xl font-bold tracking-tight">Puestos del estacionamiento</h1>
    <p class="mt-1 text-sm text-slate-500">Las plazas numeradas donde se para cada vehículo. De aquí sale qué puestos están tomados y cuáles libres.</p>

    <div class="mt-6">
        <livewire:estacionamiento.lista-de-puestos />
    </div>
@endsection
