{{--
    El índice de rostros.

    Lo calcula el navegador: aquí se enseña qué falta y se recogen los números. Ninguna foto sale
    del equipo que tiene esta pantalla abierta.
--}}
{{-- Sin datos en el atributo: el navegador se los pide a Livewire al pulsar. Un @json
     aquí rompe el valor —sus comillas chocan con las del atributo— y con cientos de personas
     pesaría más que la página. --}}
<div x-data="indiceDeRostros($wire)">
    @if ($aviso !== '')
        <x-aviso class="mb-4" wire:key="aviso">{{ $aviso }}</x-aviso>
    @endif

    <div class="grid grid-cols-3 gap-4">
        @foreach ([['Con rostro', $this->estado['indexadas'], 'parte1'], ['Faltan', $this->estado['faltan'], 'invitado'], ['Personal', $this->estado['total'], 'slate-500']] as [$rotulo, $numero, $color])
            <x-tarjeta>
                <p class="text-3xl font-bold tabular-nums text-slate-900">{{ $numero }}</p>
                <p class="mt-1 font-mono text-xs uppercase tracking-widest text-{{ $color }}">{{ $rotulo }}</p>
            </x-tarjeta>
        @endforeach
    </div>

    {{-- Mientras trabaja: se dice por quién va, porque son decenas de fotos y una barra parada sin
         explicación se lee como que se colgó. --}}
    <template x-if="trabajando">
        <div class="mt-4 rounded border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-sm text-slate-600">
                <span x-show="hechas === 0 && actual.startsWith('cargando')" x-text="actual"></span>
                <span x-show="!(hechas === 0 && actual.startsWith('cargando'))">
                    Mirando <span class="font-semibold" x-text="actual"></span>…
                    <span class="font-mono text-xs text-slate-400" x-text="hechas + ' de ' + total"></span>
                </span>
            </p>
            <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100">
                <div class="h-full rounded-full bg-parte1 transition-all"
                     :style="'width: ' + (total ? Math.round(hechas / total * 100) : 0) + '%'"></div>
            </div>
            <p class="mt-2 text-xs text-slate-500">
                No cierres esta pestaña: el cálculo ocurre aquí, en este equipo.
            </p>
        </div>
    </template>

    <template x-if="error">
        <div class="mt-4"><x-error x-text="error"></x-error></div>
    </template>

    @can('gestionar-personal')
        <div class="mt-4 flex flex-wrap items-center gap-3">
            <x-boton x-show="!trabajando"
                     x-on:click="indexar('pendientes')"
                     :disabled="$this->estado['faltan'] === 0">
                {{ $this->estado['faltan'] === 0 ? 'No falta ninguno' : 'Indexar los '.$this->estado['faltan'].' que faltan' }}
            </x-boton>

            @if ($this->estado['indexadas'] > 0)
                {{-- La foto manda y puede cambiar: el índice guarda la cara que TENÍA esa persona
                     el día que se miró. Si en carnets le ponen una foto nueva, hay que volver a
                     mirarlas o el reconocimiento seguirá buscando la cara vieja. --}}
                {{-- Solo a quien le cambió la foto en carnets: se sabe comparando el hash que
                     publica su padrón con el que se guardó al indexar. Sin la API configurada no
                     hay con qué comparar y solo queda mirarlos a todos. --}}
                {{-- Solo a quien le cambió la foto en carnets: se sabe comparando el hash que
                     publica su padrón con el que se guardó al indexar. Se pregunta cuando se pulsa
                     y no al abrir la pantalla: es una llamada por la red a un sistema que puede no
                     estar, y no se va a esperar por ella para pintar un botón. --}}
                @if ($this->padronDisponible)
                    @if ($desactualizados !== [])
                        <x-boton x-show="!trabajando" x-on:click="indexar('desactualizados')">
                            Actualizar los {{ count($desactualizados) }} que cambiaron de foto
                        </x-boton>
                    @elseif (! $comprobado)
                        <x-boton variante="secundario" x-show="!trabajando" wire:click="comprobarCambios">
                            Comprobar si alguna foto cambió
                        </x-boton>
                    @endif
                @endif

                <x-boton variante="secundario" x-show="!trabajando"
                         x-on:click="indexar('todos')">
                    Volver a indexar todos
                </x-boton>

                <x-boton variante="secundario" x-show="!trabajando"
                         wire:click="vaciar"
                         wire:confirm="¿Borrar el índice entero? Habrá que volver a indexar para usar el reconocimiento.">
                    Borrar el índice
                </x-boton>
            @endif

            <p class="text-sm text-slate-500">
                La primera vez baja unos 12 MB de modelos; después quedan en el navegador.
            </p>
        </div>
    @endcan

    @if ($fallidas !== [])
        <div class="mt-5 overflow-hidden rounded border-l-4 border-invitado bg-invitado-suave/40">
            <div class="px-4 py-3">
                <p class="font-mono text-xs font-bold uppercase tracking-widest text-invitado">
                    {{ count($fallidas) }} sin indexar
                </p>
                <p class="mt-1 text-sm text-slate-600">
                    No pasa nada: esas personas se marcan con su carnet, como siempre. Pero el motivo dice
                    dónde arreglarlo —dar de alta a alguien en carnets, subirle una foto, o repetir una que
                    salió movida—.
                </p>
            </div>

            {{-- Agrupadas por motivo: cada uno se arregla en un sitio distinto —dar de alta a
                 alguien en carnets, subirle una foto, repetir una foto movida— y en una lista
                 corrida de ciento y pico nombres eso no se ve. --}}
            <div class="divide-y divide-white/60 bg-white/50 text-sm">
                @foreach (collect($fallidas)->groupBy('motivo') as $motivo => $quienes)
                    <div class="px-4 py-3">
                        <p class="font-semibold text-slate-800">
                            {{ ucfirst($motivo) }}
                            <span class="ml-1 font-mono text-xs font-normal text-slate-500">{{ $quienes->count() }}</span>
                        </p>
                        <p class="mt-1 text-xs leading-relaxed text-slate-500">
                            {{ $quienes->pluck('nombre')->filter()->take(12)->implode(' · ') }}@if ($quienes->count() > 12) … y {{ $quienes->count() - 12 }} más @endif
                        </p>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
