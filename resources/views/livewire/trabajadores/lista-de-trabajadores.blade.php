<div>
    {{-- Aviso de lo último que se hizo. --}}
    @if ($aviso)
        <x-aviso class="mb-5" wire:key="aviso">{{ $aviso }}</x-aviso>
    @endif

    {{-- Barra: buscar · importar · nuevo --}}
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="w-full max-w-xs">
            <x-campo
                etiqueta="Buscar"
                nombre="busqueda"
                type="search"
                placeholder="Cédula o nombre"
                wire:model.live.debounce.300ms="busqueda"
            />
        </div>

        <div class="flex flex-wrap items-center gap-3">
            {{-- La plantilla en blanco, para que la carga masiva salga normalizada. --}}
            <button type="button" wire:click="descargarPlantilla"
                    class="text-sm font-semibold text-parte3 hover:underline">
                Descargar plantilla
            </button>

            {{-- Importar: subir el Excel y cargar en bloque. --}}
            <form wire:submit="importar" class="flex items-center gap-2">
                <label class="cursor-pointer rounded border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    <span>{{ $archivo ? $archivo->getClientOriginalName() : 'Elegir Excel…' }}</span>
                    <input type="file" wire:model="archivo" class="sr-only" accept=".xlsx,.xls,.csv">
                </label>
                <x-boton type="submit" variante="secundario" wire:loading.attr="disabled" wire:target="archivo,importar">
                    <span wire:loading.remove wire:target="archivo,importar">Importar</span>
                    <span wire:loading wire:target="archivo,importar">Cargando…</span>
                </x-boton>
            </form>

            <x-boton wire:click="abrirAlta">Nuevo trabajador</x-boton>
        </div>
    </div>
    @error('archivo') <p class="mt-2 text-sm text-alto">{{ $message }}</p> @enderror

    {{-- Errores de la última importación, fila por fila. --}}
    @if ($erroresDeImportacion)
        <div class="mt-4 rounded border border-alto/30 bg-alto-suave px-4 py-3">
            <p class="text-sm font-semibold text-alto">Filas que no se cargaron:</p>
            <ul class="mt-2 space-y-1 text-sm text-slate-700">
                @foreach ($erroresDeImportacion as $fila => $error)
                    <li><span class="font-mono font-semibold">Fila {{ $fila }}:</span> {{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Alta manual --}}
    @if ($creando)
        <form wire:submit="guardar" class="mt-6 rounded border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold">Nuevo trabajador</h2>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <x-campo etiqueta="Cédula" nombre="cedula" inputmode="numeric" maxlength="9"
                         oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                         wire:model="cedula" :error="$errors->first('cedula')" />

                <x-campo etiqueta="Nombre y apellido" nombre="nombre" maxlength="120"
                         wire:model="nombre" :error="$errors->first('nombre')" />

                <x-selector etiqueta="Ente" nombre="ente"
                            :opciones="array_merge(['' => 'Sin asignar'], $this->entes)"
                            wire:model="ente" :error="$errors->first('ente')" />

                <x-campo etiqueta="Dependencia" nombre="dependencia" ayuda="Opcional." maxlength="120"
                         wire:model="dependencia" :error="$errors->first('dependencia')" />

                <x-campo etiqueta="Piso" nombre="piso" ayuda="Opcional." maxlength="10"
                         wire:model="piso" :error="$errors->first('piso')" />
            </div>

            <div class="mt-5 flex items-center gap-3">
                <x-boton type="submit">Guardar</x-boton>
                <x-boton type="button" variante="secundario" wire:click="cancelarAlta">Cancelar</x-boton>
            </div>
        </form>
    @endif

    {{-- La lista --}}
    <div class="mt-6 overflow-x-auto rounded border border-slate-200 bg-white shadow-sm">
        <table class="w-full min-w-[44rem] text-sm">
            <thead>
                <tr class="border-b border-slate-200 text-left font-mono text-xs uppercase tracking-widest text-slate-500">
                    <th class="px-4 py-3 font-semibold">Cédula</th>
                    <th class="px-4 py-3 font-semibold">Nombre</th>
                    <th class="px-4 py-3 font-semibold">Ente</th>
                    <th class="px-4 py-3 font-semibold">Dependencia</th>
                    <th class="px-4 py-3 font-semibold">Estado</th>
                    <th class="px-4 py-3 font-semibold text-right">Acción</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($this->trabajadores as $t)
                    <tr wire:key="t-{{ $t->id }}" class="{{ $t->activo ? '' : 'opacity-60' }}">
                        <td class="px-4 py-3 font-mono tabular-nums text-slate-500">{{ $t->cedulaConPuntos() }}</td>
                        <td class="px-4 py-3 font-medium">{{ $t->nombre }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ \App\Services\GestionDeTrabajadores::ENTES[$t->ente] ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $t->dependencia ?: '—' }}</td>
                        <td class="px-4 py-3">
                            @if ($t->activo)
                                <x-etiqueta tipo="trabajador">Activo</x-etiqueta>
                            @else
                                <x-etiqueta tipo="inactivo">Inactivo</x-etiqueta>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            @if ($t->activo)
                                <button wire:click="desactivar({{ $t->id }})"
                                        class="text-sm font-semibold text-alto hover:underline">Desactivar</button>
                            @else
                                <button wire:click="reactivar({{ $t->id }})"
                                        class="text-sm font-semibold text-parte3 hover:underline">Reactivar</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <x-tabla-vacia :columnas="6">
                        {{ trim($busqueda) === '' ? 'Todavía no hay trabajadores cargados.' : 'Nadie coincide con «'.$busqueda.'».' }}
                    </x-tabla-vacia>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $this->trabajadores->links() }}
    </div>
</div>
