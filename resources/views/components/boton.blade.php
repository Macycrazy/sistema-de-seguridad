@props([
    'variante' => 'primario',
    'tamano' => 'normal',
    'type' => 'button',
])

@php
    $colores = match ($variante) {
        'entrada' => 'bg-ok text-white hover:opacity-90',
        'salida' => 'bg-white text-slate-600 border border-slate-300 hover:bg-slate-50',
        'secundario' => 'bg-white text-slate-700 border border-slate-300 hover:bg-slate-50',
        'peligro' => 'bg-alto text-white hover:opacity-90',
        default => 'bg-slate-900 text-white hover:bg-slate-700',
    };

    // «grande» es para los botones de la puerta: el vigilante los toca de pie y apurado.
    $medidas = match ($tamano) {
        'grande' => 'px-8 py-5 text-lg',
        'chico' => 'px-3 py-1.5 text-xs',
        default => 'px-4 py-2.5 text-sm',
    };
@endphp

<button {{ $attributes->merge([
    'type' => $type,
    'class' => "inline-flex items-center justify-center gap-2 rounded font-semibold tracking-wide
                transition focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-900
                focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50
                $colores $medidas",
]) }}>
    {{ $slot }}
</button>
