@php
    use App\Services\Estacionamiento\Estacionamiento;
    $est = app(Estacionamiento::class);
    $r = $this->resumen;
    $vehiculos = $this->vehiculos;
    $total = $r['total'];
@endphp

<div wire:loading.class="opacity-60" class="transition-opacity">
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

    {{-- La lista de lo que hay dentro (o lo que coincide con la placa buscada). --}}
    <div class="mt-3 overflow-x-auto rounded border border-slate-200 bg-white shadow-sm">
        <table class="w-full min-w-[44rem] text-sm">
            <thead>
                <tr class="border-b border-slate-200 text-left font-mono text-xs uppercase tracking-widest text-slate-500">
                    <th class="px-4 py-3 font-semibold">Placa</th>
                    <th class="px-4 py-3 font-semibold">Vehículo</th>
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
                    <x-tabla-vacia :columnas="5">
                        {{ trim($busqueda) === '' ? 'No hay vehículos dentro ahora mismo.' : 'Ninguna placa dentro coincide con «'.$busqueda.'».' }}
                    </x-tabla-vacia>
                @endforelse
            </tbody>
        </table>
    </div>

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
</div>
