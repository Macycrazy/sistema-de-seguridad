@extends('layouts.app')

@section('titulo', 'Alertas')
@section('seccion', 'Alertas')

@section('contenido')
    <h1 class="text-3xl font-bold tracking-tight">Alertas</h1>
    <p class="mt-1 text-sm text-slate-500">Lo que ahora mismo merece que alguien mire.</p>

    <div class="mt-6">
        <livewire:alertas.panel />
    </div>
@endsection
