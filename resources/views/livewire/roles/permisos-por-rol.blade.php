{{--
    Qué puede hacer cada rol.

    Los tres roles son fijos: los define el README y no se crean ni se borran desde aquí. Lo que
    se mueve son los permisos, que es lo que abre cada pantalla.

    Lo que esta pantalla NO cambia es el orden de los roles. Darle «gestionar usuarios» al
    vigilante le deja crear vigilantes, no administradores: a quién puede tocar cada quien lo
    decide el código, y es lo que impide que esta misma pantalla sea una escalera al rol de arriba.
--}}
<div>

    <div class="mb-6">
        <div class="flex items-center gap-2.5">
            <h1 class="text-3xl font-bold tracking-tight">Roles y permisos</h1>
            <x-ayuda
                titulo="Roles y permisos"
                que="Qué puede hacer cada rol. Marcas por rol lo que abre, y el sistema respeta esas casillas en todo el sistema."
                :pasos="[
                    'Cada fila es un permiso; cada columna, un rol. Marca lo que ese rol puede hacer.',
                    'Algunos permisos son <b>intocables</b> (no se pueden quitar) porque el sistema se quedaría sin salida.',
                    'Nadie puede darse a sí mismo un permiso por encima de su rol.',
                ]" />
        </div>
        <p class="mt-1 text-sm text-slate-500">Marca lo que abre cada rol.</p>
    </div>

    @if ($confirmacion !== '')
        <x-aviso class="mb-5" wire:key="confirmacion">{{ $confirmacion }}</x-aviso>
    @endif

    @if ($errors->has('permisos'))
        <x-error class="mb-5">{{ $errors->first('permisos') }}</x-error>
    @endif

    <div class="overflow-hidden rounded border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left font-mono text-xs uppercase tracking-widest text-slate-500">
                        <th scope="col" class="px-4 py-3 font-semibold">Permiso</th>
                        @foreach ($this->roles() as $rol)
                            <th scope="col" class="w-40 px-4 py-3 text-center font-semibold">
                                {{ $rol->etiqueta() }}
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
