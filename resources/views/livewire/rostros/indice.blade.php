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

    {{-- LO ESTRICTO QUE SE PONE LA PUERTA.

         Confundir a dos personas es lo peor que puede hacer esto: un nombre equivocado se cree y
         entra en el registro, mientras que un «no lo reconozco» solo obliga a usar el carnet. El
         punto bueno depende de las fotos que haya y de cuánta gente, así que se ajusta aquí. --}}
    @can('gestionar-personal')
        <div class="mt-6 border-t border-slate-200 pt-5">
            <p class="font-mono text-xs font-bold uppercase tracking-widest text-slate-500">
                Cuándo se atreve a decir un nombre
            </p>
            <p class="mt-1 text-sm text-slate-500">
                Si confunde a dos personas, baja el parecido o sube el margen. Es preferible que no reconozca
                a que se equivoque.
            </p>

            <form wire:submit="fijarAjustes" class="mt-3 flex flex-wrap items-end gap-3">
                <div class="w-40">
                    <x-campo etiqueta="Parecido máximo" nombre="umbral" inputmode="decimal"
                             ayuda="0,30 a 0,70. Más bajo, más estricto." wire:model="umbral" />
                </div>
                <div class="w-40">
                    <x-campo etiqueta="Margen al segundo" nombre="margen" inputmode="decimal"
                             ayuda="Cuánto más lejos ha de estar el 2.º." wire:model="margen" />
                </div>
                <div class="w-40">
                    <x-campo etiqueta="Veces seguidas" nombre="confirmaciones" inputmode="numeric"
                             ayuda="Cuadros que ha de ganar el mismo." wire:model="confirmaciones" />
                </div>
                <div class="pb-6">
                    <x-boton type="submit" variante="secundario" tamano="chico">Guardar</x-boton>
                </div>
            </form>

            <p class="text-xs leading-relaxed text-slate-500">
                <b>Parecido máximo</b>: a qué distancia como máximo se da por la misma persona.
                <b>Margen</b>: si el segundo candidato está casi igual de cerca, no dice nada y pide el carnet —sin
                esto, dos personas parecidas se resuelven a cara o cruz—.
                <b>Veces seguidas</b>: un cuadro malo puede acertar por casualidad; dos seguidos con la misma
                persona, ya no.
            </p>
        </div>
    @endcan

    {{-- MUESTRAS CON LA CÁMARA.

         La foto del carnet es de hace años. Cada muestra nueva es la misma cara con la luz, las
         gafas y el peinado de hoy, y al comparar se usa la que mejor case: por eso una persona con
         cuatro muestras se reconoce mucho mejor que con una. --}}
    @can('gestionar-personal')
        <div class="mt-6 border-t border-slate-200 pt-5">
            <p class="font-mono text-xs font-bold uppercase tracking-widest text-slate-500">
                Añadir caras con la cámara
            </p>
            <p class="mt-1 text-sm text-slate-500">
                La del carnet puede ser de hace años. Cuantas más caras tenga alguien, mejor se le reconoce.
            </p>

            {{-- Cuántas por persona. Lo que limita no es el reconocimiento: es que la galería
                 viaja entera al navegador —unos 250 kB por muestra con este personal— y que
                 cuantas más haya, más fácil es que una cara ajena caiga cerca de alguna. --}}
            @can('gestionar-personal')
                <form wire:submit="fijarMaxMuestras" class="mt-3 flex flex-wrap items-end gap-3">
                    <div class="w-40">
                        <x-campo etiqueta="Caras por persona" nombre="maxMuestras" inputmode="numeric"
                                 ayuda="Entre 1 y {{ \App\Services\Rostros\Rostros::TOPE_MUESTRAS }}."
                                 wire:model="maxMuestras" />
                    </div>
                    <div class="pb-6">
                        <x-boton type="submit" variante="secundario" tamano="chico">Guardar</x-boton>
                    </div>
                    <p class="pb-6 text-xs text-slate-500 sm:max-w-md">
                        De una a tres o cuatro está casi toda la mejora. Más arriba pesa más al abrir la cámara
                        y aumenta el riesgo de confundir a dos personas parecidas.
                    </p>
                </form>
            @endcan

            @unless ($this->personaDeMuestras)
                <form wire:submit="buscarParaMuestras" class="mt-3 flex flex-wrap items-end gap-3">
                    <div class="w-44">
                        <x-campo etiqueta="Cédula" nombre="cedulaMuestras" inputmode="numeric" maxlength="9"
                                 oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                                 wire:model="cedulaMuestras" :error="$errors->first('cedulaMuestras')" />
                    </div>
                    <div class="pb-6">
                        <x-boton type="submit" tamano="chico">Buscar</x-boton>
                    </div>
                </form>
            @else
                <div class="mt-3 rounded border border-slate-200 bg-white p-4 shadow-sm"
                     x-data="muestrasDeRostro($wire)">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <p class="font-semibold text-slate-900">{{ $this->personaDeMuestras->nombre }}</p>
                        <button type="button" wire:click="cerrarMuestras"
                                class="text-sm font-semibold text-slate-500 hover:underline">Cambiar de persona</button>
                    </div>

                    {{-- Lo que ya tiene, y de dónde salió cada una. --}}
                    <ul class="mt-3 divide-y divide-slate-100 text-sm">
                        @forelse ($this->muestras as $muestra)
                            <li class="flex items-center justify-between gap-3 py-2" wire:key="muestra-{{ $muestra->id }}">
                                <span>
                                    <span class="font-medium text-slate-800">
                                        {{ $muestra->origen === \App\Models\Rostro::DEL_CARNET ? 'Del carnet' : 'Tomada con la cámara' }}
                                    </span>
                                    <span class="ml-1 font-mono text-xs text-slate-400">
                                        {{ $muestra->calculado_en?->translatedFormat('d M Y · g:i a') }}
                                    </span>
                                </span>

                                <button type="button" wire:click="olvidarMuestra({{ $muestra->id }})"
                                        class="shrink-0 text-sm font-semibold text-alto hover:underline">Quitar</button>
                            </li>
                        @empty
                            <li class="py-2 text-slate-500">Todavía no tiene ninguna cara guardada.</li>
                        @endforelse
                    </ul>

                    <div x-show="!abierto" class="mt-3">
                        <x-boton type="button" x-on:click="abrir()">Añadir con la cámara</x-boton>
                        <p class="mt-2 text-xs text-slate-500">
                            Se guardan solas las que aporten algo distinto. Las que ya estén, o las que no se
                            parezcan a esta persona, se descartan.
                        </p>
                    </div>

                    <div x-show="abierto" x-cloak class="mt-3">
                        <div class="relative overflow-hidden rounded-xl bg-slate-900" @click="enfocar($event)">
                            <video x-ref="video" playsinline muted class="h-auto w-full"></video>

                            <div class="absolute inset-x-0 top-0 z-20 flex items-center gap-3 p-3">
                                <span class="rounded-full bg-black/50 px-3 py-1 font-mono text-xs font-bold text-white backdrop-blur-md">
                                    <span x-text="guardadas"></span> guardadas
                                </span>

                                <div class="ml-auto flex items-center gap-3">
                                    <button type="button" x-show="soportaLinterna" @click.stop="toggleLinterna()" x-cloak
                                            class="flex h-10 w-10 items-center justify-center rounded-full bg-black/40 text-white backdrop-blur-md transition hover:bg-black/60"
                                            :class="linternaEncendida ? '!bg-yellow-400 !text-black' : ''">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                                    </button>

                                    <button type="button" x-show="puedeCambiarCamara" @click.stop="cambiarCamara()" x-cloak
                                            class="flex h-10 w-10 items-center justify-center rounded-full bg-black/40 text-white backdrop-blur-md transition hover:bg-black/60">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                                    </button>
                                </div>
                            </div>

                            <p class="absolute inset-x-0 bottom-0 z-20 bg-gradient-to-t from-slate-900/90 to-transparent
                                      px-4 pb-4 pt-8 text-center text-sm font-semibold text-white"
                               x-text="mensaje"></p>
                        </div>

                        <x-boton type="button" variante="secundario" x-on:click="cerrar()" class="mt-3 w-full">
                            Terminar
                        </x-boton>
                    </div>
                </div>
            @endunless
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
