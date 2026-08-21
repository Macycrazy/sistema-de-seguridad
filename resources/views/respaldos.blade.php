@extends('layouts.app')

@section('titulo', 'Respaldos')
@section('seccion', 'Respaldos')

@section('contenido')
    <div class="flex items-center gap-2.5">
        <h1 class="text-3xl font-bold tracking-tight">Respaldos</h1>
        <x-ayuda
            titulo="Respaldos"
            que="Copias de seguridad de la base de datos, para no perder los registros si algo le pasa al servidor."
            :pasos="[
                'Puedes crear un respaldo cuando quieras.',
                'Se listan los respaldos que ya existen.',
                'Guárdalos también fuera del servidor: un respaldo en el mismo disco no protege de que se dañe el disco.',
            ]" />
    </div>
    <p class="mt-1 text-sm text-slate-500">Copias de seguridad de la base de datos.</p>

    <div class="mt-6">
        <livewire:respaldos.panel />
    </div>
@endsection
