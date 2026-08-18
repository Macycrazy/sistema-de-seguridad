@extends('layouts.app')

@section('titulo', 'Registro')
@section('seccion', 'Registro')

@section('contenido')
    <h1 class="text-3xl font-bold tracking-tight">Registro</h1>
    <p class="mt-1 text-sm text-slate-500">Los movimientos del día, con el histórico de cada persona.</p>

    <x-nav-registro class="mt-4" />

    <div class="mt-6">
        <livewire:registro.registro-del-dia />
    </div>
@endsection
