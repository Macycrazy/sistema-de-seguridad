<div>
    @if ($aviso)
        <x-aviso class="mb-5" wire:key="aviso">{{ $aviso }}</x-aviso>
    @endif

    <div class="flex items-center justify-end gap-4">
        @can('gestionar-puestos')
            <x-boton wire:click="abrirAlta">Nuevo puesto</x-boton>
        @endcan
    </div>

    {{-- Alta / edición --}}
    @if ($creando)
        <form wire:submit="guardar" class="mt-5 rounded border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold">{{ $editando ? 'Editar puesto' : 'Nuevo puesto' }}</h2>

            <div class="mt-4 grid gap-4 sm:grid-cols-3">
                <x-campo etiqueta="Código" nombre="codigo" maxlength="20"
                         ayuda="Como «A-1», «S2-14», «12»."
                         wire:model="codigo" :error="$errors->first('codigo')" />

                <x-selector etiqueta="Tipo" nombre="tipo"
                            :opciones="$this->tipos"
                            wire:model="tipo" :error="$errors->first('tipo')" />

                <x-campo etiqueta="Zona" nombre="zona" ayuda="Opcional. «Sótano 1», «Frente»." maxlength="60"
                         wire:model="zona" :error="$errors->first('zona')" />
            </div>

            <div class="mt-5 flex items-center gap-3">
                <x-boton type="submit">Guardar</x-boton>
                <x-boton type="button" variante="secundario" wire:click="cancelar">Cancelar</x-boton>
            </div>
        </form>
    @endif

    {{-- El catálogo --}}
    <div class="mt-6 overflow-x-auto rounded border border-slate-200 bg-white shadow-sm">
        <table class="w-full min-w-[36rem] text-sm">
            <thead>
                <tr class="border-b border-slate-200 text-left font-mono text-xs uppercase tracking-widest text-slate-500">
                    <th class="px-4 py-3 font-semibold">Código</th>
                    <th class="px-4 py-3 font-semibold">Tipo</th>
                    <th class="px-4 py-3 font-semibold">Zona</th>
                    <th class="px-4 py-3 font-semibold">Estado</th>
                    <th class="px-4 py-3 font-semibold text-right">Acción</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($this->puestos as $puesto)
                    <tr wire:key="puesto-{{ $puesto->id }}" class="{{ $puesto->activo ? '' : 'opacity-60' }}">
                        <td class="px-4 py-3 font-mono font-semibold text-slate-900">{{ $puesto->codigo }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $puesto->etiquetaTipo() }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $puesto->zona ?: '—' }}</td>
                        <td class="px-4 py-3">
                            @if ($puesto->activo)
                                <span class="font-mono text-xs uppercase tracking-widest text-slate-500">Habilitado</span>
                            @else
                                <x-etiqueta tipo="inactivo" />
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            @can('gestionar-puestos')
                                <div class="flex items-center justify-end gap-3">
                                    <x-boton tamano="chico" variante="secundario"
                                             wire:click="editar({{ $puesto->id }})">Editar</x-boton>
                                    @if ($puesto->activo)
                                        <x-boton tamano="chico" variante="secundario"
                                                 wire:click="activar({{ $puesto->id }}, false)">Deshabilitar</x-boton>
                                    @else
                                        <x-boton tamano="chico" variante="secundario"
                                                 wire:click="activar({{ $puesto->id }}, true)">Habilitar</x-boton>
                                    @endif
                                    <x-boton tamano="chico" variante="peligro"
                                             wire:click="eliminar({{ $puesto->id }})"
                                             wire:confirm="¿Quitar el puesto {{ $puesto->codigo }} del catálogo?">Quitar</x-boton>
                                </div>
                            @else
                                <span class="text-slate-300">—</span>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <x-tabla-vacia :columnas="5">
                        No hay puestos en el catálogo.
                    </x-tabla-vacia>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
