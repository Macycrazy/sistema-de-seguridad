@php
    use App\Services\Estacionamiento\Estacionamiento;
    $est = app(Estacionamiento::class);
    $r = $this->resumen;
    $vehiculos = $this->vehiculos;
    $total = $r['total'];
@endphp

<div wire:loading.class="opacity-60" class="transition-opacity">
    @if ($aviso !== '')
        <x-aviso class="mb-4" wire:key="aviso">{{ $aviso }}</x-aviso>
    @endif

    {{-- El contador que gobierna la pantalla: el total dentro, contra el aforo si está puesto. --}}
    <div class="flex flex-wrap items-center justify-between gap-4 rounded border-2 bg-white px-5 py-4
                {{ $total['lleno'] ? 'border-alto' : 'border-parte1' }}">
        <p class="flex items-baseline gap-3">
            <span class="text-4xl font-bold tabular-nums tracking-tight text-slate-900">{{ $total['dentro'] }}</span>
            <span class="font-mono text-xs font-semibold uppercase tracking-widest {{ $total['lleno'] ? 'text-alto' : 'text-parte1' }}">
                @if ($total['aforo'] > 0)
                    de {{ $total['aforo'] }} · {{ $total['lleno'] ? 'lleno' : 'quedan '.$total['libres'] }}
                @else
                    vehículos dentro
                @endif
            </span>
        </p>

        <x-boton variante="secundario" wire:click="actualizar" wire:loading.attr="disabled" wire:target="actualizar">
            <span wire:loading.remove wire:target="actualizar">Actualizar</span>
            <span wire:loading wire:target="actualizar">Mirando…</span>
        </x-boton>
    </div>

    {{-- Carros y motos, que no estacionan en el mismo sitio: cada uno con su cupo y sus libres. --}}
    <div class="mt-4 grid grid-cols-2 gap-4">
        @foreach (['carro' => 'Carros', 'moto' => 'Motos'] as $clave => $rotulo)
            @php $c = $r[$clave]; @endphp
            <x-tarjeta class="{{ $c['lleno'] ? 'border-t-4 border-t-alto' : '' }}">
                <p class="text-3xl font-bold tabular-nums text-slate-900">{{ $c['dentro'] }}</p>
                <p class="mt-1 font-mono text-xs uppercase tracking-widest text-slate-500">{{ $rotulo }}</p>
                @if ($c['aforo'] > 0)
                    <p class="mt-2 text-xs font-semibold {{ $c['lleno'] ? 'text-alto' : 'text-slate-500' }}">
                        {{ $c['lleno'] ? 'Lleno · '.$c['dentro'].'/'.$c['aforo'] : 'Quedan '.$c['libres'].' de '.$c['aforo'] }}
                    </p>
                @endif
            </x-tarjeta>
        @endforeach
    </div>

    {{-- Buscar por placa: «¿está el carro ABC123?», «¿de quién es este que estorba?». --}}
    <div class="mt-4">
        <x-campo type="search" nombre="busqueda" autocomplete="off"
                 placeholder="Buscar por placa…" wire:model.live.debounce.300ms="busqueda" />
    </div>

    {{-- Los que pernoctan: siguen dentro y entraron antes de hoy. Solo aparece si hay alguno. --}}
    @if ($this->pernoctan->isNotEmpty())
        <div class="mt-4 overflow-hidden rounded border-l-4 border-parte1 bg-parte1-suave/40">
            <div class="flex items-baseline justify-between gap-3 px-4 py-3">
                <p class="font-mono text-xs font-bold uppercase tracking-widest text-parte1">
                    Pernoctan · {{ $this->pernoctan->count() }}
                </p>
                <p class="font-mono text-xs text-slate-500">se quedaron de noche</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[40rem] text-sm">
                    <thead>
                        <tr class="border-y border-parte1/20 text-left font-mono text-xs uppercase tracking-widest text-slate-500">
                            <th class="px-4 py-2 font-semibold">Placa</th>
                            <th class="px-4 py-2 font-semibold">Puesto</th>
                            <th class="px-4 py-2 font-semibold">Vehículo</th>
                            <th class="px-4 py-2 font-semibold">Dueño</th>
                            <th class="px-4 py-2 font-semibold text-right">Desde</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-parte1/10">
                        @foreach ($this->pernoctan as $p)
                            <tr wire:key="pernocta-{{ $p->persona_id }}">
                                <td class="px-4 py-2 font-mono text-base font-bold tracking-wider text-slate-900">{{ $p->placa ?: '—' }}</td>
                                <td class="px-4 py-2 font-mono font-semibold text-slate-700">{{ $p->puesto ?: '—' }}</td>
                                <td class="px-4 py-2 text-slate-600"><x-etiqueta :tipo="$p->tipo_vehiculo" tamano="chico" /> <span class="ml-1">{{ trim(($p->marca ?? '').' '.($p->modelo ?? '')) ?: '—' }}</span></td>
                                <td class="px-4 py-2 text-slate-600">{{ $p->nombre }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-right font-mono text-xs text-slate-500">{{ $est->desde($p->ocurrio_en)->translatedFormat('D j M · g:i a') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- La lista de lo que hay dentro (o lo que coincide con la placa buscada). --}}
    <div class="mt-3 overflow-x-auto rounded border border-slate-200 bg-white shadow-sm">
        <table class="w-full min-w-[44rem] text-sm">
            <thead>
                <tr class="border-b border-slate-200 text-left font-mono text-xs uppercase tracking-widest text-slate-500">
                    <th class="px-4 py-3 font-semibold">Placa</th>
                    <th class="px-4 py-3 font-semibold">Vehículo</th>
                    <th class="px-4 py-3 font-semibold">Puesto</th>
                    <th class="px-4 py-3 font-semibold">Dueño</th>
                    <th class="px-4 py-3 font-semibold text-right">Lleva</th>
                    <th class="px-4 py-3 font-semibold text-right">Entró</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($vehiculos as $v)
                    <tr wire:key="veh-{{ $v->persona_id }}">
                        <td class="px-4 py-3 font-mono text-base font-bold tracking-wider text-slate-900">{{ $v->placa ?: '—' }}</td>
                        <td class="px-4 py-3 text-slate-600">
                            <x-etiqueta :tipo="$v->tipo_vehiculo" tamano="chico" />
                            <span class="ml-1">{{ trim(($v->marca ?? '').' '.($v->modelo ?? '')) ?: '—' }}</span>
                            @if ($v->color)<span class="text-slate-400"> · {{ $v->color }}</span>@endif
                        </td>
                        <td class="px-4 py-3">
                            @if ($this->hayPuestos)
                                {{-- Lo pone quien está en el estacionamiento, que ve dónde quedó. --}}
                                <select wire:change="asignarPuesto({{ $v->persona_id }}, $event.target.value)"
                                        class="rounded border border-slate-300 bg-white px-2 py-1 font-mono text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-parte1/30">
                                    @foreach (($this->opcionesPorVehiculo[$v->persona_id] ?? []) as $valor => $texto)
                                        <option value="{{ $valor }}" @selected((string) $valor === (string) $v->puesto_id)>{{ $texto }}</option>
                                    @endforeach
                                </select>
                            @else
                                <span class="font-mono font-semibold text-slate-700">{{ $v->puesto ?: '—' }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-600">
                            {{ $v->nombre }}
                            @if ($v->cedula)<span class="ml-1 font-mono text-xs text-slate-400">{{ $v->cedula }}</span>@endif
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-right font-mono text-xs font-semibold text-slate-700">
                            {{ $est->tiempoDentro($v->ocurrio_en) }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-right font-mono text-xs text-slate-500">
                            {{ $est->desde($v->ocurrio_en)->translatedFormat('g:i a') }}
                        </td>
                    </tr>
                @empty
                    <x-tabla-vacia :columnas="6">
                        {{ trim($busqueda) === '' ? 'No hay vehículos dentro ahora mismo.' : 'Ninguna placa dentro coincide con «'.$busqueda.'».' }}
                    </x-tabla-vacia>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Vehículos fijos: los de la empresa o los que ya estaban y se quedan. Ocupan un puesto sin
         pasar por el marcaje de una persona. Solo si hay puestos en el catálogo. --}}
    @if ($this->hayPuestos)
        <div class="mt-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="font-mono text-xs font-bold uppercase tracking-widest text-slate-500">
                    Vehículos fijos @if ($this->fijos->isNotEmpty()) · {{ $this->fijos->count() }} @endif
                </p>
                @unless ($agregandoFijo)
                    <x-boton variante="secundario" wire:click="abrirFijo">Anotar vehículo fijo</x-boton>
                @endunless
            </div>

            @if ($agregandoFijo)
                @php
                    $opFijo = ['' => 'Elegir puesto…'];
                    foreach ($this->puestosLibresFijo as $p) {
                        $opFijo[$p->id] = $p->codigo.($p->zona ? ' · '.$p->zona : '').' ('.$p->etiquetaTipo().')';
                    }
                @endphp
                <form wire:submit="agregarFijo" class="mt-3 rounded border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <x-campo etiqueta="Placa" nombre="placaFija" maxlength="15" wire:model="placaFija" :error="$errors->first('placaFija')" />
                        <x-selector etiqueta="Tipo" nombre="tipoFija" :opciones="['carro' => 'Carro', 'moto' => 'Moto']" wire:model.live="tipoFija" />
                        <x-selector etiqueta="Puesto" nombre="puestoFijo" :opciones="$opFijo" wire:model="puestoFijo" :error="$errors->first('puestoFijo')" />
                        <x-campo etiqueta="Marca" nombre="marcaFija" ayuda="Opcional." maxlength="40" wire:model="marcaFija" />
                        <x-campo etiqueta="Color" nombre="colorFija" ayuda="Opcional." maxlength="30" wire:model="colorFija" />
                        <x-campo etiqueta="Nota" nombre="notaFija" ayuda="Opcional. «Flota», «visita larga»." maxlength="120" wire:model="notaFija" />
                    </div>
                    <div class="mt-4 flex items-center gap-3">
                        <x-boton type="submit">Guardar</x-boton>
                        <x-boton type="button" variante="secundario" wire:click="cancelarFijo">Cancelar</x-boton>
                    </div>
                </form>
            @endif

            @if ($this->fijos->isNotEmpty())
                <div class="mt-3 overflow-x-auto rounded border border-slate-200 bg-white shadow-sm">
                    <table class="w-full min-w-[40rem] text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 text-left font-mono text-xs uppercase tracking-widest text-slate-500">
                                <th class="px-4 py-3 font-semibold">Placa</th>
                                <th class="px-4 py-3 font-semibold">Puesto</th>
                                <th class="px-4 py-3 font-semibold">Vehículo</th>
                                <th class="px-4 py-3 font-semibold">Nota</th>
                                <th class="px-4 py-3 font-semibold text-right">Desde</th>
                                <th class="px-4 py-3 font-semibold text-right">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($this->fijos as $f)
                                <tr wire:key="fijo-{{ $f->id }}">
                                    <td class="px-4 py-3 font-mono text-base font-bold tracking-wider text-slate-900">{{ $f->placa }}</td>
                                    <td class="px-4 py-3 font-mono font-semibold text-slate-700">{{ $f->puesto?->codigo ?? '—' }}</td>
                                    <td class="px-4 py-3 text-slate-600">
                                        <x-etiqueta :tipo="$f->tipo_vehiculo" tamano="chico" />
                                        <span class="ml-1">{{ trim(($f->marca ?? '').' '.($f->color ?? '')) ?: '—' }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-slate-500">{{ $f->nota ?: '—' }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right font-mono text-xs text-slate-500">{{ $f->entro_en->translatedFormat('D j M · g:i a') }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <button wire:click="sacarFijo({{ $f->id }})"
                                                wire:confirm="¿Sacar este vehículo fijo? Su puesto queda libre."
                                                class="text-sm font-semibold text-alto hover:underline">Sacar</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif

    {{-- El registro del día: entradas Y salidas de vehículos hoy. Plegado, para no cargarlo si no
         se pide. --}}
    <div class="mt-6">
        <button type="button" wire:click="$toggle('verHistorial')"
                class="flex items-center gap-2 font-mono text-xs font-bold uppercase tracking-widest text-slate-500 hover:text-slate-800">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                 class="h-4 w-4 transition-transform {{ $verHistorial ? 'rotate-90' : '' }}"><path d="m9 6 6 6-6 6"/></svg>
            Movimiento del día
        </button>

        @if ($verHistorial)
            <div class="mt-3 overflow-x-auto rounded border border-slate-200 bg-white shadow-sm">
                <table class="w-full min-w-[40rem] text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-left font-mono text-xs uppercase tracking-widest text-slate-500">
                            <th class="px-4 py-3 font-semibold">Hora</th>
                            <th class="px-4 py-3 font-semibold">Mov.</th>
                            <th class="px-4 py-3 font-semibold">Placa</th>
                            <th class="px-4 py-3 font-semibold">Vehículo</th>
                            <th class="px-4 py-3 font-semibold">Dueño</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($this->historial as $m)
                            <tr wire:key="hist-{{ $loop->index }}">
                                <td class="whitespace-nowrap px-4 py-3 font-mono text-xs text-slate-500">{{ $est->desde($m->ocurrio_en)->translatedFormat('g:i a') }}</td>
                                <td class="px-4 py-3"><x-etiqueta :tipo="$m->esEntrada ? 'entrada' : 'salida'" tamano="chico" /></td>
                                <td class="px-4 py-3 font-mono font-bold tracking-wider text-slate-900">{{ $m->placa ?: '—' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $m->vehiculo->etiquetaTipo() }} {{ trim(($m->marca ?? '').' '.($m->modelo ?? '')) }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $m->nombre }}</td>
                            </tr>
                        @empty
                            <x-tabla-vacia :columnas="5">Hoy no ha entrado ni salido ningún vehículo.</x-tabla-vacia>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Reporte de pernoctas por noche: se elige una fecha y sale quién se quedó esa noche
         (personas y fijos). Plegado, para no consultarlo si no se pide. --}}
    <div class="mt-6">
        <button type="button" wire:click="$toggle('verReporte')"
                class="flex items-center gap-2 font-mono text-xs font-bold uppercase tracking-widest text-slate-500 hover:text-slate-800">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                 class="h-4 w-4 transition-transform {{ $verReporte ? 'rotate-90' : '' }}"><path d="m9 6 6 6-6 6"/></svg>
            Pernoctas por noche
        </button>

        @if ($verReporte)
            <div class="mt-3">
                <div class="flex flex-wrap items-end gap-3">
                    <div class="w-48">
                        <x-campo etiqueta="Noche del" nombre="fechaReporte" type="date" wire:model.live="fechaReporte" />
                    </div>
                    <p class="pb-2.5 text-sm text-slate-500">
                        Quién estaba dentro en la medianoche que cierra ese día.
                    </p>
                </div>

                <div class="mt-3 overflow-x-auto rounded border border-slate-200 bg-white shadow-sm">
                    <table class="w-full min-w-[40rem] text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 text-left font-mono text-xs uppercase tracking-widest text-slate-500">
                                <th class="px-4 py-3 font-semibold">Placa</th>
                                <th class="px-4 py-3 font-semibold">Puesto</th>
                                <th class="px-4 py-3 font-semibold">Vehículo</th>
                                <th class="px-4 py-3 font-semibold">Quién</th>
                                <th class="px-4 py-3 font-semibold text-right">Entró</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($this->reporteNoche as $r)
                                <tr wire:key="rep-{{ $loop->index }}">
                                    <td class="px-4 py-3 font-mono text-base font-bold tracking-wider text-slate-900">{{ $r->placa ?: '—' }}</td>
                                    <td class="px-4 py-3 font-mono font-semibold text-slate-700">{{ $r->puesto ?: '—' }}</td>
                                    <td class="px-4 py-3 text-slate-600">
                                        <x-etiqueta :tipo="$r->tipo_vehiculo" tamano="chico" />
                                        <span class="ml-1">{{ trim(($r->marca ?? '').' '.($r->color ?? '')) ?: '—' }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-slate-600">
                                        {{ $r->quien }}
                                        @if ($r->origen === 'fijo')<span class="ml-1 font-mono text-xs text-slate-400">fijo</span>@endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right font-mono text-xs text-slate-500">{{ $r->entro_en->translatedFormat('D j M · g:i a') }}</td>
                                </tr>
                            @empty
                                <x-tabla-vacia :columnas="5">Esa noche no pernoctó ningún vehículo.</x-tabla-vacia>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</div>
