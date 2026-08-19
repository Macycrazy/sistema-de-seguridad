@extends('layouts.app')

@section('titulo', 'Edificio')
@section('seccion', 'Edificio')

@section('contenido')
    <h1 class="text-3xl font-bold tracking-tight">Edificio</h1>
    <p class="mt-1 text-sm text-slate-500">El catálogo de oficinas que la puerta ofrece al marcar el piso de un visitante.</p>

    <div class="mt-6">
        <livewire:edificio.lista-de-oficinas />
    </div>
@endsection
