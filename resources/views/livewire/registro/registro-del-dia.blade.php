@php
    // Todo lo que rehace la lista. Los estados de carga se acotan a esto para que no se
    // disparen con acciones que no la tocan, como abrir o cerrar el panel.
    $recalcula = 'fecha,tipo,ente,verHoy,previousPage,nextPage,gotoPage';
@endphp

<div>
    {{-- Rojo solo para esto: un dato que no se entiende.
         role=alert porque aparece a mitad de sesión, al cambiar la fecha. --}}
    @if ($this->fechaIlegible)
        <x-error class="mb-4">
            No se entiende la fecha «{{ $fecha }}». Se está mostrando el día de hoy.
        </x-error>
    @endif

    {{-- La cifra que gobierna la pantalla, y al lado el botón que produce el reporte. --}}
    <x-contador :numero="$this->dentro" :leyenda="$this->leyendaDelContador">
        {{-- El wire:target no es opcional: sin él, el botón se deshabilitaba en cada
             pulsación de cualquier filtro, no solo al exportar. --}}
        <x-boton
            variante="secundario"
            wire:click="exportar"
            wire:loading.attr="disabled"
            wire:target="exportar"
        >
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
                ayuda="Al menos dos caracteres."
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
                                title="{{ $persona->nombre() }}"
                                class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm hover:bg-slate-50"
                            >
                                {{-- min-w-0 deja que el nombre largo se trunque en vez de partirse
                                     en dos líneas y amontonarse con la etiqueta. --}}
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate font-medium">{{ $persona->nombre() }}</span>
                                    <span class="block truncate font-mono text-xs text-slate-500">{{ $persona->documento() }}</span>
                                </span>
                                <x-etiqueta :tipo="$persona->tipo->value" class="shrink-0" />
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

                {{-- El recuento cambia solo, al filtrar: se anuncia en vez de mutar en
                     silencio, y avisa de que hay una consulta en curso. --}}
                <p aria-live="polite" class="text-sm text-slate-500">
                    <span wire:loading.remove wire:target="{{ $recalcula }}">
                        {{ $this->movimientos->total() }}
                        {{ $this->movimientos->total() === 1 ? 'movimiento' : 'movimientos' }}
                    </span>
                    <span wire:loading wire:target="{{ $recalcula }}">Actualizando…</span>
                </p>
            </div>

            @if ($this->movimientos->isEmpty())
                <div class="mt-3 rounded border border-dashed border-slate-300 bg-white px-4 py-10 text-center">
                    <p class="text-sm text-slate-500">No hay movimientos con estos filtros.</p>
                </div>
            @else
                {{-- Misma estructura que la tabla de la base visual, para que las tres
                     partes se vean como un solo sistema. --}}
                {{-- El scroll interno con cabecera fija es SOLO de pantalla ancha (sm+): ahí la
                     altura acotada hace que el contenedor desplace en vertical y el `sticky` de la
                     cabecera se ancle a él. En el teléfono eso sería un scroll dentro de otro
                     scroll —confuso, y deja un hueco grande bajo la lista—, así que ahí la tabla
                     fluye con la página (solo desplaza en horizontal, para las columnas anchas) y
                     hay una sola barra de scroll: la de la página. --}}
                <div
                    class="mt-3 overflow-x-auto rounded border border-slate-200 bg-white shadow-sm transition-opacity sm:max-h-[70vh] sm:overflow-auto"
                    {{-- Los dos modificadores van en el MISMO atributo. Separados, el
                         `wire:loading.delay` suelto es una directiva por su cuenta, y una
                         wire:loading sin `.class` significa «muestra esto solo mientras
                         carga»: Livewire le ponía display:none a la tabla en reposo. --}}
                    wire:loading.delay.class="opacity-50"
                    wire:target="{{ $recalcula }}"
                >
                    {{-- table-fixed: sin esto un nombre largo empuja la tabla al scroll
                         horizontal y las columnas bailan entre página y página.
                         min-w: en un teléfono los anchos fijos no caben y la columna «Persona»
                         se aplastaba a cero —el ente se corría a su sitio y el encabezado se
                         encimaba—. Con un ancho mínimo, el contenedor desplaza en vez de aplastar. --}}
                    <table class="w-full min-w-[40rem] table-fixed text-sm">
                        <caption class="sr-only">
                            Movimientos del {{ $this->diaElegido()->format('d/m/Y') }}, del más reciente al más antiguo.
                        </caption>

                        {{-- La cabecera se fija solo donde hay scroll interno (sm+): en móvil el
                             contenedor no desplaza en vertical, así que fijarla la encimaría con la
                             cabecera azul de la página. --}}
                        <thead class="bg-white sm:sticky sm:top-0 sm:z-10">
                            <tr class="border-b border-slate-200 text-left font-mono text-xs uppercase tracking-widest text-slate-500">
                                <th scope="col" class="w-20 px-4 py-3 font-semibold">Hora</th>
                                <th scope="col" class="px-4 py-3 font-semibold">Persona</th>
                                <th scope="col" class="w-28 px-4 py-3 font-semibold">Ente</th>
                                <th scope="col" class="w-36 px-4 py-3 font-semibold">Tipo</th>
                                <th scope="col" class="w-32 px-4 py-3 font-semibold">Mov.</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-100">
                            @foreach ($this->movimientos as $movimiento)
                                <tr
                                    wire:key="{{ $movimiento->id }}"
                                    wire:click="abrirPanel('{{ $movimiento->persona->id }}')"
                                    class="cursor-pointer hover:bg-slate-50 has-[:focus-visible]:bg-slate-50"
                                >
                                    <td class="px-4 py-3 font-mono tabular-nums text-slate-500">{{ $movimiento->hora() }}</td>

                                    {{-- El botón no es decorativo: la fila entera responde al
                                         ratón, pero sin un control de verdad no había forma de
                                         llegar aquí con el teclado ni de anunciarlo. El .stop
                                         evita que la acción se dispare dos veces. --}}
                                    <td class="px-4 py-3">
                                        <button
                                            type="button"
                                            wire:click.stop="abrirPanel('{{ $movimiento->persona->id }}')"
                                            title="{{ $movimiento->persona->nombre() }}"
                                            class="block w-full truncate rounded text-left font-medium
                                                   focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-900
                                                   focus-visible:ring-offset-2"
                                        >
                                            <span class="sr-only">Ver el histórico de</span>
                                            {{ $movimiento->persona->nombre() }}
                                        </button>
                                    </td>

                                    <td class="truncate px-4 py-3 font-mono text-xs text-slate-500">
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
