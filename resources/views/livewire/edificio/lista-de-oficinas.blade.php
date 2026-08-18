<div>
    @if ($aviso)
        <x-aviso class="mb-5" wire:key="aviso">{{ $aviso }}</x-aviso>
    @endif

    <div class="flex items-center justify-end gap-4">
        <x-boton wire:click="abrirAlta">Nueva oficina</x-boton>
    </div>

    {{-- Alta / edición --}}
    @if ($creando)
        <form wire:submit="guardar" class="mt-5 rounded border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold">{{ $editando ? 'Editar oficina' : 'Nueva oficina' }}</h2>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <x-campo etiqueta="Código" nombre="codigo" maxlength="20"
                         ayuda="Como en las fichas: «2-1», «LOBBY», «7»."
                         wire:model="codigo" :error="$errors->first('codigo')" />

                <x-campo etiqueta="Nombre" nombre="nombre" maxlength="60"
                         ayuda="Opcional. Solo se usa si no labora nadie ahí."
                         wire:model="nombre" :error="$errors->first('nombre')" />
            </div>

            <div class="mt-5 flex items-center gap-3">
                <x-boton type="submit">Guardar</x-boton>
                <x-boton type="button" variante="secundario" wire:click="cancelar">Cancelar</x-boton>
            </div>
        </form>
    @endif

    {{-- El catálogo --}}
    <div class="mt-6 overflow-x-auto rounded border border-slate-200 bg-white shadow-sm">
        <table class="w-full min-w-[32rem] text-sm">
            <thead>
                <tr class="border-b border-slate-200 text-left font-mono text-xs uppercase tracking-widest text-slate-500">
                    <th class="px-4 py-3 font-semibold">Código</th>
                    <th class="px-4 py-3 font-semibold">Nombre</th>
                    <th class="px-4 py-3 font-semibold text-right">Acción</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($this->oficinas as $oficina)
                    <tr wire:key="of-{{ $oficina->id }}">
                        <td class="px-4 py-3 font-mono font-semibold text-slate-900">{{ $oficina->codigo }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $oficina->nombre ?: '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            <button wire:click="editar({{ $oficina->id }})"
                                    class="text-sm font-semibold text-parte3 hover:underline">Editar</button>
                            <button wire:click="eliminar({{ $oficina->id }})"
                                    class="ml-4 text-sm font-semibold text-alto hover:underline">Quitar</button>
                        </td>
                    </tr>
                @empty
                    <x-tabla-vacia :columnas="3">
                        No hay oficinas en el catálogo.
                    </x-tabla-vacia>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
