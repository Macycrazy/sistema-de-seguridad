@extends('layouts.app')

@section('titulo', 'Maqueta · escanear cédula')
@section('seccion', 'Maqueta')

@section('contenido')
    <h1 class="mb-2 text-2xl font-bold tracking-tight">Escanear la cédula con el teléfono</h1>
    <p class="mb-6 max-w-2xl text-slate-600">
        Cómo se vería marcar desde un teléfono, apuntando la cámara al carnet en vez de teclear.
    </p>

    <livewire:maqueta-escaneo />
@endsection
