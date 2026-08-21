@extends('layouts.app')

@section('titulo', 'Registro')
@section('seccion', 'Registro')

@section('contenido')
    <div class="flex items-center gap-2.5">
        <h1 class="text-3xl font-bold tracking-tight">Registro</h1>
        <x-ayuda
            titulo="Registro"
            que="Todas las entradas y salidas: el registro del día y el histórico de cada persona."
            :pasos="[
                'Se listan los movimientos del día, del más reciente al más viejo.',
                'Los <b>filtros</b> (ente, tipo, fecha) y la búsqueda achican lo que ves.',
                'Al abrir a una persona ves <b>su histórico</b> de entradas y salidas.',
                'Con permiso, puedes <b>exportar</b> el registro a Excel.',
            ]" />
    </div>
    <p class="mt-1 text-sm text-slate-500">Los movimientos del día, con el histórico de cada persona.</p>

    <x-nav-registro class="mt-4" />

    <div class="mt-6">
        <livewire:registro.registro-del-dia />
    </div>
@endsection
