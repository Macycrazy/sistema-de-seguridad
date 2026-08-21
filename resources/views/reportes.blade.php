@extends('layouts.app')

@section('titulo', 'Reportes')
@section('seccion', 'Reportes')

@section('contenido')
    <div class="flex items-center gap-2.5">
        <h1 class="text-3xl font-bold tracking-tight">Reportes</h1>
        <x-ayuda
            titulo="Reportes"
            que="Las cuentas del registro sobre un tramo de fechas: cuánta gente, de qué ente, cuándo hubo más movimiento."
            :pasos="[
                'Eliges el <b>tramo de fechas</b> que quieres analizar.',
                'El sistema arma los totales y desgloses de ese tramo.',
                'Sirve para informes: cuántos entraron, por ente, por día.',
            ]" />
    </div>
    <p class="mt-1 text-sm text-slate-500">Las cuentas del registro sobre un tramo de fechas.</p>

    <x-nav-registro class="mt-4" />

    <div class="mt-6">
        <livewire:reportes.panel />
    </div>
@endsection
