{{--
    La puerta del sistema. Dos campos y nada más: no hay registro público —los usuarios los crea
    el administrador— ni «olvidé mi clave» —no hay correo por donde mandar nada, y el servidor no
    tiene salida a Internet—. Si alguien pierde la suya, el administrador se la reinicia.

    Todo lo que se ve sale de los componentes de /diseno: x-tarjeta, x-campo y x-boton.
--}}
<div class="mx-auto max-w-sm">

    <div class="mb-6">
        <h1 class="text-3xl font-bold tracking-tight">Entrar</h1>
        <p class="mt-2 text-sm text-slate-600">
            Cada quien con el suyo. Si no tienes usuario, te lo crea el administrador.
        </p>
    </div>

    {{-- Por qué se acabó la sesión anterior: la desactivaron, o se cerró. --}}
    @if (session('aviso'))
        <x-error class="mb-5">{{ session('aviso') }}</x-error>
    @endif

    <x-tarjeta parte="3">
        <form wire:submit="entrar" class="space-y-4">
            {{--
                El error del ingreso se cuelga del campo «usuario» porque es el primero, pero
                habla de los dos: nunca dice cuál de los dos falló.
            --}}
            <x-campo
                etiqueta="Usuario"
                nombre="usuario"
                autofocus
                autocomplete="username"
                maxlength="40"
                wire:model="usuario"
                :error="$errors->first('usuario')"
            />

            {{--
                «wire:model» a secas, sin «.live»: la clave viaja al servidor una sola vez, al
                enviar, y no en cada tecla.
            --}}
            <x-campo
                etiqueta="Clave"
                nombre="clave"
                type="password"
                autocomplete="current-password"
                wire:model="clave"
                :error="$errors->first('clave')"
            />

            <x-boton type="submit" class="w-full" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="entrar">Entrar</span>
                <span wire:loading wire:target="entrar">Entrando…</span>
            </x-boton>
        </form>
    </x-tarjeta>
</div>
