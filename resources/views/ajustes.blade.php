@extends('layouts.app')

@section('titulo', 'Ajustes')
@section('seccion', 'Ajustes')

@section('contenido')
    <h1 class="text-3xl font-bold tracking-tight">Ajustes</h1>

    <h2 class="mt-8 font-mono text-xs font-bold uppercase tracking-widest text-slate-500">Reglas de tiempo</h2>
    <div class="mt-3">
        <livewire:ajustes.lista-de-tiempos />
    </div>

    <h2 class="mt-10 font-mono text-xs font-bold uppercase tracking-widest text-slate-500">Umbrales de alerta</h2>
    <div class="mt-3">
        <livewire:ajustes.lista-de-umbrales />
    </div>

    <h2 class="mt-10 font-mono text-xs font-bold uppercase tracking-widest text-slate-500">Retención de datos</h2>
    <div class="mt-3">
        <livewire:ajustes.lista-de-retencion />
    </div>
@endsection
