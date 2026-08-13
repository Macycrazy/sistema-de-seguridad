@props([
    'numero' => 0,
    'leyenda' => 'Dentro ahora',
])

{{--
    La cifra que gobierna la pantalla: cuánta gente hay dentro.
    El slot es para lo que va al lado, normalmente el botón de exportar.
--}}
<div {{ $attributes->merge([
    'class' => 'flex flex-wrap items-center justify-between gap-4 rounded border-2 border-parte2 bg-white px-5 py-4',
]) }}>
    <p class="flex items-baseline gap-3">
        <span class="text-4xl font-bold tabular-nums tracking-tight text-slate-900">{{ $numero }}</span>
        <span class="font-mono text-xs font-semibold uppercase tracking-widest text-parte2">{{ $leyenda }}</span>
    </p>

    {{ $slot }}
</div>
