@props([
    'etiqueta' => null,
    'nombre' => null,
    'tamano' => 'normal',
    'error' => null,
    'ayuda' => null,
])

@php
    // «grande» es el campo de la cédula: se teclea sin mirar el teclado.
    //
    // «puerta» es ese mismo campo en el teléfono del puesto: más grande todavía y centrado,
    // porque se teclea mirando el carnet y se comprueba de un vistazo, con el brazo estirado.
    // Es un tamaño MÁS, no un cambio de los que ya había: lo demás del sistema sigue igual.
    $medidas = match ($tamano) {
        // Alto fijo —h-16— para que la casilla de la nacionalidad, que va al lado, pueda ser
        // exactamente igual de alta sin depender del tamaño de su letra.
        'puerta' => 'h-16 px-3 text-center text-3xl font-mono font-semibold tracking-wider',
        'grande' => 'px-4 py-4 text-2xl font-mono tracking-wider',
        default => 'px-3 py-2.5 text-sm',
    };

    // El campo de la puerta va rodeado del azul de la parte 1: es el único sitio donde se
    // escribe en toda la pantalla, y tiene que cantar cuál es.
    $borde = match (true) {
        (bool) $error => 'border-alto focus:border-alto focus:ring-alto/30',
        $tamano === 'puerta' => 'border-2 border-parte1 focus:border-parte1 focus:ring-parte1/25',
        default => 'border-slate-300 focus:border-slate-900 focus:ring-slate-900/20',
    };
@endphp

<div class="w-full">
    @if ($etiqueta)
        <label @if ($nombre) for="{{ $nombre }}" @endif
               class="mb-1.5 block font-mono text-xs font-semibold uppercase tracking-widest text-slate-500">
            {{ $etiqueta }}
        </label>
    @endif

    <input {{ $attributes->merge([
        'id' => $nombre,
        'name' => $nombre,
        'class' => "block w-full rounded border bg-white text-slate-900 placeholder-slate-400
                    focus:outline-none focus:ring-4 $medidas $borde",
    ]) }}>

    @if ($error)
        <p class="mt-1.5 text-sm text-alto">{{ $error }}</p>
    @elseif ($ayuda)
        <p class="mt-1.5 text-sm text-slate-500">{{ $ayuda }}</p>
    @endif
</div>
