@extends('layouts.app')

@section('titulo', 'Respaldos')
@section('seccion', 'Respaldos')

@section('contenido')
    <h1 class="text-3xl font-bold tracking-tight">Respaldos</h1>
    <p class="mt-1 text-sm text-slate-500">Copias de seguridad de la base de datos.</p>

    <div class="mt-6">
        <livewire:respaldos.panel />
    </div>
@endsection
