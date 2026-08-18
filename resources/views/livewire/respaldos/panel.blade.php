<div>
    @if ($aviso)
        <div class="mb-5 rounded border border-parte3/30 bg-parte3-suave px-4 py-3 text-sm font-semibold text-parte3"
             role="status" wire:key="aviso-resp">{{ $aviso }}</div>
    @endif

    @if ($error)
        <div class="mb-5 rounded border border-alto/40 bg-alto-suave px-4 py-3 text-sm text-alto" role="alert">{{ $error }}</div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-4">
        <p class="max-w-2xl text-sm text-slate-600">
            Copias completas de la base. Un respaldo es <strong>toda la data</strong> en un archivo:
            guárdalo en sitio seguro. No se crean solos; también existe el comando
            <code class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-xs">php artisan respaldo:crear</code>.
        </p>

        <x-boton wire:click="crear" wire:loading.attr="disabled" wire:target="crear">
            <span wire:loading.remove wire:target="crear">Crear respaldo</span>
            <span wire:loading wire:target="crear">Creando…</span>
        </x-boton>
    </div>

    <div class="mt-6 overflow-x-auto rounded border border-slate-200 bg-white shadow-sm">
        <table class="w-full min-w-[36rem] text-sm">
            <thead>
                <tr class="border-b border-slate-200 text-left font-mono text-xs uppercase tracking-widest text-slate-500">
                    <th class="px-4 py-3 font-semibold">Archivo</th>
                    <th class="px-4 py-3 font-semibold">Cuándo</th>
                    <th class="px-4 py-3 font-semibold text-right">Tamaño</th>
                    <th class="px-4 py-3 font-semibold text-right">Acción</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($this->respaldos as $r)
                    <tr wire:key="resp-{{ $r['nombre'] }}">
                        <td class="px-4 py-3 font-mono text-slate-900">{{ $r['nombre'] }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $r['cuando']->translatedFormat('d M Y · g:i a') }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-slate-600">{{ number_format($r['bytes'] / 1024, 1) }} KB</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right">
                            <button wire:click="descargar('{{ $r['nombre'] }}')"
                                    class="text-sm font-semibold text-parte3 hover:underline">Descargar</button>
                            <button wire:click="eliminar('{{ $r['nombre'] }}')"
                                    wire:confirm="¿Borrar este respaldo? No se puede deshacer."
                                    class="ml-4 text-sm font-semibold text-alto hover:underline">Borrar</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-10 text-center text-sm text-slate-500">
                            Todavía no hay respaldos. Crea el primero con el botón de arriba.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
