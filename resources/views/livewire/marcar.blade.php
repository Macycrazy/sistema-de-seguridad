{{--
    La pantalla de la puerta. Todo lo que se ve aquí sale de los componentes de /diseno:
    x-campo, x-boton, x-etiqueta y x-tarjeta. Nada de clases sueltas inventadas.

    El foco vive en el campo de la cédula: se teclea, Enter, y el botón que corresponde ya
    está resaltado. El vigilante no debería necesitar el ratón.
--}}
<div class="mx-auto max-w-3xl">

    {{-- Cuántos están dentro. Es el dato que el vigilante mira de reojo. --}}
    <div class="mb-6 flex items-baseline justify-between">
        <h1 class="text-3xl font-bold tracking-tight">Marcar</h1>
        <p class="font-mono text-xs uppercase tracking-widest text-slate-500">
            Dentro ahora:
            <span class="text-base font-bold text-slate-900">{{ $this->dentro }}</span>
        </p>
    </div>

    {{-- Confirmación del último marcaje. Se va sola en cuanto se teclea la siguiente cédula. --}}
    @if ($confirmacion !== '')
        <div class="mb-5 rounded border border-ok/30 bg-ok-suave px-4 py-3 text-sm font-semibold text-ok"
             wire:key="confirmacion">
            {{ $confirmacion }}
        </div>
    @endif

    {{-- EL CAMPO DE LA CÉDULA --}}
    <x-tarjeta parte="1">
        <form wire:submit="buscar">
            <x-campo
                etiqueta="Cédula"
                nombre="cedula"
                tamano="grande"
                placeholder="0.000.000"
                autofocus
                autocomplete="off"
                inputmode="numeric"
                wire:model="cedula"
                :error="$errors->first('cedula')"
                ayuda="Teclea la cédula y pulsa Enter, o pasa el carnet por el lector."
            />
            {{-- El submit del formulario es lo que responde al Enter y al lector de carnets. --}}
            <button type="submit" class="sr-only">Buscar</button>
        </form>
    </x-tarjeta>

    {{-- QUIÉN ES --}}
    @if ($this->persona)
        {{-- Siempre en bloque: la forma en línea de esta directiva no cierra su etiqueta. --}}
        @php
            $persona = $this->persona;
        @endphp

        <x-tarjeta class="mt-5" wire:key="persona-{{ $persona->id }}">
            <div class="flex items-start gap-5">

                {{-- La foto. Si no hay, las iniciales: no se piden imágenes a Internet. --}}
                <div class="flex h-24 w-24 shrink-0 items-center justify-center overflow-hidden rounded bg-slate-100">
                    @if ($persona->foto_ruta)
                        <img src="{{ asset($persona->foto_ruta) }}"
                             alt="Foto de {{ $persona->nombre }}"
                             class="h-full w-full object-cover">
                    @else
                        <span class="font-mono text-2xl font-bold text-slate-400">
                            {{ $persona->iniciales() }}
                        </span>
                    @endif
                </div>

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-3">
                        <p class="text-2xl font-bold tracking-tight">{{ $persona->nombre }}</p>
                        <x-etiqueta :tipo="$persona->tipo" />
                        @unless ($persona->activo)
                            <x-etiqueta tipo="inactivo" />
                        @endunless
                    </div>

                    <p class="mt-1 font-mono text-sm text-slate-500">{{ $persona->cedulaConPuntos() }}</p>

                    @if ($persona->esTrabajador())
                        <p class="mt-2 text-slate-600">{{ $persona->dependencia }}</p>
                    @else
                        {{-- Del invitado que vuelve se puede corregir a quién viene a ver hoy. --}}
                        <div class="mt-3 max-w-sm">
                            <x-campo
                                etiqueta="A quién viene a ver"
                                nombre="visita"
                                wire:model="visita"
                                :error="$errors->first('visita')"
                            />
                        </div>
                    @endif
                </div>
            </div>

            {{-- LOS DOS BOTONES --}}
            @if ($persona->activo)
                @php
                    // Cada botón conserva SIEMPRE su color: el verde significa entrada en todo el
                    // sistema y no se le presta al otro botón. Lo que cambia es el realce del que
                    // corresponde, y que los botones nunca se mueven de sitio: el vigilante los
                    // busca por posición, no por color.
                    $realce = 'ring-2 ring-slate-900 ring-offset-2';
                    $apagado = 'opacity-50';

                    // Se calculan aquí y se pasan con «:class», que es la forma de dar una
                    // expresión PHP a un componente sin meterla dentro del atributo.
                    $claseEntrada = $this->sugerido === 'entrada' ? $realce : $apagado;
                    $claseSalida = $this->sugerido === 'salida' ? $realce : $apagado;
                @endphp

                <div class="mt-6 flex flex-wrap items-center gap-3 border-t border-slate-100 pt-5">
                    <x-boton
                        variante="entrada"
                        tamano="grande"
                        :class="$claseEntrada"
                        wire:click="marcarEntrada"
                        wire:loading.attr="disabled"
                    >ENTRADA</x-boton>

                    <x-boton
                        variante="salida"
                        tamano="grande"
                        :class="$claseSalida"
                        wire:click="marcarSalida"
                        wire:loading.attr="disabled"
                    >SALIDA</x-boton>

                    <p class="ml-auto max-w-[16rem] text-sm text-slate-500">
                        @if ($this->sugerido === 'salida')
                            Está dentro: lo que toca es la salida.
                        @else
                            No está dentro: lo que toca es la entrada.
                        @endif
                    </p>
                </div>
            @else
                <p class="mt-6 border-t border-slate-100 pt-5 text-sm font-semibold text-alto">
                    Esta persona está desactivada y no se le puede marcar. Avisa al supervisor.
                </p>
            @endif

            <div class="mt-4">
                <x-boton variante="secundario" tamano="chico" wire:click="limpiar">
                    Cancelar y empezar de nuevo
                </x-boton>
            </div>
        </x-tarjeta>
    @endif

    {{-- INVITADO NUEVO: la cédula no está en el sistema --}}
    @if ($invitadoNuevo)
        <x-tarjeta class="mt-5" wire:key="invitado-nuevo">
            <div class="mb-4 rounded bg-invitado-suave px-4 py-3">
                <p class="flex flex-wrap items-center gap-2 font-semibold text-invitado">
                    <x-etiqueta tipo="invitado" />
                    Esta cédula no está en el sistema: es un invitado.
                </p>
                <p class="mt-1 text-sm text-slate-600">
                    Solo hacen falta dos datos. La próxima vez que venga, con la cédula bastará.
                </p>
            </div>

            <form wire:submit="guardarInvitado" class="max-w-md space-y-5">
                <x-campo
                    etiqueta="Nombre y apellido"
                    nombre="nombre"
                    wire:model="nombre"
                    autocomplete="off"
                    ayuda="Como aparece en el documento."
                    :error="$errors->first('nombre')"
                />

                <x-campo
                    etiqueta="A quién viene a ver"
                    nombre="visita"
                    wire:model="visita"
                    autocomplete="off"
                    :error="$errors->first('visita')"
                />

                <div class="flex items-center gap-3">
                    <x-boton type="submit" wire:loading.attr="disabled">Guardar y continuar</x-boton>
                    <x-boton variante="secundario" wire:click="limpiar" type="button">Cancelar</x-boton>
                </div>
            </form>
        </x-tarjeta>
    @endif
</div>
