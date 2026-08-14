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
        <h1 class="text-3xl font-bold tracking-tight">Roles y permisos</h1>
        <p class="mt-2 max-w-3xl text-sm text-slate-600">
            Marca lo que abre cada rol. Vale desde que se guarda, sin que nadie tenga que volver a
            entrar.
        </p>
    </div>

    @if ($confirmacion !== '')
        <div class="mb-5 rounded border border-ok/30 bg-ok-suave px-4 py-3 text-sm font-semibold text-ok"
             wire:key="confirmacion">
            {{ $confirmacion }}
        </div>
    @endif

    @if ($errors->has('permisos'))
        <div class="mb-5 rounded border border-alto/30 bg-alto-suave px-4 py-3 text-sm font-semibold text-alto">
            {{ $errors->first('permisos') }}
        </div>
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
                    @foreach ($this->permisos() as $permiso)
                        <tr wire:key="permiso-{{ $permiso->value }}">
                            <td class="px-4 py-3">
                                <p class="font-semibold text-slate-900">{{ $permiso->etiqueta() }}</p>
                                <p class="mt-0.5 text-xs text-slate-500">{{ $permiso->explicacion() }}</p>
                            </td>

                            @foreach ($this->roles() as $rol)
                                <td class="px-4 py-3 text-center">
                                    @if ($this->editable($rol, $permiso))
                                        <input
                                            type="checkbox"
                                            class="h-5 w-5 rounded border-slate-300 text-parte3 focus:ring-2 focus:ring-parte3/40"
                                            aria-label="{{ $permiso->etiqueta() }} · {{ $rol->etiqueta() }}"
                                            wire:model="matriz.{{ $rol->value }}.{{ $permiso->value }}"
                                        >
                                    @else
                                        {{-- Clavado: ver Permiso::esIntocable(). --}}
                                        <span
                                            class="font-mono text-xs uppercase tracking-widest {{ $matriz[$rol->value][$permiso->value] ? 'text-parte3' : 'text-slate-300' }}"
                                            title="No se puede cambiar: sin él, esta pantalla se cerraría para siempre."
                                        >
                                            {{ $matriz[$rol->value][$permiso->value] ? 'Siempre' : 'Nunca' }}
                                        </span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
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
