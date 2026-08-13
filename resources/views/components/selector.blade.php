@props([
    'etiqueta' => null,
    'nombre' => null,
    'opciones' => [],
    'error' => null,
    'ayuda' => null,
])

@php
    // Mismo borde y mismo foco que <x-campo>, para que un select y un input no se vean
    // como piezas de dos sistemas distintos. Clases escritas enteras: Tailwind 4 solo
    // reconoce el texto literal, nunca lo que se arma desde una variable.
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

    <select {{ $attributes->merge([
        'id' => $nombre,
        'name' => $nombre,
        'class' => "block w-full rounded border bg-white px-3 py-2.5 text-sm text-slate-900
                    focus:outline-none focus:ring-4 $borde",
    ]) }}>
        @foreach ($opciones as $valor => $texto)
            <option value="{{ $valor }}">{{ $texto }}</option>
        @endforeach

        {{ $slot }}
    </select>

    @if ($error)
        <p class="mt-1.5 text-sm text-alto">{{ $error }}</p>
    @elseif ($ayuda)
        <p class="mt-1.5 text-sm text-slate-500">{{ $ayuda }}</p>
    @endif
</div>
