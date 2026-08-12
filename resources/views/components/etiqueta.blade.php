@props(['tipo' => 'trabajador'])

@php
    // Un solo lugar decide de qué color se ve cada cosa en todo el sistema.
    [$clases, $texto] = match ($tipo) {
        'invitado' => ['bg-invitado-suave text-invitado', 'INVITADO'],
        'entrada' => ['bg-ok-suave text-ok', 'ENTRADA'],
        'salida' => ['bg-slate-100 text-slate-600', 'SALIDA'],
        'inactivo' => ['bg-alto-suave text-alto', 'INACTIVO'],
        default => ['bg-parte1-suave text-parte1', 'TRABAJADOR'],
    };
@endphp

<span {{ $attributes->merge([
    'class' => "inline-flex items-center rounded px-2.5 py-1 font-mono text-xs
                font-bold uppercase tracking-widest $clases",
]) }}>
    {{ trim($slot) ?: $texto }}
</span>
