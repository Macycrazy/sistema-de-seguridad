@extends('layouts.app')

@section('titulo', 'Puestos')
@section('seccion', 'Puestos')

@section('contenido')
    <div class="flex items-center gap-2.5">
        <h1 class="text-3xl font-bold tracking-tight">Puestos del estacionamiento</h1>
        <x-ayuda
            titulo="Puestos"
            que="Las plazas numeradas del estacionamiento. Se cargan una vez aquí, y luego se usan para saber qué está tomado y para asignarle una plaza a cada vehículo."
            :pasos="[
                '<b>Nuevo puesto</b>: un código (A-1, S2-14), su tipo (carro, moto o cualquiera) y una zona opcional.',
                'Un puesto se puede <b>deshabilitar</b> (no se ofrece) o quitar.',
                'Con los puestos cargados, en <b>Estacionamiento</b> ya se le puede asignar la plaza a cada vehículo.',
            ]" />
    </div>
    <p class="mt-1 text-sm text-slate-500">Las plazas numeradas donde se para cada vehículo. De aquí sale qué puestos están tomados y cuáles libres.</p>

    <div class="mt-6">
        <livewire:estacionamiento.lista-de-puestos />
    </div>
@endsection
