@props([
    'etiqueta' => null,
    'nombre' => null,
    'tamano' => 'normal',
    'error' => null,
    'ayuda' => null,
    // Un ojito para ver la clave mientras se teclea: útil en el teléfono, donde un punto o una
    // mayúscula mal puesta no se ven bajo los asteriscos. Empieza oculta.
    'revelable' => false,
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

    @if ($revelable)
        {{-- El ojito: empieza en «password» (oculta) y solo se muestra si se toca. --}}
        <div class="relative" x-data="{ ver: false }">
            <input {{ $attributes->merge([
                'id' => $nombre,
                'name' => $nombre,
                'class' => "block w-full rounded border bg-white text-slate-900 placeholder-slate-400
                            focus:outline-none focus:ring-4 pr-11! $medidas $borde",
            ]) }} x-bind:type="ver ? 'text' : 'password'">

            <button type="button" tabindex="-1" @click="ver = !ver"
                    x-bind:aria-label="ver ? 'Ocultar la clave' : 'Ver la clave'"
                    class="absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 hover:text-slate-700">
                {{-- Ojo (clave oculta) --}}
                <svg x-show="!ver" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                     stroke="currentColor" stroke-width="2" class="h-5 w-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-7.5 9.75-7.5 9.75 7.5 9.75 7.5-3.75 7.5-9.75 7.5S2.25 12 2.25 12z"/>
                    <circle cx="12" cy="12" r="3"/>
                </svg>
                {{-- Ojo tachado (clave visible). Oculto de entrada, para que no parpadee antes de Alpine. --}}
                <svg x-show="ver" style="display:none" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                     stroke="currentColor" stroke-width="2" class="h-5 w-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.5 10.5 0 002.25 12s3.75 7.5 9.75 7.5c1.77 0 3.37-.5 4.77-1.27M6.53 6.53A10.4 10.4 0 0112 4.5c6 0 9.75 7.5 9.75 7.5a12.9 12.9 0 01-2.17 3.03M3 3l18 18"/>
                </svg>
            </button>
        </div>
    @else
        <input {{ $attributes->merge([
            'id' => $nombre,
            'name' => $nombre,
            'class' => "block w-full rounded border bg-white text-slate-900 placeholder-slate-400
                        focus:outline-none focus:ring-4 $medidas $borde",
        ]) }}>
    @endif

    @if ($error)
        <p class="mt-1.5 text-sm text-alto">{{ $error }}</p>
    @elseif ($ayuda)
        <p class="mt-1.5 text-sm text-slate-500">{{ $ayuda }}</p>
    @endif
</div>
