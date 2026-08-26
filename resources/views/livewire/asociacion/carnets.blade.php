<div class="max-w-2xl">
{{-- EL LECTOR DE LA PUERTA.

     Teclear la cédula es lo que siempre funciona; escanear el carnet es un atajo. Y un atajo que
     no encaja en un puesto —una tableta sin cámara decente, una entrada a contraluz, un carnets
     que no responde— estorba encima del campo que sí sirve. Se apaga aquí, que es donde se está
     comprobando si el carnets responde. --}}
@can('gestionar-ajustes')
    <label class="mb-5 flex cursor-pointer items-center gap-4 rounded border px-4 py-3 transition
                  {{ $escanerEnLaPuerta ? 'border-parte1 bg-parte1-suave' : 'border-slate-200 bg-white' }}">
        <input type="checkbox" wire:model.live="escanerEnLaPuerta" wire:change="alternarEscaner" class="peer sr-only">

        <span aria-hidden="true"
              class="relative inline-flex h-7 w-12 shrink-0 items-center rounded-full transition
                     peer-focus-visible:ring-2 peer-focus-visible:ring-parte1/40 peer-focus-visible:ring-offset-2
                     {{ $escanerEnLaPuerta ? 'bg-parte1' : 'bg-slate-300' }}">
            <span class="h-5 w-5 rounded-full bg-white shadow transition-transform
                         {{ $escanerEnLaPuerta ? 'translate-x-6' : 'translate-x-1' }}"></span>
        </span>

        <span class="min-w-0">
            <span class="block font-semibold text-slate-900">
                «Escanear carnet con la cámara» en la puerta
                <span class="ml-1 font-mono text-[0.625rem] uppercase tracking-widest
                             {{ $escanerEnLaPuerta ? 'text-parte1' : 'text-slate-400' }}">
                    {{ $escanerEnLaPuerta ? 'encendido' : 'apagado' }}
                </span>
            </span>
            <span class="mt-0.5 block text-sm text-slate-600">
                @if ($escanerEnLaPuerta)
                    El vigilante ve el botón debajo de la cédula.
                @else
                    La puerta solo pide la cédula. Apágalo si en ese puesto el escáner no sirve.
                @endif
            </span>
        </span>
    </label>
@endcan

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
