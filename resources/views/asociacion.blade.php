@extends('layouts.app')

@section('titulo', 'Asociación con carnets')
@section('seccion', 'Asociación')

@section('contenido')
    <h1 class="text-3xl font-bold tracking-tight">Asociación con carnets</h1>
    <p class="mt-1 text-sm text-slate-500">Probar la conexión con el sistema de carnets y la lectura del QR.</p>

    <div class="mt-6">
        <livewire:asociacion.carnets />
    </div>
@endsection
