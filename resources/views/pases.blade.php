@extends('layouts.app')

@section('titulo', 'Pases')
@section('seccion', 'Pases')

@section('contenido')
    <div class="flex items-center gap-2.5">
        <h1 class="text-3xl font-bold tracking-tight">Pases de visitante</h1>
        <x-ayuda
            titulo="Pases de visitante"
            que="Las credenciales numeradas que se le prestan a quien viene de visita. Aquí se cargan y se ve cuáles están en la calle."
            :pasos="[
                '<b>Cargar una tanda</b>: un prefijo y un rango («V-» del 1 al 20) y quedan todos dados de alta.',
                'El pase se <b>entrega en la puerta</b>, al marcar la entrada del visitante, y vuelve al marcarle la salida.',
                '<b>Entregar un pase</b> desde aquí es para quien YA estaba dentro cuando se cargaron los pases, o llegó cuando no quedaba ninguno libre.',
                '<b>Recuperar</b> es para cuando el pase aparece y nadie marcó la salida: en el mostrador, en un cajón, al cerrar el turno.',
                'Un pase perdido se <b>deshabilita</b> en vez de quitarse: así su histórico se puede seguir leyendo.',
            ]"
            nota="En Alertas sale cada pase que lleva fuera demasiado tiempo. El plazo se cambia en Ajustes." />
    </div>
    <p class="mt-1 text-sm text-slate-500">Qué pases hay y quién lleva cada uno ahora mismo.</p>

    <div class="mt-6">
        <livewire:pases.lista-de-pases />
    </div>
@endsection
