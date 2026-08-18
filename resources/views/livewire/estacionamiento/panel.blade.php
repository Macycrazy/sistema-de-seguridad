@php
    $vehiculos = $this->vehiculos;
    $porTipo = $this->porTipo;
    $aforo = $this->aforo;
    $dentro = $vehiculos->count();
    $lleno = $aforo > 0 && $dentro >= $aforo;
@endphp

<div wire:loading.class="opacity-60" class="transition-opacity">
    {{-- El contador que gobierna la pantalla, con el botón de recalcular. --}}
    <div class="flex flex-wrap items-center justify-between gap-4 rounded border-2 bg-white px-5 py-4
                {{ $lleno ? 'border-alto' : 'border-parte1' }}">
        <p class="flex items-baseline gap-3">
            <span class="text-4xl font-bold tabular-nums tracking-tight text-slate-900">{{ $dentro }}</span>
            <span class="font-mono text-xs font-semibold uppercase tracking-widest {{ $lleno ? 'text-alto' : 'text-parte1' }}">
                @if ($aforo > 0)
                    de {{ $aforo }} · {{ $lleno ? 'lleno' : 'vehículos dentro' }}
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

    {{-- Carros y motos, que no estacionan en el mismo sitio. --}}
    <div class="mt-4 grid grid-cols-2 gap-4">
        <x-tarjeta>
            <p class="text-3xl font-bold tabular-nums text-slate-900">{{ $porTipo['carro'] }}</p>
            <p class="mt-1 font-mono text-xs uppercase tracking-widest text-slate-500">Carros</p>
        </x-tarjeta>
        <x-tarjeta>
            <p class="text-3xl font-bold tabular-nums text-slate-900">{{ $porTipo['moto'] }}</p>
            <p class="mt-1 font-mono text-xs uppercase tracking-widest text-slate-500">Motos</p>
        </x-tarjeta>
    </div>

    {{-- La lista de lo que hay dentro. --}}
    <div class="mt-4 overflow-x-auto rounded border border-slate-200 bg-white shadow-sm">
        <table class="w-full min-w-[40rem] text-sm">
            <thead>
                <tr class="border-b border-slate-200 text-left font-mono text-xs uppercase tracking-widest text-slate-500">
                    <th class="px-4 py-3 font-semibold">Placa</th>
                    <th class="px-4 py-3 font-semibold">Vehículo</th>
                    <th class="px-4 py-3 font-semibold">Dueño</th>
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
                        <td class="whitespace-nowrap px-4 py-3 text-right font-mono text-xs text-slate-500">
                            {{ app(\App\Services\Estacionamiento\Estacionamiento::class)->desde($v->ocurrio_en)->translatedFormat('d M · g:i a') }}
                        </td>
                    </tr>
                @empty
                    <x-tabla-vacia :columnas="4">
                        No hay vehículos dentro ahora mismo.
                    </x-tabla-vacia>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
