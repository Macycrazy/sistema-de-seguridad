@props([
    'titulo' => null,
    // Una frase de qué hace el módulo, para quien llega nuevo.
    'que' => null,
    // Los pasos, en orden. Cada uno es texto (admite <b> para resaltar).
    'pasos' => [],
    // Un recuadro destacado al final, opcional.
    'nota' => null,
])

{{--
    La ayuda de cada módulo: un «?» que abre un recuadro con qué hace la pantalla y cómo se usa.
    Es para la gente nueva; no estorba, está cerrado hasta que se toca. Mismo patrón en todo el
    sistema —se cierra tocando fuera, la equis, Escape o «Entendido»— para que se aprenda una vez.
--}}
<span x-data="{ ayuda: false }" class="inline-flex align-middle">
    <button type="button" x-on:click="ayuda = true"
            class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full border border-slate-300
                   text-sm font-bold text-slate-500 transition hover:bg-slate-50 hover:text-slate-800"
            aria-label="¿Cómo se usa esta pantalla?">?</button>

    <div x-show="ayuda" x-cloak x-transition.opacity
         x-on:click.self="ayuda = false" x-on:keydown.escape.window="ayuda = false"
         class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/50 p-4 sm:items-center">
        <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
            <div class="flex items-start justify-between gap-4">
                <h2 class="text-xl font-bold tracking-tight">{{ $titulo ?? '¿Cómo se usa?' }}</h2>
                <button type="button" x-on:click="ayuda = false" aria-label="Cerrar"
                        class="-mr-1 -mt-1 flex h-8 w-8 items-center justify-center rounded-full text-slate-400 hover:bg-slate-100 hover:text-slate-700">✕</button>
            </div>

            @if ($que)
                <p class="mt-3 text-slate-600">{!! $que !!}</p>
            @endif

            @if (! empty($pasos))
                <ol class="mt-5 space-y-4">
                    @foreach ($pasos as $i => $paso)
                        <li class="flex items-start gap-3">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-marca text-sm font-bold text-white">{{ $i + 1 }}</span>
                            <p class="text-slate-700">{!! $paso !!}</p>
                        </li>
                    @endforeach
                </ol>
            @endif

            {{ $slot }}

            @if ($nota)
                <p class="mt-5 rounded-lg bg-invitado-suave px-3 py-2.5 text-sm font-medium text-invitado">{!! $nota !!}</p>
            @endif

            <x-boton type="button" x-on:click="ayuda = false" class="mt-6 w-full">Entendido</x-boton>
        </div>
    </div>
</span>
