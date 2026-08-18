@extends('layouts.app')

@section('titulo', 'Reportes')
@section('seccion', 'Reportes')

@section('contenido')
    <h1 class="text-3xl font-bold tracking-tight">Reportes</h1>
    <p class="mt-1 text-sm text-slate-500">Las cuentas del registro sobre un tramo de fechas.</p>

    <div class="mt-6">
        <livewire:reportes.panel />
    </div>
@endsection
