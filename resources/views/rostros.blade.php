@extends('layouts.app')

@section('titulo', 'Reconocimiento facial')
@section('seccion', 'Reconocimiento facial')

@section('contenido')
    <div class="flex items-center gap-2.5">
        <h1 class="text-3xl font-bold tracking-tight">Reconocimiento facial</h1>
        <x-ayuda
            titulo="Reconocimiento facial"
            que="Deja que la puerta proponga quién es quien tiene delante, mirando por la cámara. Es una ayuda para el vigilante, no un sustituto: él confirma con la foto."
            :pasos="[
                '<b>Indexar</b> recorre las fotos del personal y saca de cada cara 128 números. Se hace en este equipo: ninguna foto sale de aquí.',
                'Hecho eso, en <b>Marcar</b> aparece el botón de buscar por rostro.',
                'La puerta <b>propone</b> a quién se parece y rellena la cédula. Marcar sigue siendo cosa del vigilante.',
                'Si a alguien le cambian la foto en carnets, <b>Volver a indexar todos</b>: el índice guarda la cara que tenía el día que se miró.',
                '<b>Borrar el índice</b> lo deja todo como estaba, y el sistema sigue funcionando igual con el carnet.',
            ]"
            nota="Los 128 números identifican a una persona, así que son un dato personal: quién indexa y quién borra queda en la auditoría." />
    </div>
    <p class="mt-1 text-sm text-slate-500">Las caras del personal, para que la puerta pueda proponer quién es.</p>

    <div class="mt-6">
        <livewire:rostros.indice />
    </div>
@endsection
