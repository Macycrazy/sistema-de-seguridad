{{--
    El catálogo de pases y dónde está cada uno.

    Se entregan en la puerta, al marcar al visitante; aquí se cargan y se recuperan los que
    aparecen sin que nadie marque la salida, que pasa más de lo que gustaría.
--}}
<div>
    @if ($aviso !== '')
        <x-aviso class="mb-4" wire:key="aviso">{{ $aviso }}</x-aviso>
    @endif

    @if ($errors->has('pase') || $errors->has('tanda'))
        <x-error class="mb-4">{{ $errors->first('pase') ?: $errors->first('tanda') }}</x-error>
    @endif

    {{-- Lo primero que se quiere saber al llegar: cuántos faltan por volver. --}}
    <div class="grid grid-cols-3 gap-4">
        @foreach ([['Fuera', $this->cuentas['fuera'], 'alto'], ['Libres', $this->cuentas['libres'], 'parte1'], ['Total', $this->cuentas['total'], 'slate-500']] as [$rotulo, $numero, $color])
            <x-tarjeta>
                <p class="text-3xl font-bold tabular-nums text-slate-900">{{ $numero }}</p>
                <p class="mt-1 font-mono text-xs uppercase tracking-widest text-{{ $color }}">{{ $rotulo }}</p>
            </x-tarjeta>
        @endforeach
    </div>

    @can('gestionar-pases')
        <div class="mt-4 flex flex-wrap justify-end gap-2">
            @unless ($creando)
                <x-boton variante="secundario" wire:click="abrirNuevo">Nuevo pase</x-boton>
            @endunless
            @unless ($creandoTanda)
                <x-boton variante="secundario" wire:click="abrirTanda">Cargar una tanda</x-boton>
            @endunless
            @unless ($entregando)
                <x-boton wire:click="abrirEntrega">Entregar un pase</x-boton>
            @endunless
        </div>

        {{-- Entregar sin pasar por la puerta. Hace falta porque el sistema no empieza de cero:
             cuando se cargan los pases ya hay visitantes dentro a los que nadie les dio ninguno. --}}
        @if ($entregando)
            <form wire:submit="entregar" class="mt-3 flex flex-wrap items-end gap-3 rounded border border-slate-200 bg-white p-4 shadow-sm">
                <div class="w-full sm:w-44">
                    <x-campo etiqueta="Cédula de quien lo lleva" nombre="cedulaEntrega" inputmode="numeric" maxlength="9" autofocus
                             oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                             wire:model="cedulaEntrega" :error="$errors->first('cedulaEntrega')" />
                </div>
                <div class="w-full sm:w-60">
                    <x-selector etiqueta="Pase" nombre="paseEntrega" wire:model="paseEntrega"
                                :opciones="['' => 'Elegir pase…'] + $this->libres"
                                :error="$errors->first('paseEntrega')" />
                </div>
                <div class="flex items-center gap-2 pb-6">
                    <x-boton type="submit" tamano="chico">Entregar</x-boton>
                    <x-boton type="button" variante="secundario" tamano="chico" wire:click="cancelar">Cancelar</x-boton>
                </div>
                <p class="w-full text-xs text-slate-500">
                    Lo normal es entregarlo en la puerta, al marcar la entrada. Esto es para quien ya estaba dentro.
                </p>
            </form>
        @endif

        @if ($creando)
            <form wire:submit="guardar" class="mt-3 flex flex-wrap items-end gap-3 rounded border border-slate-200 bg-white p-4 shadow-sm">
                <div class="w-full sm:w-40">
                    <x-campo etiqueta="Código" nombre="codigo" maxlength="20" autofocus
                             ayuda="Lo que va escrito en el pase." wire:model="codigo" :error="$errors->first('codigoPase')" />
                </div>
                <div class="w-full sm:w-56">
                    <x-campo etiqueta="Nota" nombre="nota" maxlength="120" ayuda="Opcional: tanda, color…" wire:model="nota" />
                </div>
                <div class="flex items-center gap-2 pb-6">
                    <x-boton type="submit" tamano="chico">Guardar</x-boton>
                    <x-boton type="button" variante="secundario" tamano="chico" wire:click="cancelar">Cancelar</x-boton>
                </div>
            </form>
        @endif

        {{-- Cargar treinta pases a mano es lo que hace que no se carguen. --}}
        @if ($creandoTanda)
            <form wire:submit="guardarTanda" class="mt-3 flex flex-wrap items-end gap-3 rounded border border-slate-200 bg-white p-4 shadow-sm">
                <div class="w-full sm:w-32">
                    <x-campo etiqueta="Prefijo" nombre="prefijoTanda" maxlength="10" autofocus wire:model="prefijoTanda" />
                </div>
                <div class="w-24">
                    <x-campo etiqueta="Del" nombre="desdeTanda" inputmode="numeric" wire:model="desdeTanda" />
                </div>
                <div class="w-24">
                    <x-campo etiqueta="Al" nombre="hastaTanda" inputmode="numeric" wire:model="hastaTanda" />
                </div>
                <div class="flex items-center gap-2 pb-1.5">
                    <x-boton type="submit" tamano="chico">Cargar</x-boton>
                    <x-boton type="button" variante="secundario" tamano="chico" wire:click="cancelar">Cancelar</x-boton>
                </div>
                <p class="w-full text-xs text-slate-500">
                    Se saltan los que ya existan, así que la tanda se puede ampliar más adelante sin pensar.
                </p>
            </form>
        @endif
    @endcan

    <div class="mt-4 overflow-x-auto rounded border border-slate-200 bg-white shadow-sm">
        <table class="w-full min-w-[36rem] text-sm">
            <thead>
                <tr class="border-b border-slate-200 text-left font-mono text-xs uppercase tracking-widest text-slate-500">
                    <th class="px-4 py-3 font-semibold">Pase</th>
                    <th class="px-4 py-3 font-semibold">Estado</th>
                    <th class="px-4 py-3 font-semibold">Quién lo lleva</th>
                    <th class="px-4 py-3 text-right font-semibold">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($this->pases as $pase)
                    @php $entrega = $this->fuera->get($pase->id); @endphp
                    <tr wire:key="pase-{{ $pase->id }}" class="{{ $pase->activo ? '' : 'opacity-50' }}">
                        <td class="px-4 py-3">
                            <span class="font-mono text-base font-bold tracking-wider text-slate-900">{{ $pase->codigo }}</span>
                            @if ($pase->nota)
                                <span class="ml-1 text-xs text-slate-400">{{ $pase->nota }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if (! $pase->activo)
                                <span class="font-mono text-xs uppercase tracking-widest text-slate-400">Deshabilitado</span>
                            @elseif ($entrega)
                                <span class="font-mono text-xs font-bold uppercase tracking-widest text-alto">Fuera</span>
                            @else
                                <span class="font-mono text-xs uppercase tracking-widest text-parte1">Libre</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-600">
                            @if ($entrega)
                                {{ $entrega->persona?->nombre ?? '—' }}
                                <span class="block font-mono text-xs text-slate-400">
                                    desde las {{ $entrega->entregado_en->format('g:i a') }}
                                    @if ($entrega->usuario) · lo entregó {{ $entrega->usuario->nombre ?? $entrega->usuario->usuario }} @endif
                                </span>
                            @else
                                <span class="text-slate-300">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            @can('gestionar-pases')
                                <span class="flex flex-wrap justify-end gap-3">
                                    @if ($entrega)
                                        {{-- Para cuando el pase aparece y nadie marcó la salida. --}}
                                        <button wire:click="recuperar({{ $entrega->id }})"
                                                class="text-sm font-semibold text-parte1 hover:underline">Recuperar</button>
                                    @else
                                        <button wire:click="habilitar({{ $pase->id }}, {{ $pase->activo ? 'false' : 'true' }})"
                                                class="text-sm font-semibold text-slate-500 hover:underline">
                                            {{ $pase->activo ? 'Deshabilitar' : 'Habilitar' }}
                                        </button>
                                        <button wire:click="eliminar({{ $pase->id }})"
                                                wire:confirm="¿Quitar el pase {{ $pase->codigo }} del catálogo?"
                                                class="text-sm font-semibold text-alto hover:underline">Quitar</button>
                                    @endif
                                </span>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <x-tabla-vacia :columnas="4">
                        Todavía no hay pases cargados. Con «Cargar una tanda» se dan de alta todos de una vez.
                    </x-tabla-vacia>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
