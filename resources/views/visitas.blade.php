@extends('layouts.app')

@section('titulo', 'Visitas')
@section('seccion', 'Visitas')

@section('contenido')
    <h1 class="text-3xl font-bold tracking-tight">Visitas esperadas</h1>
    <p class="mt-1 text-sm text-slate-500">Quién se espera hoy, agendado antes de que llegue.</p>

    <div class="mt-6">
        <livewire:visitas.agenda />
    </div>
@endsection
