@props([
    'titulo' => null,
    'parte' => null,
])

@php
    $borde = match ($parte) {
        '1' => 'border-t-4 border-t-parte1',
        '2' => 'border-t-4 border-t-parte2',
        '3' => 'border-t-4 border-t-parte3',
        default => '',
    };
@endphp

<div {{ $attributes->merge([
    'class' => "rounded border border-slate-200 bg-white p-5 shadow-sm $borde",
]) }}>
    @if ($titulo)
        <h3 class="mb-3 font-mono text-xs font-semibold uppercase tracking-widest text-slate-500">
            {{ $titulo }}
        </h3>
    @endif

    {{ $slot }}
</div>
