@extends('layouts.app')

@section('titulo', 'El registro')
@section('seccion', 'Parte 2 · El registro')

@section('contenido')
    {{-- El teal identifica al módulo, no decora: aquí dice de qué parte es esta pantalla.
         Va escrito aquí y no en <x-etiqueta> porque ese componente lo comparten las tres
         partes, y añadirle variantes desde una rama choca en el merge. --}}
    <div class="flex flex-wrap items-center gap-3">
        <span class="inline-flex items-center rounded bg-parte2-suave px-2.5 py-1 font-mono text-xs font-bold uppercase tracking-widest text-parte2">
            Parte 2
        </span>

        <h1 class="text-3xl font-bold tracking-tight">El registro</h1>
    </div>
    <p class="mt-3 max-w-2xl text-slate-600">
        Se llena solo, con cada marcaje. Los movimientos no se editan ni se borran: si hubo un
        error, se corrige con uno nuevo.
    </p>

    <div class="mt-8">
        <livewire:registro.registro-del-dia />
    </div>
@endsection
