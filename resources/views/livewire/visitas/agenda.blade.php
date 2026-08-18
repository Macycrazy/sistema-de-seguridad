<div>
    @if ($aviso)
        <x-aviso class="mb-5" wire:key="aviso-visitas">{{ $aviso }}</x-aviso>
    @endif

    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="w-40">
            <x-campo etiqueta="Día" nombre="fecha" type="date" wire:model.live="fecha" />
        </div>
        <x-boton wire:click="abrirAlta">Agendar visita</x-boton>
    </div>

    {{-- Alta --}}
    @if ($creando)
        <form wire:submit="agendar" class="mt-5 rounded border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold">Nueva visita esperada</h2>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <x-campo etiqueta="Nombre de quien viene" nombre="nombre" maxlength="120"
                         wire:model="nombre" :error="$errors->first('nombre')" />

                <x-campo etiqueta="Cédula" nombre="cedula" maxlength="20"
                         ayuda="Opcional. Solo dígitos."
                         wire:model="cedula" :error="$errors->first('cedula')" />

                <x-campo etiqueta="A quién visita" nombre="aQuienVisita" maxlength="120"
                         ayuda="Opcional. El anfitrión."
                         wire:model="aQuienVisita" :error="$errors->first('a_quien_visita')" />

                <x-campo etiqueta="Día esperado" nombre="fechaEsperada" type="date"
                         wire:model="fechaEsperada" :error="$errors->first('fecha_esperada')" />

                <x-campo etiqueta="Motivo" nombre="motivo" maxlength="150"
                         ayuda="Opcional."
                         wire:model="motivo" :error="$errors->first('motivo')" />

                <x-campo etiqueta="Notas" nombre="notas" maxlength="255"
                         ayuda="Opcional."
                         wire:model="notas" :error="$errors->first('notas')" />
            </div>

            <div class="mt-5 flex items-center gap-3">
                <x-boton type="submit">Agendar</x-boton>
                <x-boton type="button" variante="secundario" wire:click="cancelarAlta">Cancelar</x-boton>
            </div>
        </form>
    @endif

    {{-- La lista del día --}}
    <div class="mt-6 overflow-x-auto rounded border border-slate-200 bg-white shadow-sm">
        <table class="w-full min-w-[44rem] text-sm">
            <thead>
                <tr class="border-b border-slate-200 text-left font-mono text-xs uppercase tracking-widest text-slate-500">
                    <th class="px-4 py-3 font-semibold">Quién</th>
                    <th class="px-4 py-3 font-semibold">A quién visita</th>
                    <th class="px-4 py-3 font-semibold">Estado</th>
                    <th class="px-4 py-3 font-semibold text-right">Acción</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($this->visitas as $visita)
                    @php
                        $vencida = $visita->esVencida();
                        $estado = match (true) {
                            $visita->estado === \App\Models\VisitaEsperada::LLEGO => 'llego',
                            $visita->estado === \App\Models\VisitaEsperada::CANCELADA => 'cancelada',
                            $vencida => 'vencida',
                            default => 'esperada',
                        };
                    @endphp
                    <tr wire:key="vis-{{ $visita->id }}">
                        <td class="px-4 py-3">
                            <span class="font-medium text-slate-900">{{ $visita->nombre }}</span>
                            @if ($visita->cedula)
                                <span class="ml-1 font-mono text-xs text-slate-400">{{ $visita->cedula }}</span>
                            @endif
                            @if ($visita->motivo)
                                <span class="block text-xs text-slate-500">{{ $visita->motivo }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ $visita->a_quien_visita ?: '—' }}</td>
                        <td class="px-4 py-3">
                            <x-etiqueta :tipo="$estado" tamano="chico" />
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-right">
                            @if ($visita->estaEsperada())
                                <button wire:click="marcarLlegada({{ $visita->id }})"
                                        class="text-sm font-semibold text-parte2 hover:underline">Llegó</button>
                                <button wire:click="cancelar({{ $visita->id }})"
                                        class="ml-4 text-sm font-semibold text-alto hover:underline">Cancelar</button>
                            @else
                                <span class="text-xs text-slate-400">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-sm text-slate-500">
                            No hay visitas agendadas para este día.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
