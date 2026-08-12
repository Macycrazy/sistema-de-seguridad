<div>
    {{-- Rojo solo para esto: un dato que no se entiende. --}}
    @if ($this->fechaIlegible)
        <p class="mb-4 rounded border border-alto bg-alto-suave px-4 py-3 text-sm text-alto">
            No se entiende la fecha «{{ $fecha }}». Se está mostrando el día de hoy.
        </p>
    @endif

    {{-- La cifra que gobierna la pantalla, y al lado el botón que produce el reporte. --}}
    <x-contador :numero="$this->dentro" :leyenda="$this->leyendaDelContador">
        <x-boton variante="secundario" wire:click="exportar" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="exportar">Exportar</span>
            <span wire:loading wire:target="exportar">Generando…</span>
        </x-boton>
    </x-contador>

    {{-- FILTROS --}}
    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-campo
            etiqueta="Fecha"
            nombre="fecha"
            type="date"
            wire:model.live="fecha"
        />

        <x-selector
            etiqueta="Tipo"
            nombre="tipo"
            wire:model.live="tipo"
            :opciones="['' => 'Todos', 'trabajador' => 'Solo trabajadores', 'invitado' => 'Solo invitados']"
        />

        {{-- Tres entes comparten el edificio, y el reporte del día suele pedirse por uno. --}}
        <x-selector
            etiqueta="Ente"
            nombre="ente"
            wire:model.live="ente"
            :opciones="['' => 'Todos', 'ciip' => 'CIIP', 'marca-pais' => 'Marca País', 'venapp' => 'VENAPP']"
        />

        <div class="relative">
            <x-campo
                etiqueta="Buscar persona"
                nombre="busqueda"
                type="search"
                autocomplete="off"
                placeholder="Cédula o nombre"
                wire:model.live.debounce.300ms="busqueda"
            />

            {{-- De a una cédula: se sugiere un puñado, nunca la lista completa. --}}
            @if ($this->sugerencias->isNotEmpty())
                <ul class="absolute z-10 mt-1 w-full overflow-hidden rounded border border-slate-300 bg-white shadow-lg">
                    @foreach ($this->sugerencias as $persona)
                        <li wire:key="sug-{{ $persona->id }}">
                            <button
                                type="button"
                                wire:click="abrirPanel('{{ $persona->id }}')"
                                class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm hover:bg-slate-50"
                            >
                                <span>
                                    <span class="font-medium">{{ $persona->nombre() }}</span>
                                    <span class="block font-mono text-xs text-slate-500">{{ $persona->documento() }}</span>
                                </span>
                                <x-etiqueta :tipo="$persona->tipo->value" />
                            </button>
                        </li>
                    @endforeach
                </ul>
            @elseif (strlen($busqueda) >= 2)
                <p class="absolute z-10 mt-1 w-full rounded border border-slate-300 bg-white px-3 py-2 text-sm text-slate-500 shadow-lg">
                    Nadie coincide con «{{ $busqueda }}».
                </p>
            @endif
        </div>
    </div>

    <div class="mt-6 grid gap-6 @if ($this->personaDelPanel) lg:grid-cols-[2fr_1fr] @endif">
        {{-- LISTA DEL DÍA --}}
        <section>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <h2 class="font-mono text-xs font-bold uppercase tracking-widest text-slate-500">
                        {{ $this->esHoy() ? 'Hoy' : $this->diaElegido()->format('d/m/Y') }}
                    </h2>

                    @unless ($this->esHoy())
                        <x-boton variante="secundario" tamano="chico" wire:click="verHoy">
                            Volver a hoy
                        </x-boton>
                    @endunless
                </div>

                <p class="text-sm text-slate-500">
                    {{ $this->movimientos->total() }}
                    {{ $this->movimientos->total() === 1 ? 'movimiento' : 'movimientos' }}
                </p>
            </div>

            @if ($this->movimientos->isEmpty())
                <div class="mt-3 rounded border border-dashed border-slate-300 bg-white px-4 py-10 text-center">
                    <p class="text-sm text-slate-500">No hay movimientos con estos filtros.</p>
                </div>
            @else
                {{-- Misma estructura que la tabla de la base visual, para que las tres
                     partes se vean como un solo sistema. --}}
                <div class="mt-3 overflow-x-auto rounded border border-slate-200 bg-white shadow-sm">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 text-left font-mono text-xs uppercase tracking-widest text-slate-500">
                                <th class="px-4 py-3 font-semibold">Hora</th>
                                <th class="px-4 py-3 font-semibold">Persona</th>
                                <th class="px-4 py-3 font-semibold">Ente</th>
                                <th class="px-4 py-3 font-semibold">Tipo</th>
                                <th class="px-4 py-3 font-semibold">Movimiento</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($this->movimientos as $movimiento)
                                <tr
                                    wire:key="{{ $movimiento->id }}"
                                    wire:click="abrirPanel('{{ $movimiento->persona->id }}')"
                                    class="cursor-pointer hover:bg-slate-50"
                                >
                                    <td class="px-4 py-3 font-mono text-slate-500">{{ $movimiento->hora() }}</td>
                                    <td class="px-4 py-3 font-medium">{{ $movimiento->persona->nombre() }}</td>
                                    <td class="px-4 py-3 font-mono text-xs text-slate-500">
                                        {{ $movimiento->persona->ente?->etiqueta() ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3"><x-etiqueta :tipo="$movimiento->persona->tipo->value" /></td>
                                    <td class="px-4 py-3"><x-etiqueta :tipo="$movimiento->sentido->value" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($this->movimientos->hasPages())
                    <div class="mt-4">
                        {{ $this->movimientos->links() }}
                    </div>
                @endif
            @endif
        </section>

        @if ($this->personaDelPanel)
            @include('livewire.registro._panel', ['persona' => $this->personaDelPanel])
        @endif
    </div>
</div>
