{{--
    Los roles y qué puede hacer cada uno.

    Los tres base (vigilante, supervisor, administrador) son fijos: no se crean ni se borran, y son
    la columna vertebral de la jerarquía. El administrador puede AÑADIR más roles, cada uno anclado
    a un nivel (1, 2 o 3), que es lo que decide a quién puede tocar.

    Lo que esta pantalla NO cambia es el ORDEN de los niveles: darle «gestionar usuarios» a un rol
    de nivel bajo le deja crear roles de su nivel o menos, nunca por encima. A quién puede tocar
    cada quien lo decide el código (Rol::alcanza), y es lo que impide que esto sea una escalera.
--}}
<div>

    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <h1 class="text-3xl font-bold tracking-tight">Roles y permisos</h1>
                <x-ayuda
                    titulo="Roles y permisos"
                    que="Los roles del sistema y qué abre cada uno. Cada columna es un rol; cada fila, un permiso."
                    :pasos="[
                        '<b>Nuevo rol</b>: un nombre y un nivel (1 como vigilante, 2 supervisor, 3 admin). El nivel decide a quién puede gestionar.',
                        'Marca en su columna lo que ese rol puede hacer, y pulsa <b>Guardar</b>.',
                        'Los tres roles base no se borran. Un rol nuevo no se puede borrar si hay usuarios que lo tienen.',
                        'Ningún rol puede darse permisos por encima de su nivel; «gestionar permisos» es solo del administrador.',
                    ]" />
            </div>
            <p class="mt-1 text-sm text-slate-500">Marca lo que abre cada rol, o crea uno nuevo.</p>
        </div>

        @unless ($gestionandoRol)
            <x-boton wire:click="abrirNuevoRol">Nuevo rol</x-boton>
        @endunless
    </div>

    @if ($confirmacion !== '')
        <x-aviso class="mb-5" wire:key="confirmacion">{{ $confirmacion }}</x-aviso>
    @endif

    @if ($errors->has('permisos') || $errors->has('rol'))
        <x-error class="mb-5">{{ $errors->first('permisos') ?: $errors->first('rol') }}</x-error>
    @endif

    {{-- Alta / edición de un rol. El slug no se edita: renombrar no mueve a los usuarios. --}}
    @if ($gestionandoRol)
        <x-tarjeta parte="3" :titulo="$rolEditando ? 'Editar rol' : 'Nuevo rol'" class="mb-6">
            <form wire:submit="guardarRol" class="flex flex-wrap items-end gap-4">
                <div class="w-full sm:w-64">
                    <x-campo
                        etiqueta="Nombre del rol"
                        nombre="nombreRol"
                        autofocus
                        maxlength="60"
                        wire:model="nombreRol"
                        :error="$errors->first('nombre')"
                    />
                </div>

                <div class="w-full sm:w-64">
                    <x-selector
                        etiqueta="Nivel"
                        nombre="nivelRol"
                        :opciones="$this->niveles()"
                        ayuda="Decide a quién puede gestionar, igual que el rol base de ese nivel."
                        wire:model="nivelRol"
                        :error="$errors->first('nivel')"
                    />
                </div>

                <div class="flex items-center gap-2 pb-1">
                    <x-boton type="submit">{{ $rolEditando ? 'Guardar' : 'Crear' }}</x-boton>
                    <x-boton type="button" variante="secundario" wire:click="cancelarRol">Cancelar</x-boton>
                </div>
            </form>
        </x-tarjeta>
    @endif

    <div class="overflow-hidden rounded border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left font-mono text-xs uppercase tracking-widest text-slate-500">
                        <th scope="col" class="px-4 py-3 font-semibold">Permiso</th>
                        @foreach ($this->roles() as $rol)
                            <th scope="col" class="w-44 px-4 py-3 text-center font-semibold">
                                <div class="flex flex-col items-center gap-0.5">
                                    <span>{{ $rol->etiqueta() }}</span>
                                    <span class="text-[10px] font-normal normal-case tracking-normal text-slate-400">
                                        Nivel {{ $rol->nivel }}@unless ($rol->esBase()) · creado @endunless
                                    </span>
                                    @unless ($rol->esBase())
                                        <div class="mt-1 flex items-center gap-2">
                                            <button type="button" wire:click="abrirEdicionRol('{{ $rol->value }}')"
                                                    class="text-[11px] font-semibold normal-case tracking-normal text-parte3 hover:underline">Editar</button>
                                            <button type="button" wire:click="eliminarRol('{{ $rol->value }}')"
                                                    wire:confirm="¿Borrar el rol «{{ $rol->nombre }}»? No se puede si hay usuarios que lo tienen."
                                                    class="text-[11px] font-semibold normal-case tracking-normal text-alto hover:underline">Borrar</button>
                                        </div>
                                    @endunless
                                </div>
                            </th>
                        @endforeach
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100">
                    @foreach ($this->porGrupo() as $grupo => $permisos)
                        {{-- Encabezado del módulo: agrupa su «ver» y su «gestionar». --}}
                        <tr wire:key="grupo-{{ $grupo }}" class="bg-slate-50">
                            <td colspan="{{ count($this->roles()) + 1 }}" class="px-4 py-2">
                                <span class="font-mono text-xs font-semibold uppercase tracking-widest text-slate-500">
                                    {{ $grupo }}
                                </span>
                            </td>
                        </tr>

                        @foreach ($permisos as $permiso)
                            <tr wire:key="permiso-{{ $permiso->value }}">
                                <td class="px-4 py-3 pl-6">
                                    <p class="font-semibold text-slate-900">{{ $permiso->etiqueta() }}</p>
                                    <p class="mt-0.5 text-xs text-slate-500">{{ $permiso->explicacion() }}</p>
                                </td>

                                @foreach ($this->roles() as $rol)
                                    <td class="px-4 py-3 text-center">
                                        @if (! $this->editable($rol, $permiso))
                                            {{-- Clavado: ver Permiso::esIntocable(). --}}
                                            <span
                                                class="font-mono text-xs uppercase tracking-widest {{ $matriz[$rol->value][$permiso->value] ? 'text-parte3' : 'text-slate-300' }}"
                                                title="No se puede cambiar: sin él, esta pantalla se cerraría para siempre."
                                            >
                                                {{ $matriz[$rol->value][$permiso->value] ? 'Siempre' : 'Nunca' }}
                                            </span>
                                        @elseif ($this->bloqueada($rol, $permiso))
                                            {{-- Su «gestionar» está marcado: ver va incluido y no se puede quitar. --}}
                                            <input
                                                type="checkbox"
                                                checked
                                                disabled
                                                class="h-5 w-5 rounded border-slate-300 text-parte3 opacity-60"
                                                aria-label="{{ $permiso->etiqueta() }} · {{ $rol->etiqueta() }} (incluido con gestionar)"
                                                title="Incluido: quien puede gestionar este módulo puede verlo."
                                            >
                                        @else
                                            <input
                                                type="checkbox"
                                                class="h-5 w-5 rounded border-slate-300 text-parte3 focus:ring-2 focus:ring-parte3/40"
                                                aria-label="{{ $permiso->etiqueta() }} · {{ $rol->etiqueta() }}"
                                                wire:model.live="matriz.{{ $rol->value }}.{{ $permiso->value }}"
                                            >
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6 flex flex-wrap items-center gap-3">
        <x-boton wire:click="guardar">Guardar</x-boton>
        <x-boton variante="secundario" wire:click="restablecer">
            Devolver a como venía
        </x-boton>
        <p class="text-sm text-slate-500">
            «Gestionar permisos» no se toca: es lo que impide dejar el sistema sin quien lo
            administre.
        </p>
    </div>
</div>
