<div>
    @if ($aviso)
        <x-aviso class="mb-5" wire:key="aviso-retencion">{{ $aviso }}</x-aviso>
    @endif

    <p class="max-w-2xl text-sm text-slate-600">
        Cuánto se guarda antes de poder depurar. En <strong>0</strong> no se borra nunca (así viene
        de fábrica). Fijar un periodo aquí <em>no</em> borra nada: solo habilita el comando de
        depuración, que archiva a un CSV y borra a mano. El registro es inmutable; esto es la única
        excepción, y a propósito exige un paso deliberado.
    </p>

    <form wire:submit="guardar" class="mt-6 space-y-4">
        @foreach ($this->periodos as $periodo)
            <div class="rounded border border-slate-200 bg-white p-4 shadow-sm sm:flex sm:items-center sm:justify-between sm:gap-6"
                 wire:key="ret-{{ $periodo['clave'] }}">
                <div class="min-w-0">
                    <p class="font-semibold text-slate-900">{{ $periodo['etiqueta'] }}</p>
                    <p class="mt-0.5 text-sm text-slate-600">{{ $periodo['explicacion'] }}</p>
                </div>

                <div class="mt-3 flex shrink-0 items-center gap-2 sm:mt-0">
                    <input
                        type="number"
                        min="{{ $periodo['minimo'] }}"
                        max="{{ $periodo['maximo'] }}"
                        wire:model="valores.{{ $periodo['clave'] }}"
                        class="w-24 rounded border border-slate-300 px-3 py-2 text-right font-mono text-lg tabular-nums
                               focus:border-slate-900 focus:outline-none focus:ring-4 focus:ring-slate-900/20"
                    >
                    <span class="w-16 font-mono text-xs uppercase tracking-widest text-slate-500">{{ $periodo['unidad'] }}</span>
                </div>

                @error('valores.'.$periodo['clave'])
                    <p class="mt-2 w-full text-sm text-alto sm:text-right">{{ $message }}</p>
                @enderror
            </div>
        @endforeach

        <div class="pt-2">
            <x-boton type="submit">Guardar</x-boton>
        </div>

        <p class="pt-1 font-mono text-xs text-slate-500">
            Para depurar: <code class="rounded bg-slate-100 px-1.5 py-0.5">php artisan registro:depurar</code>
            (muestra lo que haría) · añade <code class="rounded bg-slate-100 px-1.5 py-0.5">--confirmar</code> para archivar y borrar.
        </p>
    </form>
</div>
