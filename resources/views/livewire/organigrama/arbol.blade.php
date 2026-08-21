<div>
    @if ($aviso)
        <x-aviso class="mb-5" wire:key="aviso-org">{{ $aviso }}</x-aviso>
    @endif

    @error('general')
        <x-error class="mb-5">{{ $message }}</x-error>
    @enderror

    <div class="flex flex-wrap items-center justify-between gap-4">
        <p class="max-w-2xl text-sm text-slate-600">
            La estructura de unidades. Cuelga unas de otras para armar el árbol; la gente enlazada
            se agrupa por su unidad en reportes y filtros. No reescribe el texto de las fichas.
        </p>
        @can('gestionar-organigrama')
            <x-boton wire:click="abrirAlta">Nueva unidad</x-boton>
        @endcan
    </div>

    {{-- Alta / edición --}}
    @if ($creando)
        <form wire:submit="guardar" class="mt-5 rounded border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold">{{ $editando ? 'Editar unidad' : 'Nueva unidad' }}</h2>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <x-campo etiqueta="Nombre" nombre="nombre" maxlength="150"
                         ayuda="Como en las fichas: «GERENCIA DE LITIGIOS»."
                         wire:model="nombre" :error="$errors->first('nombre')" />

                <x-campo etiqueta="Código" nombre="codigo" maxlength="20"
                         ayuda="Opcional. Siglas, si la casa las usa: «GGA», «CJ»."
                         wire:model="codigo" :error="$errors->first('codigo')" />

                <x-selector etiqueta="Ente" nombre="ente" wire:model="ente"
                            :error="$errors->first('ente')"
                            :opciones="['' => '— Sin ente —'] + $this->entes" />

                <x-selector etiqueta="Cuelga de" nombre="parentId" wire:model="parentId"
                            :error="$errors->first('parent_id')"
                            :opciones="['' => '— Raíz (no cuelga de nadie) —'] + $this->madres" />
            </div>

            <div class="mt-5 flex items-center gap-3">
                <x-boton type="submit">Guardar</x-boton>
                <x-boton type="button" variante="secundario" wire:click="cancelar">Cancelar</x-boton>
            </div>
        </form>
    @endif

    {{-- El árbol --}}
    <div class="mt-6 overflow-x-auto rounded border border-slate-200 bg-white shadow-sm">
        <table class="w-full min-w-[40rem] text-sm">
            <thead>
                <tr class="border-b border-slate-200 text-left font-mono text-xs uppercase tracking-widest text-slate-500">
                    <th class="px-4 py-3 font-semibold">Unidad</th>
                    <th class="px-4 py-3 font-semibold">Ente</th>
                    <th class="px-4 py-3 font-semibold text-right">Personas</th>
                    <th class="px-4 py-3 font-semibold text-right">Acción</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($this->unidades as $unidad)
                    <tr wire:key="dep-{{ $unidad->id }}" class="{{ $unidad->activo ? '' : 'opacity-50' }}">
                        <td class="px-4 py-3">
                            <span style="padding-left: {{ ($unidad->_profundidad ?? 0) * 1.25 }}rem">
                                @if (($unidad->_profundidad ?? 0) > 0)
                                    <span class="text-slate-300" aria-hidden="true">└ </span>
                                @endif
                                <span class="font-medium text-slate-900">{{ $unidad->nombre }}</span>
                                @if ($unidad->codigo)
                                    <span class="ml-1 font-mono text-xs text-slate-400">{{ $unidad->codigo }}</span>
                                @endif
                                @unless ($unidad->activo)
                                    <x-etiqueta tipo="inactivo" tamano="chico" class="ml-1">Inactiva</x-etiqueta>
                                @endunless
                            </span>
                        </td>
                        <td class="px-4 py-3 text-slate-500">
                            {{ $unidad->ente ? (\App\Services\Organigrama\Organigrama::ENTES[$unidad->ente] ?? $unidad->ente) : '—' }}
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums text-slate-600">{{ $unidad->personas_count }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right">
                            @can('gestionar-organigrama')
                                <button wire:click="editar({{ $unidad->id }})"
                                        class="text-sm font-semibold text-parte3 hover:underline">Editar</button>
                                <button wire:click="activar({{ $unidad->id }}, {{ $unidad->activo ? 'false' : 'true' }})"
                                        class="ml-4 text-sm font-semibold text-slate-500 hover:underline">
                                    {{ $unidad->activo ? 'Desactivar' : 'Reactivar' }}
                                </button>
                                <button wire:click="eliminar({{ $unidad->id }})"
                                        class="ml-4 text-sm font-semibold text-alto hover:underline">Quitar</button>
                            @else
                                <span class="text-slate-300">—</span>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <x-tabla-vacia :columnas="4">
                        Todavía no hay unidades. Empieza con una nueva.
                    </x-tabla-vacia>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
