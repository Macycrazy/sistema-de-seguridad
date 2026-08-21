@extends('layouts.app')

@section('titulo', 'Ajustes')
@section('seccion', 'Ajustes')

@section('contenido')
    <div class="flex items-center gap-2.5">
        <h1 class="text-3xl font-bold tracking-tight">Ajustes</h1>
        <x-ayuda
            titulo="Ajustes"
            que="Los números que gobiernan el sistema, cambiables sin reprogramar: reglas de tiempo, umbrales de alerta y retención de datos."
            :pasos="[
                '<b>Reglas de tiempo</b>: cuánto esperar entre entrada y salida, y entre salida y la siguiente entrada.',
                '<b>Umbrales de alerta</b>: a partir de cuántas horas dentro avisa, y los aforos del estacionamiento.',
                '<b>Retención</b>: cuánto tiempo se guardan los datos.',
            ]"
            nota="Cada número trae su explicación al lado. Si algo no está claro, no lo cambies sin preguntar." />
    </div>
    <p class="mt-1 text-sm text-slate-500">Reglas de tiempo, umbrales de alerta y retención de datos.</p>

    <h2 class="mt-8 font-mono text-xs font-bold uppercase tracking-widest text-slate-500">Reglas de tiempo</h2>
    <div class="mt-3">
        <livewire:ajustes.lista-de-tiempos />
    </div>

    <h2 class="mt-10 font-mono text-xs font-bold uppercase tracking-widest text-slate-500">Umbrales de alerta</h2>
    <div class="mt-3">
        <livewire:ajustes.lista-de-umbrales />
    </div>

    <h2 class="mt-10 font-mono text-xs font-bold uppercase tracking-widest text-slate-500">Retención de datos</h2>
    <div class="mt-3">
        <livewire:ajustes.lista-de-retencion />
    </div>
@endsection
