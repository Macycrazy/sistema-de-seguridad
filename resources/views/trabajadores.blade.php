@extends('layouts.app')

@section('titulo', 'Trabajadores')
@section('seccion', 'Trabajadores')

@section('contenido')
    <h1 class="text-3xl font-bold tracking-tight">Trabajadores</h1>
    <p class="mt-1 text-sm text-slate-500">El personal que se marca en la puerta: alta manual o por Excel.</p>

    <div class="mt-6">
        <livewire:trabajadores.lista-de-trabajadores />
    </div>
@endsection
