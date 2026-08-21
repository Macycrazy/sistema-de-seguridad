@extends('layouts.app')

@section('titulo', 'Edificio')
@section('seccion', 'Edificio')

@section('contenido')
    <div class="flex items-center gap-2.5">
        <h1 class="text-3xl font-bold tracking-tight">Edificio</h1>
        <x-ayuda
            titulo="Edificio"
            que="El catálogo de oficinas del edificio. Es lo que la puerta ofrece al elegir a qué piso va un visitante."
            :pasos="[
                '<b>Nueva oficina</b>: código como «2-1» o «LOBBY», y un nombre opcional.',
                'A cada oficina se le puede asociar su <b>gerencia</b>, para que al asignar el piso de un trabajador se ofrezcan los suyos.',
                'Editar o quitar una oficina cuando cambie el edificio.',
            ]" />
    </div>
    <p class="mt-1 text-sm text-slate-500">El catálogo de oficinas que la puerta ofrece al marcar el piso de un visitante.</p>

    <div class="mt-6">
        <livewire:edificio.lista-de-oficinas />
    </div>
@endsection
