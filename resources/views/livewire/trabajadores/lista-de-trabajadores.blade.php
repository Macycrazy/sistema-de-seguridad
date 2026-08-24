<div>
    {{-- Aviso de lo último que se hizo. --}}
    @if ($aviso)
        <x-aviso class="mb-5" wire:key="aviso">{{ $aviso }}</x-aviso>
    @endif

    {{-- Personal de nómina o visitas: una sola pantalla, dos vistas. --}}
    <div class="mb-5 inline-flex rounded-lg border border-slate-200 bg-slate-50 p-1">
        @foreach ([\App\Models\Persona::TRABAJADOR => 'Trabajadores', \App\Models\Persona::INVITADO => 'Visitantes'] as $valor => $rotulo)
            <button type="button" wire:click="$set('filtro', '{{ $valor }}')"
                    class="rounded-md px-4 py-1.5 text-sm font-semibold transition
                           {{ $filtro === $valor ? 'bg-white text-parte3 shadow-sm' : 'text-slate-500 hover:text-slate-700' }}">
                {{ $rotulo }}
            </button>
        @endforeach
    </div>

    {{-- Barra: buscar · (solo trabajadores) importar · nuevo --}}
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

        {{-- Cargar en bloque y dar de alta son cosas de nómina: los invitados nacen en la puerta.
             Y son de quien gestiona el personal: quien solo puede ver, no las ve. --}}
        @if (! $this->verInvitados() && auth()->user()->can('gestionar-personal'))
            <div class="flex flex-wrap items-center gap-3">
                {{-- La plantilla en blanco, para que la carga masiva salga normalizada. --}}
                <button type="button" wire:click="descargarPlantilla"
                        class="text-sm font-semibold text-parte3 hover:underline">
                    Descargar plantilla
                </button>

                {{-- Comparar con el carnets. Se pulsa: es una llamada por la red y esta pantalla
                     se abre muchas veces al día para buscar a alguien, no para cotejar. --}}
                <button type="button" wire:click="cotejarConCarnets"
                        class="text-sm font-semibold text-parte3 hover:underline">
                    <span wire:loading.remove wire:target="cotejarConCarnets">Comparar con carnets</span>
                    <span wire:loading wire:target="cotejarConCarnets">Preguntando…</span>
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
        @endif
    </div>
    @error('archivo') <p class="mt-2 text-sm text-alto">{{ $message }}</p> @enderror

    {{-- Filtros: gerencia y ente (solo nómina) y estado. Se afinan en vivo. --}}
    {{-- QUÉ NO CUADRA CON EL CARNETS.

         Las dos listas de personal se llevan por separado y se separan solas: entra alguien, lo
         dan de alta allá, aquí nadie lo carga, y el día que llega se planta en la puerta y no
         aparece. Esto lo saca antes de que pase. --}}
    @if ($cotejo && $cotejo['disponible'])
        @php
            $faltan = $cotejo['faltan'];
            $sobran = $cotejo['sobran'];
            $sinEnte = $cotejo['sinEnte'];
            $desactivados = $cotejo['desactivados'];
        @endphp

        <div class="mt-4 rounded border border-slate-200 bg-white p-4 shadow-sm">
            <p class="font-mono text-xs font-bold uppercase tracking-widest text-slate-500">
                Comparado con carnets
            </p>
            <p class="mt-1 text-sm text-slate-600">
                En carnets <b class="tabular-nums">{{ $cotejo['enCarnets'] }}</b> activos ·
                aquí <b class="tabular-nums">{{ $cotejo['aqui'] }}</b> ·
                coinciden <b class="tabular-nums">{{ $cotejo['coinciden'] }}</b>
            </p>

            {{-- El carnets es solo del CIIP: de Marca País y VENAPP no está nadie allá, y eso es
                 lo normal. Decirlo evita que el número de «coinciden» parezca malo. --}}
            @if ($cotejo['otrosEntes'] > 0)
                <p class="mt-1 text-xs text-slate-500">
                    {{ $cotejo['otrosEntes'] }} de Marca País y VENAPP quedan fuera de la comparación:
                    el sistema de carnets es solo del CIIP.
                </p>
            @endif

            @if ($faltan->isNotEmpty())
                <div class="mt-4">
                    <p class="font-semibold text-slate-900">
                        {{ $faltan->count() }} en carnets y no aquí
                    </p>
                    <p class="mt-0.5 text-xs text-slate-500">
                        Se plantarán en la puerta y no aparecerán. «Cargar» los da de alta con lo que dice el carnets.
                    </p>

                    <ul class="mt-2 divide-y divide-slate-100 text-sm">
                        @foreach ($faltan as $ficha)
                            <li class="flex flex-wrap items-center justify-between gap-2 py-2" wire:key="falta-{{ $ficha['cedula'] }}">
                                <span class="min-w-0">
                                    <span class="block truncate font-medium text-slate-800">{{ $ficha['nombre'] }}</span>
                                    <span class="font-mono text-xs text-slate-500">
                                        {{ $ficha['cedula'] }}@if ($ficha['gerencia']) · {{ $ficha['gerencia'] }} @endif
                                    </span>
                                </span>

                                @can('gestionar-personal')
                                    <button type="button" wire:click="cargarDelPadron('{{ $ficha['cedula'] }}')"
                                            class="shrink-0 text-sm font-semibold text-parte3 hover:underline">Cargar</button>
                                @endcan
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Están aquí pero desactivados, y en carnets siguen activos. NO se cargan: su
                 ficha existe con su histórico, y crearla otra vez encima pisaría lo que tenga. --}}
            @if ($desactivados->isNotEmpty())
                <div class="mt-4 border-t border-slate-100 pt-3">
                    <p class="font-semibold text-slate-900">
                        {{ $desactivados->count() }} desactivados aquí, activos en carnets
                    </p>
                    <p class="mt-0.5 text-xs text-slate-500">
                        Tampoco pueden marcar. Se reactivan, no se vuelven a cargar: su ficha y su histórico ya están.
                    </p>

                    <ul class="mt-2 divide-y divide-slate-100 text-sm">
                        @foreach ($desactivados as $persona)
                            <li class="flex flex-wrap items-center justify-between gap-2 py-2" wire:key="desact-{{ $persona->id }}">
                                <span class="min-w-0">
                                    <span class="block truncate font-medium text-slate-800">{{ $persona->nombre }}</span>
                                    <span class="font-mono text-xs text-slate-500">{{ $persona->cedula }}</span>
                                </span>

                                @can('gestionar-personal')
                                    <button type="button" wire:click="reactivarDelPadron('{{ $persona->cedula }}')"
                                            class="shrink-0 text-sm font-semibold text-parte3 hover:underline">Reactivar</button>
                                @endcan
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Sin ente no se puede juzgar: podrían ser del CIIP y faltarles el carnet, o de
                 otro ente y estar bien. Se dicen para que alguien les ponga el ente. --}}
            @if ($sinEnte->isNotEmpty())
                <div class="mt-4 border-t border-slate-100 pt-3">
                    <p class="font-semibold text-slate-900">
                        {{ $sinEnte->count() }} sin ente asignado, y sin carnet
                    </p>
                    <p class="mt-0.5 text-xs text-slate-500">
                        No se puede saber si les falta el carnet o es que no son del CIIP. Ponles el ente y vuelve a comparar.
                    </p>
                    <p class="mt-2 text-sm text-slate-600">
                        {{ $sinEnte->take(15)->pluck('nombre')->implode(' · ') }}@if ($sinEnte->count() > 15) … y {{ $sinEnte->count() - 15 }} más @endif
                    </p>
                </div>
            @endif

            @if ($sobran->isNotEmpty())
                <div class="mt-4 border-t border-slate-100 pt-3">
                    <p class="font-semibold text-slate-900">
                        {{ $sobran->count() }} del CIIP activos aquí y ya no en carnets
                    </p>
                    <p class="mt-0.5 text-xs text-slate-500">
                        Puede que se hayan ido. Desactivarlos conserva su histórico; borrarlos, no.
                    </p>

                    <p class="mt-2 text-sm text-slate-600">
                        {{ $sobran->take(15)->pluck('nombre')->implode(' · ') }}@if ($sobran->count() > 15) … y {{ $sobran->count() - 15 }} más @endif
                    </p>
                </div>
            @endif

            @if ($faltan->isEmpty() && $sobran->isEmpty() && $sinEnte->isEmpty() && $desactivados->isEmpty())
                <p class="mt-3 text-sm font-medium text-parte1">Las dos listas dicen lo mismo.</p>
            @endif
        </div>
    @endif

    <div class="mt-4 flex flex-wrap items-end gap-3">
        @unless ($this->verInvitados())
            <div class="w-56">
                <x-selector etiqueta="Gerencia" nombre="filtroGerencia"
                            :opciones="array_merge(['' => 'Todas'], array_combine($this->gerencias, $this->gerencias))"
                            wire:model.live="filtroGerencia" />
            </div>
            <div class="w-44">
                <x-selector etiqueta="Ente" nombre="filtroEnte"
                            :opciones="array_merge(['' => 'Todos'], $this->entes)"
                            wire:model.live="filtroEnte" />
            </div>
        @endunless
        <div class="w-40">
            <x-selector etiqueta="Estado" nombre="filtroEstado"
                        :opciones="['' => 'Todos', 'activo' => 'Activos', 'inactivo' => 'Inactivos']"
                        wire:model.live="filtroEstado" />
        </div>
        @if ($busqueda !== '' || $filtroEnte !== '' || $filtroGerencia !== '' || $filtroEstado !== '')
            <button type="button" wire:click="limpiarFiltros"
                    class="pb-2.5 text-sm font-semibold text-slate-500 hover:text-slate-700 hover:underline">
                Limpiar filtros
            </button>
        @endif
    </div>

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

    {{-- Formulario: alta de trabajador, o corrección de trabajador/invitado. --}}
    @if ($creando)
        @php $editando = $editandoId !== null; @endphp
        <form wire:submit="guardar" class="mt-6 rounded border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold">
                @if ($this->verInvitados()) Editar visitante
                @elseif ($editando) Editar trabajador
                @else Nuevo trabajador @endif
            </h2>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                {{-- La cédula es la identidad: al editar se muestra pero no se cambia. --}}
                <x-campo etiqueta="Cédula" nombre="cedula" inputmode="numeric" maxlength="9"
                         oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                         :readonly="$editando"
                         :ayuda="$editando ? 'No se puede cambiar.' : null"
                         wire:model="cedula" :error="$errors->first('cedula')" />

                <x-campo etiqueta="Nombre y apellido" nombre="nombre" maxlength="120"
                         wire:model="nombre" :error="$errors->first('nombre')" />

                <x-selector etiqueta="Nacionalidad" nombre="nacionalidad"
                            :opciones="\App\Models\Persona::NACIONALIDADES"
                            wire:model="nacionalidad" :error="$errors->first('nacionalidad')" />

                @if ($this->verInvitados())
                    <x-campo etiqueta="Motivo de la visita" nombre="motivo" maxlength="255"
                             wire:model="motivo" :error="$errors->first('motivo')" />

                    <x-campo etiqueta="Piso al que va" nombre="piso" ayuda="Opcional." maxlength="10"
                             wire:model="piso" :error="$errors->first('piso')" />
                @else
                    <x-selector etiqueta="Ente" nombre="ente"
                                :opciones="array_merge(['' => 'Sin asignar'], $this->entes)"
                                wire:model="ente" :error="$errors->first('ente')" />

                    {{-- En vivo: al cambiar la gerencia se refrescan los pisos que se ofrecen abajo. --}}
                    <x-campo etiqueta="Gerencia" nombre="dependencia" ayuda="Opcional." maxlength="120"
                             list="gerencias-conocidas"
                             wire:model.live.debounce.400ms="dependencia" :error="$errors->first('dependencia')" />
                    <datalist id="gerencias-conocidas">
                        @foreach ($this->gerencias as $g)
                            <option value="{{ $g }}"></option>
                        @endforeach
                    </datalist>

                    {{-- El piso ofrece los de la gerencia elegida (del catálogo del edificio), pero
                         se puede escribir otro: es una sugerencia, no una jaula. --}}
                    <x-campo etiqueta="Piso" nombre="piso" maxlength="10" list="pisos-de-gerencia"
                             :ayuda="$this->pisosDeLaGerencia ? 'Pisos de esa gerencia; puedes escribir otro.' : 'Opcional.'"
                             wire:model="piso" :error="$errors->first('piso')" />
                    <datalist id="pisos-de-gerencia">
                        @foreach ($this->pisosDeLaGerencia as $p)
                            <option value="{{ $p['codigo'] }}">{{ $p['nombre'] ? $p['codigo'].' · '.$p['nombre'] : $p['codigo'] }}</option>
                        @endforeach
                    </datalist>
                @endif
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
                    @if ($this->verInvitados())
                        <th class="px-4 py-3 font-semibold">Motivo</th>
                        <th class="px-4 py-3 font-semibold">Piso</th>
                    @else
                        <th class="px-4 py-3 font-semibold">Ente</th>
                        <th class="px-4 py-3 font-semibold">Gerencia</th>
                        <th class="px-4 py-3 font-semibold">Piso</th>
                    @endif
                    <th class="px-4 py-3 font-semibold">Estado</th>
                    <th class="px-4 py-3 font-semibold text-right">Acción</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($this->personas as $p)
                    <tr wire:key="p-{{ $p->id }}" class="{{ $p->activo ? '' : 'opacity-60' }}">
                        <td class="px-4 py-3 font-mono tabular-nums text-slate-500">{{ $p->cedulaConPuntos() }}</td>
                        <td class="px-4 py-3 font-medium">{{ $p->nombre }}</td>
                        @if ($this->verInvitados())
                            <td class="px-4 py-3 text-slate-500">{{ $p->motivo ?: '—' }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ $p->piso ?: '—' }}</td>
                        @else
                            <td class="px-4 py-3 text-slate-500">{{ \App\Services\GestionDeTrabajadores::ENTES[$p->ente] ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ $p->dependencia ?: '—' }}</td>
                            <td class="px-4 py-3 font-mono text-slate-500">{{ $p->piso ?: '—' }}</td>
                        @endif
                        <td class="px-4 py-3">
                            @if ($p->activo)
                                <x-etiqueta tipo="trabajador">Activo</x-etiqueta>
                            @else
                                <x-etiqueta tipo="inactivo">Inactivo</x-etiqueta>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            @can('gestionar-personal')
                                <div class="flex items-center justify-end gap-3">
                                    <button wire:click="editar({{ $p->id }})"
                                            class="text-sm font-semibold text-parte3 hover:underline">Editar</button>
                                    @if ($p->activo)
                                        <button wire:click="desactivar({{ $p->id }})"
                                                class="text-sm font-semibold text-alto hover:underline">Desactivar</button>
                                    @else
                                        <button wire:click="reactivar({{ $p->id }})"
                                                class="text-sm font-semibold text-parte3 hover:underline">Reactivar</button>
                                    @endif
                                </div>
                            @else
                                <span class="text-slate-300">—</span>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <x-tabla-vacia :columnas="$this->verInvitados() ? 6 : 7">
                        @if (trim($busqueda) !== '')
                            Nadie coincide con «{{ $busqueda }}».
                        @elseif ($this->verInvitados())
                            Todavía no hay visitantes registrados.
                        @else
                            Todavía no hay trabajadores cargados.
                        @endif
                    </x-tabla-vacia>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $this->personas->links() }}
    </div>
</div>
