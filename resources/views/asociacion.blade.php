@extends('layouts.app')

@section('titulo', 'Asociación con carnets')
@section('seccion', 'Asociación')

@section('contenido')
    <div class="flex items-center gap-2.5">
        <h1 class="text-3xl font-bold tracking-tight">Asociación con carnets</h1>
        <x-ayuda
            titulo="Asociación con carnets"
            que="El puente con el sistema de carnets: de ahí salen las fotos del personal y ahí se verifica el QR que se escanea en la puerta."
            :pasos="[
                '<b>Probar conexión</b> comprueba que el sistema de carnets responde en la dirección configurada.',
                'La dirección (CARNETS_URL) y la de las fotos se ponen en el <code>.env</code> del servidor.',
                'Si el QR o las fotos fallan, casi siempre es la dirección o la red, no el sistema.',
            ]" />
    </div>
    <p class="mt-1 text-sm text-slate-500">Probar la conexión con el sistema de carnets y la lectura del QR.</p>

    <div class="mt-6">
        <livewire:asociacion.carnets />
    </div>
@endsection
