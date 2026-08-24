<div class="max-w-2xl">
    <p class="text-sm text-slate-600">
        La dirección del sistema de carnets en la red interna. La prueba la lanza el <strong>servidor</strong>
        de este sistema, no tu navegador: si estás detrás de una VPN, dará «no respondió» aunque todo
        esté bien —eso es la red, no la app—. Cuando la dirección correcta esté confirmada, se fija en
        el <code class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-xs">.env</code> (CARNETS_URL).
    </p>

    <div class="mt-5 flex flex-wrap items-end gap-3">
        <div class="grow">
            <x-campo etiqueta="Dirección del carnets" nombre="url" type="url"
                     placeholder="http://172.21.140.245:8000" wire:model="url" />
        </div>
        <x-boton wire:click="probar" wire:loading.attr="disabled" wire:target="probar">
            <span wire:loading.remove wire:target="probar">Probar conexión</span>
            <span wire:loading wire:target="probar">Probando…</span>
        </x-boton>
    </div>

    {{-- Resultado de la conexión --}}
    @if ($conexion)
        @if ($conexion['ok'])
            <x-aviso class="mt-4">{{ $conexion['mensaje'] }}</x-aviso>
        @else
            <x-error class="mt-4">{{ $conexion['mensaje'] }}</x-error>
        @endif
    @endif

    {{-- Probar un QR concreto --}}
    <div class="mt-8 border-t border-slate-200 pt-6">
        <h2 class="font-mono text-xs font-bold uppercase tracking-widest text-slate-500">Probar un QR</h2>
        <p class="mt-2 text-sm text-slate-600">
            Pega el contenido de un QR de carnet (la URL <code class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-xs">…/Trabajador_…</code>)
            y mira el veredicto que devolvería la puerta.
        </p>

        <div class="mt-4 flex flex-wrap items-end gap-3">
            <div class="grow">
                <x-campo etiqueta="Contenido del QR" nombre="qr"
                         placeholder="http://carnets/Trabajador_9f2e…" wire:model="qr" />
            </div>
            <x-boton variante="secundario" wire:click="verificar" wire:loading.attr="disabled" wire:target="verificar">
                <span wire:loading.remove wire:target="verificar">Probar verificación</span>
                <span wire:loading wire:target="verificar">Consultando…</span>
            </x-boton>
        </div>

        @if ($verificacion)
            @if ($verificacion['ok'] ?? false)
                @php $d = $verificacion['datos'] ?? []; $activo = ($d['activo'] ?? false) === true; @endphp
                @if ($activo)
                    <x-tarjeta class="mt-4">
                        <div class="flex items-center gap-2">
                            <x-etiqueta tipo="entrada">ACTIVO</x-etiqueta>
                            <span class="font-semibold text-slate-900">{{ $d['nombre'] ?? '—' }}</span>
                        </div>
                        <dl class="mt-3 grid grid-cols-2 gap-x-6 gap-y-1 text-sm">
                            <div><dt class="inline text-slate-500">Cédula:</dt> <dd class="inline font-mono text-slate-800">{{ ($d['nacionalidad'] ?? '') }}-{{ $d['cedula'] ?? '—' }}</dd></div>
                            <div><dt class="inline text-slate-500">Cargo:</dt> <dd class="inline text-slate-800">{{ $d['cargo'] ?? '—' }}</dd></div>
                            <div class="col-span-2"><dt class="inline text-slate-500">Gerencia:</dt> <dd class="inline text-slate-800">{{ $d['gerencia'] ?? '—' }}</dd></div>
                        </dl>
                        <p class="mt-3 border-t border-slate-200 pt-3 text-xs text-slate-500">
                            En la puerta: se marcaría con esta cédula y se actualizaría su ficha.
                        </p>
                    </x-tarjeta>
                @else
                    <x-tarjeta class="mt-4">
                        <div class="flex items-center gap-2">
                            <x-etiqueta tipo="inactivo">NO ACTIVO</x-etiqueta>
                            <span class="text-sm text-slate-600">Carnet inexistente o dado de baja. En la puerta iría por el flujo de visitante o se rechazaría.</span>
                        </div>
                    </x-tarjeta>
                @endif
            @else
                <x-error class="mt-4">{{ $verificacion['mensaje'] ?? 'No se pudo verificar.' }}</x-error>
            @endif
        @endif
    </div>
</div>
