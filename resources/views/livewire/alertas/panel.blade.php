@php
    $alertas = $this->alertas;
    $urgentes = $alertas->where('severidad', \App\Services\Alertas\Alerta::URGENTE)->count();
@endphp

<div>
    @if ($aviso !== '')
        <x-aviso class="mb-4" wire:key="aviso">{{ $aviso }}</x-aviso>
    @endif

    {{-- Las permanencias largas casi siempre son olvidos de marcar la salida, y se acumulan: con
         treinta y nueve encendidas la pantalla deja de mirarse, y esa gente además sigue contando
         como «dentro». Cerrarlas de una vez es lo único que alguien va a hacer de verdad. --}}
    @if ($this->permanencias->count() > 1)
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded border border-invitado/40 bg-invitado-suave/40 px-4 py-3">
            <p class="text-sm text-slate-700">
                <b>{{ $this->permanencias->count() }}</b> personas llevan demasiado dentro. Casi siempre es que
                nadie les marcó la salida.
            </p>

            <x-boton variante="secundario" tamano="chico"
                     wire:click="cerrarTodosLosOlvidos"
                     wire:confirm="Se registrará la salida que faltó de {{ $this->permanencias->count() }} personas. No se borra nada: sus entradas y su histórico se conservan.">
                Registrar la salida de todas
            </x-boton>
        </div>
    @endif
    {{-- Cabecera: cuántas hay y el botón de recalcular. --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="flex items-baseline gap-3">
            <span class="text-4xl font-bold tabular-nums tracking-tight text-slate-900">{{ $alertas->count() }}</span>
            <span class="font-mono text-xs font-semibold uppercase tracking-widest text-slate-500">
                {{ $alertas->count() === 1 ? 'alerta activa' : 'alertas activas' }}
                @if ($urgentes > 0)· <span class="text-alto">{{ $urgentes }} urgente{{ $urgentes === 1 ? '' : 's' }}</span>@endif
            </span>
        </p>

        <x-boton variante="secundario" wire:click="actualizar" wire:loading.attr="disabled" wire:target="actualizar">
            <span wire:loading.remove wire:target="actualizar">Actualizar</span>
            <span wire:loading wire:target="actualizar">Mirando…</span>
        </x-boton>
    </div>

    @if ($alertas->isEmpty())
        <x-tarjeta class="mt-6">
            <div class="flex items-center gap-3 text-slate-600">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                     stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6 text-parte2">
                    <circle cx="12" cy="12" r="9"/><path d="m8.5 12 2.5 2.5 4.5-5"/>
                </svg>
                <p class="text-sm">Nada que reportar. No hay alertas ahora mismo.</p>
            </div>
        </x-tarjeta>
    @else
        <ul class="mt-6 space-y-3">
            @foreach ($alertas as $alerta)
                @php $urgente = $alerta->esUrgente(); @endphp
                <li class="flex items-start gap-3 rounded border bg-white p-4 shadow-sm
                           {{ $urgente ? 'border-alto/40' : 'border-slate-200' }}">
                    {{-- El punto de color dice la gravedad de un vistazo. Clases enteras: Tailwind
                         solo reconoce el texto literal, nunca lo que se arma desde una variable. --}}
                    <span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full {{ $urgente ? 'bg-alto' : 'bg-parte2' }}" aria-hidden="true"></span>

                    <div class="min-w-0 grow">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-semibold text-slate-900">
                                {{ $alerta->titulo }}
                                {{-- La cédula al lado del nombre: el nombre no identifica, y de
                                     estas alertas cuelgan acciones que no se pueden hacer sobre la
                                     persona equivocada. --}}
                                @if ($alerta->personaCedula)
                                    <span class="ml-1 font-mono text-xs font-normal text-slate-500">{{ $alerta->personaCedula }}</span>
                                @endif
                            </p>
                            <x-etiqueta :tipo="$urgente ? 'urgente' : 'aviso'" tamano="chico" />
                            <span class="font-mono text-[10px] uppercase tracking-widest text-slate-400">
                                @switch($alerta->tipo)
                                    @case(\App\Services\Alertas\Alerta::AFORO) Aforo @break
                                    @case(\App\Services\Alertas\Alerta::ESTACIONAMIENTO) Estacionamiento @break
                                    @case(\App\Services\Alertas\Alerta::FLOTA_FUERA) Vehículo de la empresa @break
                                    @case(\App\Services\Alertas\Alerta::PASE_FUERA) Pase de visitante @break
                                    @default Permanencia
                                @endswitch
                            </span>
                        </div>
                        <p class="mt-1 text-sm text-slate-600">{{ $alerta->detalle }}</p>

                        {{-- Dos salidas, porque son dos cosas distintas que se ven igual: o esa
                             persona se fue y nadie la marcó —lo normal—, o de verdad sigue dentro
                             (el guardia de noche, un turno largo) y lo que sobra es el aviso. --}}
                        @if ($alerta->tipo === \App\Services\Alertas\Alerta::PERMANENCIA && $alerta->personaId)
                            <p class="mt-2 flex flex-wrap gap-4">
                                <button type="button" wire:click="cerrarOlvido('{{ $alerta->personaId }}')"
                                        class="text-sm font-semibold text-parte2 hover:underline">
                                    Ya salió: registrar su salida
                                </button>

                                <button type="button" wire:click="silenciar('{{ $alerta->personaId }}')"
                                        class="text-sm font-semibold text-slate-500 hover:underline">
                                    Sigue dentro: callar hasta mañana
                                </button>
                            </p>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
