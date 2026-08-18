<div>
    @if ($aviso)
        <div class="mb-5 rounded border border-parte3/30 bg-parte3-suave px-4 py-3 text-sm font-semibold text-parte3"
             role="status" wire:key="aviso-umbral">
            {{ $aviso }}
        </div>
    @endif

    <p class="max-w-2xl text-sm text-slate-600">
        Cuándo el registro merece un aviso. Valen desde la siguiente lectura de alertas.
    </p>

    <form wire:submit="guardar" class="mt-6 space-y-4">
        @foreach ($this->umbrales as $umbral)
            <div class="rounded border border-slate-200 bg-white p-4 shadow-sm sm:flex sm:items-center sm:justify-between sm:gap-6"
                 wire:key="umbral-{{ $umbral['clave'] }}">
                <div class="min-w-0">
                    <p class="font-semibold text-slate-900">{{ $umbral['etiqueta'] }}</p>
                    <p class="mt-0.5 text-sm text-slate-600">{{ $umbral['explicacion'] }}</p>
                </div>

                <div class="mt-3 flex shrink-0 items-center gap-2 sm:mt-0">
                    <input
                        type="number"
                        min="{{ $umbral['minimo'] }}"
                        max="{{ $umbral['maximo'] }}"
                        wire:model="valores.{{ $umbral['clave'] }}"
                        class="w-24 rounded border border-slate-300 px-3 py-2 text-right font-mono text-lg tabular-nums
                               focus:border-slate-900 focus:outline-none focus:ring-4 focus:ring-slate-900/20"
                    >
                    <span class="w-16 font-mono text-xs uppercase tracking-widest text-slate-500">{{ $umbral['unidad'] }}</span>
                </div>

                @error('valores.'.$umbral['clave'])
                    <p class="mt-2 w-full text-sm text-alto sm:text-right">{{ $message }}</p>
                @enderror
            </div>
        @endforeach

        <div class="pt-2">
            <x-boton type="submit">Guardar</x-boton>
        </div>
    </form>
</div>
