{{--
    Cambiar la propia clave. Se entra por el nombre del encabezado, cuando a uno le parece.

    Si la clave te la puso el administrador, cambiarla aquí es lo que hace que sea tuya: mientras
    no lo hagas, la sabe también quien la puso.
--}}
<div class="mx-auto max-w-sm">

    <div class="mb-6">
        <h1 class="text-3xl font-bold tracking-tight">Cambiar la clave</h1>
        <p class="mt-2 text-sm text-slate-600">
            Si te la puso el administrador, con esto pasa a ser tuya y de nadie más.
        </p>
    </div>

    @if ($confirmacion !== '')
        <x-aviso class="mb-5" wire:key="confirmacion">{{ $confirmacion }}</x-aviso>
    @endif

    <x-tarjeta parte="3">
        <form wire:submit="guardar" class="space-y-4">
            <x-campo
                etiqueta="Clave actual"
                nombre="actual"
                type="password"
                revelable
                autofocus
                autocomplete="current-password"
                wire:model="actual"
                :error="$errors->first('actual')"
            />

            <x-campo
                etiqueta="Clave nueva"
                nombre="nueva"
                type="password"
                revelable
                autocomplete="new-password"
                ayuda="Al menos {{ \App\Services\GestionDeUsuarios::MINIMO_DE_LA_CLAVE }} caracteres."
                wire:model="nueva"
                :error="$errors->first('nueva')"
            />

            <x-campo
                etiqueta="Repite la clave nueva"
                nombre="repetida"
                type="password"
                revelable
                autocomplete="new-password"
                wire:model="repetida"
                :error="$errors->first('repetida')"
            />

            <x-boton type="submit" class="w-full">Guardar</x-boton>
        </form>
    </x-tarjeta>
</div>
