@php
    $tramo = $this->tramo;
    $resumen = $this->resumen;
    $porFranja = $this->porFranja;
    $porTipo = $this->porTipo;

    // La barra más alta manda la escala de cada gráfica: todo se mide contra el pico, así una
    // barra llena es «el día/hora de más» y no un valor absoluto que nadie tiene con qué comparar.
    $topeDia = max(1, $this->porDia->max('entradas'));
    $topeFranja = max(1, max($porFranja ?: [0]));
    $totalTipo = max(1, $porTipo['trabajador'] + $porTipo['invitado']);
@endphp

<div wire:loading.class="opacity-60" class="transition-opacity">
    {{-- TRAMO: los atajos primero (es como se pide casi siempre), y debajo las dos fechas. --}}
    <div class="flex flex-wrap items-end gap-3">
        <div class="flex flex-wrap gap-2">
            <x-boton variante="secundario" tamano="chico" wire:click="ultimos(7)">7 días</x-boton>
            <x-boton variante="secundario" tamano="chico" wire:click="ultimos(30)">30 días</x-boton>
            <x-boton variante="secundario" tamano="chico" wire:click="esteMes">Este mes</x-boton>
        </div>

        <div class="grid grow grid-cols-2 gap-3 sm:max-w-xs">
            <x-campo etiqueta="Desde" nombre="desde" type="date" wire:model.live="desde" />
            <x-campo etiqueta="Hasta" nombre="hasta" type="date" wire:model.live="hasta" />
        </div>
    </div>

    <p class="mt-3 font-mono text-xs uppercase tracking-widest text-slate-500">
        {{ $tramo['desde']->translatedFormat('d M Y') }}
        — {{ $tramo['hasta']->translatedFormat('d M Y') }}
        · {{ $this->diasDelTramo }} {{ $this->diasDelTramo === 1 ? 'día' : 'días' }}
    </p>

    {{-- CIFRAS DE CABECERA --}}
    <div class="mt-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-tarjeta parte="2">
            <p class="text-3xl font-bold tabular-nums text-slate-900">{{ number_format($resumen['entradas']) }}</p>
            <p class="mt-1 font-mono text-xs uppercase tracking-widest text-slate-500">Entradas</p>
        </x-tarjeta>
        <x-tarjeta>
            <p class="text-3xl font-bold tabular-nums text-slate-900">{{ number_format($resumen['personas']) }}</p>
            <p class="mt-1 font-mono text-xs uppercase tracking-widest text-slate-500">Personas distintas</p>
        </x-tarjeta>
        <x-tarjeta>
            <p class="text-3xl font-bold tabular-nums text-slate-900">{{ number_format($resumen['promedio']) }}</p>
            <p class="mt-1 font-mono text-xs uppercase tracking-widest text-slate-500">Promedio por día activo</p>
        </x-tarjeta>
        <x-tarjeta>
            <p class="truncate text-lg font-semibold text-slate-900">{{ $this->franjaPico ?? '—' }}</p>
            <p class="mt-1 font-mono text-xs uppercase tracking-widest text-slate-500">
                Franja pico @if ($resumen['picoEntradas'] > 0)· {{ $resumen['picoEntradas'] }} @endif
            </p>
        </x-tarjeta>
    </div>

    @if ($resumen['entradas'] === 0)
        <x-tarjeta class="mt-4">
            <p class="text-sm text-slate-500">No hubo entradas en este tramo. Prueba con otras fechas.</p>
        </x-tarjeta>
    @else
        {{-- ENTRADAS POR DÍA --}}
        <x-tarjeta titulo="Entradas por día" class="mt-4">
            <div class="overflow-x-auto">
                <div class="flex min-w-[36rem] items-end gap-1" style="height: 10rem">
                    @foreach ($this->porDia as $dia)
                        <div class="group flex flex-1 flex-col items-center justify-end gap-1"
                             title="{{ $dia['fecha']->translatedFormat('D d M') }}: {{ $dia['entradas'] }}">
                            <span class="text-[10px] font-semibold tabular-nums text-slate-400 opacity-0 group-hover:opacity-100">
                                {{ $dia['entradas'] ?: '' }}
                            </span>
                            <div class="w-full rounded-t bg-parte2/80 transition group-hover:bg-parte2"
                                 style="height: {{ max(2, round($dia['entradas'] / $topeDia * 100)) }}%"></div>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="mt-2 flex min-w-[36rem] justify-between font-mono text-[10px] uppercase tracking-widest text-slate-400">
                <span>{{ $tramo['desde']->translatedFormat('d M') }}</span>
                <span>{{ $tramo['hasta']->translatedFormat('d M') }}</span>
            </div>
        </x-tarjeta>

        <div class="mt-4 grid gap-4 lg:grid-cols-2">
            {{-- ENTRADAS POR HORA --}}
            <x-tarjeta titulo="Entradas por hora del día">
                <div class="overflow-x-auto">
                    <div class="flex min-w-[28rem] items-end gap-0.5" style="height: 9rem">
                        @foreach ($porFranja as $hora => $entradas)
                            <div class="group flex flex-1 flex-col items-center justify-end"
                                 title="{{ $hora }}:00 – {{ $hora }}:59 · {{ $entradas }}">
                                <div class="w-full rounded-t bg-marca/70 transition group-hover:bg-marca"
                                     style="height: {{ $entradas ? max(2, round($entradas / $topeFranja * 100)) : 0 }}%"></div>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="mt-2 flex min-w-[28rem] justify-between font-mono text-[10px] uppercase tracking-widest text-slate-400">
                    <span>0 h</span><span>6 h</span><span>12 h</span><span>18 h</span><span>23 h</span>
                </div>
            </x-tarjeta>

            {{-- TRABAJADORES VS INVITADOS --}}
            <x-tarjeta titulo="Quién entró">
                @php
                    $pt = round($porTipo['trabajador'] / $totalTipo * 100);
                    $pi = 100 - $pt;
                @endphp
                <div class="flex h-4 overflow-hidden rounded-full bg-slate-100">
                    <div class="bg-parte1" style="width: {{ $pt }}%"></div>
                    <div class="bg-parte3" style="width: {{ $pi }}%"></div>
                </div>
                <dl class="mt-4 space-y-3">
                    <div class="flex items-center justify-between">
                        <dt class="flex items-center gap-2 text-sm text-slate-600">
                            <span class="h-3 w-3 rounded-full bg-parte1"></span>Trabajadores
                        </dt>
                        <dd class="font-semibold tabular-nums text-slate-900">
                            {{ number_format($porTipo['trabajador']) }}
                            <span class="ml-1 text-xs font-normal text-slate-400">{{ $pt }}%</span>
                        </dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="flex items-center gap-2 text-sm text-slate-600">
                            <span class="h-3 w-3 rounded-full bg-parte3"></span>Invitados
                        </dt>
                        <dd class="font-semibold tabular-nums text-slate-900">
                            {{ number_format($porTipo['invitado']) }}
                            <span class="ml-1 text-xs font-normal text-slate-400">{{ $pi }}%</span>
                        </dd>
                    </div>
                </dl>
            </x-tarjeta>
        </div>

        {{-- MÁS FRECUENTES --}}
        <x-tarjeta titulo="Quiénes entraron más veces" class="mt-4">
            <ol class="divide-y divide-slate-100">
                @foreach ($this->masFrecuentes as $i => $fila)
                    <li class="flex items-center gap-3 py-2.5">
                        <span class="w-5 shrink-0 text-right font-mono text-xs font-semibold text-slate-400">{{ $i + 1 }}</span>
                        <span class="min-w-0 grow truncate text-sm text-slate-800">
                            {{ $fila['persona']?->nombre ?? 'Persona retirada' }}
                            @if ($fila['persona']?->esInvitado())
                                <span class="ml-1 rounded bg-parte3/10 px-1.5 py-0.5 font-mono text-[10px] uppercase tracking-wide text-parte3">Invitado</span>
                            @endif
                        </span>
                        <span class="shrink-0 font-semibold tabular-nums text-slate-900">{{ $fila['visitas'] }}</span>
                    </li>
                @endforeach
            </ol>
        </x-tarjeta>
    @endif
</div>
