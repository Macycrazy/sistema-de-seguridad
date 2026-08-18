<div>
    <p class="max-w-2xl text-sm text-slate-600">
        Quién consultó, exportó o cambió qué, y cuándo. No incluye los marcajes de la puerta: cada
        movimiento ya guarda su vigilante.
    </p>

    {{-- Filtros --}}
    <div class="mt-5 grid gap-4 sm:grid-cols-3">
        <x-selector etiqueta="Acción" nombre="accion"
                    :opciones="array_merge(['' => 'Todas'], $this->acciones)"
                    wire:model.live="accion" />

        <x-selector etiqueta="Usuario" nombre="usuario"
                    :opciones="array_merge(['' => 'Todos'], $this->usuarios)"
                    wire:model.live="usuario" />

        <x-campo etiqueta="Fecha" nombre="fecha" type="date" wire:model.live="fecha" />
    </div>

    {{-- El rastro --}}
    <div class="mt-6 overflow-x-auto rounded border border-slate-200 bg-white shadow-sm">
        <table class="w-full min-w-[46rem] text-sm">
            <thead>
                <tr class="border-b border-slate-200 text-left font-mono text-xs uppercase tracking-widest text-slate-500">
                    <th class="px-4 py-3 font-semibold">Cuándo</th>
                    <th class="px-4 py-3 font-semibold">Quién</th>
                    <th class="px-4 py-3 font-semibold">Acción</th>
                    <th class="px-4 py-3 font-semibold">Sobre</th>
                    <th class="px-4 py-3 font-semibold">Detalle</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($this->entradas as $fila)
                    <tr wire:key="b-{{ $fila->id }}">
                        <td class="whitespace-nowrap px-4 py-3 font-mono tabular-nums text-slate-500">
                            {{ $fila->ocurrio_en->format('d/m/Y H:i') }}
                        </td>
                        <td class="px-4 py-3 font-medium">
                            {{ $fila->usuario?->nombreCorto() ?? 'Sistema' }}
                        </td>
                        <td class="px-4 py-3">
                            {{ $this->acciones[$fila->accion] ?? $fila->accion }}
                        </td>
                        <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ $fila->sobre ?: '—' }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $fila->detalle ?: '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-sm text-slate-500">
                            No hay nada en la bitácora con estos filtros.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $this->entradas->links() }}
    </div>
</div>
