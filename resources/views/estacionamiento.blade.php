@extends('layouts.app')

@section('titulo', 'Estacionamiento')
@section('seccion', 'Estacionamiento')

@section('contenido')
    <div class="flex items-center gap-2.5">
        <h1 class="text-3xl font-bold tracking-tight">Estacionamiento</h1>
        <x-ayuda
            titulo="Estacionamiento"
            que="Qué vehículos hay dentro y en qué puesto. Aquí se anotan, se les pone la plaza y se sacan —la puerta marca personas, no vehículos—."
            :pasos="[
                '<b>Anotar vehículo</b> al entrar: eliges de la flota o tecleas la placa, y pones el conductor. El puesto puede quedar <b>«sin puesto todavía»</b>.',
                'Cuando ves dónde quedó, le asignas el <b>puesto</b> en el desplegable de su fila.',
                '<b>Sacar</b> al salir, anotando quién se lo lleva (puede ser otro conductor).',
                '<b>Flota de la empresa</b> guarda los vehículos propios para elegirlos sin teclear.',
            ]"
            nota="Abajo ves los que <b>pernoctan</b> (se quedaron de noche) y un <b>reporte por noche</b> para consultar fechas pasadas." />
    </div>
    <p class="mt-1 text-sm text-slate-500">Qué vehículos hay dentro ahora mismo.</p>

    <div class="mt-6">
        <livewire:estacionamiento.panel />
    </div>
@endsection
