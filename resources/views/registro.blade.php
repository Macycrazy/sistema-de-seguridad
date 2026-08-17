@extends('layouts.app')

@section('titulo', 'Registro')
@section('seccion', 'Registro')

@section('contenido')
    <h1 class="text-3xl font-bold tracking-tight">Registro</h1>
    <p class="mt-3 max-w-2xl text-slate-600">
        Se llena solo, con cada marcaje. Los movimientos no se editan ni se borran: si hubo un
        error, se corrige con uno nuevo.
    </p>

    <div class="mt-8">
        <livewire:registro.registro-del-dia />
    </div>
@endsection
