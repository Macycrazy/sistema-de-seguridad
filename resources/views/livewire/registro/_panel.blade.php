{{-- El histórico de una persona, al lado de la lista: buscar no debe hacer perder de
     vista el registro del día. --}}
<aside aria-label="Histórico de la persona">
    <x-tarjeta parte="2" class="lg:sticky lg:top-6">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-lg font-semibold leading-tight">{{ $persona->nombre() }}</p>
                <p @class([
                    'mt-0.5 font-mono text-xs',
                    'text-slate-500' => $persona->tieneDocumento(),
                    // Sin documento no es un detalle menor en un registro de accesos.
                    'text-alto' => ! $persona->tieneDocumento(),
                ])>
                    {{ $persona->documento() }}
                </p>
            </div>

            <x-boton
                variante="secundario"
                tamano="chico"
                wire:click="cerrarPanel"
                aria-label="Cerrar el histórico"
                class="shrink-0"
            >
                Cerrar
            </x-boton>
        </div>

        <div class="mt-3 flex flex-wrap items-center gap-2">
            <x-etiqueta :tipo="$persona->tipo->value" />

            @if ($persona->ente)
                <span class="font-mono text-xs uppercase tracking-widest text-slate-500">
                    {{ $persona->ente->etiqueta() }}
                </span>
            @endif
        </div>

        {{-- De a una cédula: aquí sí se muestra el detalle, porque es una sola persona
             consultada a propósito, no un volcado de la lista completa. --}}
        <dl class="mt-3 space-y-1 text-sm">
            <div class="flex gap-2">
                <dt class="shrink-0 text-slate-500">Adscripción:</dt>
                <dd class="text-slate-700">{{ $persona->adscripcion() }}</dd>
            </div>

            @if ($persona->cargo)
                <div class="flex gap-2">
                    <dt class="shrink-0 text-slate-500">Cargo:</dt>
                    <dd class="text-slate-700">{{ $persona->cargo }}</dd>
                </div>
            @endif
        </dl>

        {{-- La ficha se muestra igual, pero se avisa: el registro no corrige datos de
             origen, y callarlo dejaría el error ahí para siempre. --}}
        @if ($persona->nombresRepitenApellidos())
            <p role="alert" class="mt-3 rounded border border-alto bg-alto-suave px-3 py-2 text-xs text-alto">
                Esta ficha trae los apellidos repetidos en el campo de nombres.
                Hay que corregirla en el listado de personal.
            </p>
        @endif

        <h3 class="mt-5 border-t border-slate-200 pt-4 font-mono text-xs font-semibold uppercase tracking-widest text-slate-500">
            Histórico
        </h3>

        @if ($this->historico->isEmpty())
            <p class="mt-3 text-sm text-slate-500">Esta persona todavía no tiene movimientos.</p>
        @else
            <ul class="mt-3 max-h-96 divide-y divide-slate-100 overflow-y-auto">
                @foreach ($this->historico as $movimiento)
                    <li wire:key="hist-{{ $movimiento->id }}" class="flex items-center justify-between gap-3 py-2">
                        <span class="min-w-0">
                            <span class="block font-mono text-xs text-slate-500">
                                {{ $movimiento->fecha() }} · {{ $movimiento->hora() }}
                            </span>
                            {{-- Con qué vehículo se hizo, para saber de quién es ese vehículo. --}}
                            @if ($movimiento->tieneVehiculo())
                                <span class="mt-1 flex items-center gap-1.5">
                                    <x-etiqueta :tipo="$movimiento->vehiculo->tipo" tamano="chico" />
                                    <span class="font-mono text-xs font-semibold tracking-wider text-slate-700">{{ $movimiento->vehiculo->placa }}</span>
                                </span>
                            @endif
                        </span>
                        <x-etiqueta :tipo="$movimiento->sentido->value" />
                    </li>
                @endforeach
            </ul>

            <p class="mt-3 border-t border-slate-200 pt-3 text-xs text-slate-500">
                {{ $this->historico->count() }} movimientos registrados.
            </p>
        @endif
    </x-tarjeta>
</aside>
