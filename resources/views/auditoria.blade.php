@extends('layouts.app')

@section('titulo', 'Auditoría')
@section('seccion', 'Auditoría')

@section('contenido')
    <h1 class="text-3xl font-bold tracking-tight">Auditoría</h1>

    <div class="mt-6">
        <livewire:auditoria.lista-de-bitacora />
    </div>
@endsection
