@extends('layouts.app')

@section('titulo', 'Auditoría')
@section('seccion', 'Auditoría')

@section('contenido')
    <div class="flex items-center gap-2.5">
        <h1 class="text-3xl font-bold tracking-tight">Auditoría</h1>
        <x-ayuda
            titulo="Auditoría"
            que="El rastro de quién hizo qué: quién consultó una cédula, vio una foto, exportó el registro o cambió algo, y cuándo."
            :pasos="[
                'Cada renglón dice <b>quién</b>, <b>qué acción</b> y a qué hora.',
                'Sirve para responsabilizar: por eso los usuarios se desactivan, no se borran.',
                'Es de solo lectura: aquí no se cambia nada, solo se consulta.',
            ]" />
    </div>
    <p class="mt-1 text-sm text-slate-500">Quién consultó, exportó o cambió qué, y cuándo.</p>

    <div class="mt-6">
        <livewire:auditoria.lista-de-bitacora />
    </div>
@endsection
