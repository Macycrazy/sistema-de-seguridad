@extends('layouts.app')

@section('titulo', 'Registro')
@section('seccion', 'Registro')

@section('contenido')
    <h1 class="text-3xl font-bold tracking-tight">Registro</h1>

    <div class="mt-6">
        <livewire:registro.registro-del-dia />
    </div>
@endsection
