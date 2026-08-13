{{--
    Paginación del proyecto.

    Sustituye a la vista que trae Livewire, que venía en inglés y con su propia paleta
    (gray, rounded-md, ring-blue, variantes dark:) sobre una aplicación que es toda
    española, slate y `rounded`.

    Dos cosas a tener en cuenta al tocarla:

    · La usan las TRES partes, así que aquí no va el color de ninguna. Todo en slate.
    · Sin iconos, como el resto del sistema: «Anterior» y «Siguiente» en letra, no
      chevrons. El servidor tampoco tiene salida a Internet para traerlos de fuera.
--}}
@php
    if (! isset($scrollTo)) {
        $scrollTo = 'body';
    }

    $scrollIntoViewJsSnippet = ($scrollTo !== false)
        ? <<<JS
           (\$el.closest('{$scrollTo}') || document.querySelector('{$scrollTo}')).scrollIntoView()
        JS
        : '';

    // Acotado a propósito: sin esto, los botones se deshabilitarían en cada cambio de
    // filtro de la pantalla que los contiene, no solo al cambiar de página.
    $mientrasPagina = 'previousPage,nextPage,gotoPage';

    $numero = 'inline-flex min-w-8 items-center justify-center rounded px-2 py-1
               font-mono text-xs font-semibold tabular-nums';
@endphp

<div>
    @if ($paginator->hasPages())
        <nav role="navigation" aria-label="Paginación" class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-slate-500">
                Mostrando
                <span class="font-medium tabular-nums text-slate-700">{{ $paginator->firstItem() }}</span>–<span class="font-medium tabular-nums text-slate-700">{{ $paginator->lastItem() }}</span>
                de
                <span class="font-medium tabular-nums text-slate-700">{{ $paginator->total() }}</span>
            </p>

            <div class="flex items-center gap-1">
                {{-- ANTERIOR --}}
                @if ($paginator->onFirstPage())
                    <x-boton variante="secundario" tamano="chico" disabled>Anterior</x-boton>
                @else
                    <x-boton
                        variante="secundario"
                        tamano="chico"
                        wire:click="previousPage('{{ $paginator->getPageName() }}')"
                        wire:loading.attr="disabled"
                        wire:target="{{ $mientrasPagina }}"
                        x-on:click="{{ $scrollIntoViewJsSnippet }}"
                    >Anterior</x-boton>
                @endif

                {{-- NÚMEROS · en pantallas estrechas estorban más de lo que ayudan --}}
                <span class="mx-1 hidden items-center gap-1 sm:flex">
                    @foreach ($elements as $element)
                        @if (is_string($element))
                            <span aria-hidden="true" class="{{ $numero }} text-slate-400">{{ $element }}</span>
                        @endif

                        @if (is_array($element))
                            @foreach ($element as $page => $url)
                                <span wire:key="paginator-{{ $paginator->getPageName() }}-page{{ $page }}">
                                    @if ($page == $paginator->currentPage())
                                        <span aria-current="page" class="{{ $numero }} bg-slate-900 text-white">
                                            {{ $page }}
                                        </span>
                                    @else
                                        <button
                                            type="button"
                                            wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')"
                                            wire:loading.attr="disabled"
                                            wire:target="{{ $mientrasPagina }}"
                                            x-on:click="{{ $scrollIntoViewJsSnippet }}"
                                            aria-label="Ir a la página {{ $page }}"
                                            class="{{ $numero }} text-slate-600 transition hover:bg-slate-100
                                                   focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-900
                                                   focus-visible:ring-offset-2 disabled:opacity-50"
                                        >{{ $page }}</button>
                                    @endif
                                </span>
                            @endforeach
                        @endif
                    @endforeach
                </span>

                {{-- SIGUIENTE --}}
                @if ($paginator->hasMorePages())
                    <x-boton
                        variante="secundario"
                        tamano="chico"
                        wire:click="nextPage('{{ $paginator->getPageName() }}')"
                        wire:loading.attr="disabled"
                        wire:target="{{ $mientrasPagina }}"
                        x-on:click="{{ $scrollIntoViewJsSnippet }}"
                    >Siguiente</x-boton>
                @else
                    <x-boton variante="secundario" tamano="chico" disabled>Siguiente</x-boton>
                @endif
            </div>
        </nav>
    @endif
</div>
