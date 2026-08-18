{{--
    El rastro. Solo se mira: aquí no hay ni un botón que escriba.

    Un rastro que se puede editar o borrar desde una pantalla no prueba nada, así que no existe la
    pantalla para hacerlo.
--}}
<div>

    <div class="mb-6">
        <h1 class="text-3xl font-bold tracking-tight">Auditoría</h1>
        <p class="mt-2 max-w-3xl text-sm text-slate-600">
            Quién hizo qué y cuándo. Se filtra por acción, por usuario y por fechas.
        </p>
    </div>

    {{-- LOS FILTROS --}}
    <x-tarjeta parte="3" class="mb-6">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-selector etiqueta="Acción" nombre="accion" wire:model.live="accion">
                <option value="">Todas</option>
                @foreach ($this->acciones as $valor => $texto)
                    <option value="{{ $valor }}">{{ $texto }}</option>
                @endforeach
            </x-selector>

            <x-selector etiqueta="Usuario" nombre="usuario" wire:model.live="usuario">
                <option value="">Todos</option>
                @foreach ($this->usuarios as $u)
                    <option value="{{ $u->id }}">{{ $u->nombre }}</option>
                @endforeach
            </x-selector>

            <x-campo etiqueta="Desde" nombre="desde" type="date" wire:model.live="desde" />
            <x-campo etiqueta="Hasta" nombre="hasta" type="date" wire:model.live="hasta" />
        </div>

        @if ($this->hayFiltros)
            <div class="mt-4">
                <x-boton variante="secundario" tamano="chico" wire:click="limpiar">
                    Quitar los filtros
                </x-boton>
            </div>
        @endif
    </x-tarjeta>

    {{-- LOS ASIENTOS --}}
    @if ($this->asientos->isEmpty())
        <div class="rounded border border-slate-200 bg-white px-4 py-10 text-center text-sm text-slate-500 shadow-sm">
            @if ($this->hayFiltros)
                No hay nada anotado que cumpla con esos filtros.
            @else
                Todavía no hay nada anotado.
            @endif
        </div>
    @else
        <div class="overflow-hidden rounded border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-left font-mono text-xs uppercase tracking-widest text-slate-500">
                            <th scope="col" class="w-40 px-4 py-3 font-semibold">Cuándo</th>
                            <th scope="col" class="w-48 px-4 py-3 font-semibold">Quién</th>
                            <th scope="col" class="w-56 px-4 py-3 font-semibold">Qué hizo</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Sobre qué</th>
                            <th scope="col" class="w-32 px-4 py-3 font-semibold">Desde</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100">
                        @foreach ($this->asientos as $asiento)
                            <tr wire:key="asiento-{{ $asiento->id }}">
                                <td class="px-4 py-3 font-mono tabular-nums text-slate-500">
                                    {{ $asiento->ocurrio_en->format('d/m/Y H:i:s') }}
                                </td>

                                <td class="px-4 py-3">
                                    <p class="text-slate-900">{{ $asiento->autor() }}</p>
                                    @if ($asiento->usuario)
                                        <p class="font-mono text-xs text-slate-500">{{ $asiento->usuario->usuario }}</p>
                                    @endif
                                </td>

                                <td class="px-4 py-3 text-slate-700">{{ $asiento->accion->etiqueta() }}</td>

                                <td class="px-4 py-3 text-slate-700">
                                    {{ $asiento->detalle ?? '—' }}
                                    @if ($asiento->persona)
                                        <span class="ml-1 text-slate-500">· {{ $asiento->persona->nombre }}</span>
                                    @endif
                                </td>

                                <td class="px-4 py-3 font-mono text-xs text-slate-500">
                                    {{ $asiento->ip ?? '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4">
            {{ $this->asientos->links() }}
        </div>
    @endif
</div>
