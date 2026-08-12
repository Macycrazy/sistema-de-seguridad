@props([
    'etiqueta' => null,
    'nombre' => null,
    'tamano' => 'normal',
    'error' => null,
    'ayuda' => null,
])

@php
    // «grande» es el campo de la cédula: se teclea sin mirar el teclado.
    $medidas = $tamano === 'grande'
        ? 'px-4 py-4 text-2xl font-mono tracking-wider'
        : 'px-3 py-2.5 text-sm';

    $borde = $error
        ? 'border-alto focus:border-alto focus:ring-alto/30'
        : 'border-slate-300 focus:border-slate-900 focus:ring-slate-900/20';
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
