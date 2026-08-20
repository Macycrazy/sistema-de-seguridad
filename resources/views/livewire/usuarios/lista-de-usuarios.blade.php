{{--
    La pantalla del administrador.

    Las claves las teclea él y se las dicta a su dueño. El sistema no inventa ninguna: para poder
    dictar una clave generada habría que escribirla en la pantalla, y una clave escrita en la
    pantalla de un puesto de vigilancia la lee cualquiera que pase por detrás.

    La vía preferida para quitar a alguien es DESACTIVAR: se le corta el acceso y su rastro sigue
    apuntando a él. Borrar existe para las cuentas creadas por error; anula ese rastro (las FKs son
    «nullOnDelete»), por eso pide confirmación y no es lo primero que se ofrece.
--}}
<div>

    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold tracking-tight">Usuarios</h1>
        </div>

        @unless ($creando)
            <x-boton wire:click="abrirFormulario">Nuevo usuario</x-boton>
        @endunless
    </div>

    {{-- El aviso nunca repite la clave: la escribió quien gestiona, ya la sabe. --}}
    @if ($aviso !== '')
        <x-aviso class="mb-5" wire:key="aviso">{{ $aviso }}</x-aviso>
    @endif

    {{-- Lo que no dejó hacer el servidor: desactivarse a uno mismo, o al último administrador. --}}
    @if ($errors->has('usuario') && ! $creando)
        <x-error class="mb-5">{{ $errors->first('usuario') }}</x-error>
    @endif

    {{-- EL ALTA / LA EDICIÓN --}}
    @if ($creando)
        @php $editando = $editandoId !== null; @endphp
        <x-tarjeta parte="3" :titulo="$editando ? 'Editar usuario' : 'Nuevo usuario'" class="mb-6">
            <form wire:submit="guardar" class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    {{-- Sin autocapitalizar ni autocorregir: el usuario va en minúsculas y tal cual. --}}
                    <x-campo
                        etiqueta="Usuario"
                        nombre="usuario"
                        autofocus
                        autocomplete="off"
                        autocapitalize="none"
                        autocorrect="off"
                        spellcheck="false"
                        maxlength="40"
                        wire:model="usuario"
                        :error="$errors->first('usuario')"
                    />

                    <x-campo
                        etiqueta="Nombre y apellido"
                        nombre="nombre"
                        autocomplete="off"
                        maxlength="120"
                        wire:model="nombre"
                        :error="$errors->first('nombre')"
                    />

                    <x-campo
                        etiqueta="Cédula"
                        nombre="cedula"
                        autocomplete="off"
                        inputmode="numeric"
                        maxlength="9"
                        oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                        ayuda="Opcional."
                        wire:model="cedula"
                        :error="$errors->first('cedula')"
                    />

                    {{-- Rol y clave solo al crear: al editar, cada uno tiene su propio botón en la
                         fila (Cambiar rol / Cambio de clave). --}}
                    @unless ($editando)
                        <x-selector
                            etiqueta="Rol"
                            nombre="rol"
                            :opciones="$this->roles"
                            wire:model="rol"
                            :error="$errors->first('rol')"
                        />

                        <x-campo
                            etiqueta="Clave"
                            nombre="clave"
                            type="password"
                            autocomplete="new-password"
                            ayuda="Mínimo {{ \App\Services\GestionDeUsuarios::MINIMO_DE_LA_CLAVE }} caracteres."
                            wire:model="clave"
                            :error="$errors->first('clave')"
                        />
                    @endunless
                </div>

                <div class="flex items-center gap-3">
                    <x-boton type="submit">{{ $editando ? 'Guardar' : 'Crear' }}</x-boton>
                    <x-boton type="button" variante="secundario" wire:click="cerrarFormulario">
                        Cancelar
                    </x-boton>
                    @unless ($editando)
                        <p class="text-sm text-slate-500">
                            Con esa clave entra. Si quiere una suya, la cambia él desde su nombre.
                        </p>
                    @endunless
                </div>
            </form>
        </x-tarjeta>
    @endif

    {{-- LA LISTA --}}
    <div class="overflow-hidden rounded border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left font-mono text-xs uppercase tracking-widest text-slate-500">
                        <th scope="col" class="px-4 py-3 font-semibold">Usuario</th>
                        <th scope="col" class="px-4 py-3 font-semibold">Nombre</th>
                        <th scope="col" class="px-4 py-3 font-semibold">Cédula</th>
                        <th scope="col" class="px-4 py-3 font-semibold">Rol</th>
                        <th scope="col" class="px-4 py-3 font-semibold">Estado</th>
                        <th scope="col" class="px-4 py-3 text-right font-semibold">Acciones</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100">
                    @foreach ($this->usuarios as $fila)
                        <tr wire:key="usuario-{{ $fila->id }}" @class(['bg-slate-50/60' => ! $fila->activo])>
                            <td class="px-4 py-3 font-mono text-slate-900">{{ $fila->usuario }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $fila->nombre }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-slate-500">
                                {{ $fila->cedula ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-slate-700">{{ $fila->rol->etiqueta() }}</td>
                            <td class="px-4 py-3">
                                @if ($fila->activo)
                                    <span class="font-mono text-xs uppercase tracking-widest text-slate-500">Activo</span>
                                @else
                                    <x-etiqueta tipo="inactivo" />
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap items-center justify-end gap-2">
                                    {{--
                                        A quien tiene un rol por encima del tuyo no se le dibujan
                                        botones. Es cortesía: el servicio corta igual a quien mande
                                        la acción sin pasar por aquí.
                                    --}}
                                    @if ($this->puedeGestionar($fila))
                                        <x-boton
                                            variante="secundario"
                                            tamano="chico"
                                            wire:click="editar({{ $fila->id }})"
                                        >
                                            Editar
                                        </x-boton>

                                        <x-boton
                                            variante="secundario"
                                            tamano="chico"
                                            wire:click="abrirCambioDeClave({{ $fila->id }})"
                                        >
                                            Cambio de clave
                                        </x-boton>

                                        <x-boton
                                            variante="secundario"
                                            tamano="chico"
                                            wire:click="abrirCambioDeRol({{ $fila->id }})"
                                        >
                                            Cambiar rol
                                        </x-boton>

                                        @if ($fila->activo)
                                            <x-boton
                                                variante="secundario"
                                                tamano="chico"
                                                wire:click="desactivar({{ $fila->id }})"
                                            >
                                                Desactivar
                                            </x-boton>
                                        @else
                                            <x-boton
                                                variante="secundario"
                                                tamano="chico"
                                                wire:click="reactivar({{ $fila->id }})"
                                            >
                                                Reactivar
                                            </x-boton>
                                        @endif

                                        {{-- Borrar de verdad: el histórico no se cae (las FKs se
                                             anulan), pero se pierde el «quién» de lo que hizo. Por
                                             eso pide confirmación y va aparte. --}}
                                        <x-boton
                                            variante="peligro"
                                            tamano="chico"
                                            wire:click="eliminar({{ $fila->id }})"
                                            wire:confirm="¿Borrar a {{ $fila->nombre }}? Se pierde su rastro en la auditoría. Si solo quieres quitarle el acceso, usa Desactivar."
                                        >
                                            Borrar
                                        </x-boton>
                                    @else
                                        <span class="font-mono text-xs uppercase tracking-widest text-slate-400">
                                            Fuera de tu alcance
                                        </span>
                                    @endif
                                </div>
                            </td>
                        </tr>

                        {{-- El campo para teclearle la clave, debajo de su propia fila. --}}
                        @if ($cambiandoClaveA === $fila->id)
                            <tr wire:key="cambio-clave-{{ $fila->id }}" class="bg-parte3-suave/40">
                                <td colspan="6" class="px-4 py-4">
                                    <form wire:submit="guardarCambioDeClave" class="flex flex-wrap items-end gap-3">
                                        <div class="w-full sm:w-72">
                                            <x-campo
                                                etiqueta="Clave nueva para {{ $fila->nombre }}"
                                                nombre="clave-nueva-{{ $fila->id }}"
                                                type="password"
                                                autofocus
                                                autocomplete="new-password"
                                                ayuda="Mínimo {{ \App\Services\GestionDeUsuarios::MINIMO_DE_LA_CLAVE }} caracteres."
                                                wire:model="claveNueva"
                                                :error="$errors->first('claveNueva')"
                                            />
                                        </div>

                                        <div class="flex items-center gap-2 pb-6">
                                            <x-boton type="submit" tamano="chico">Guardar</x-boton>
                                            <x-boton
                                                type="button"
                                                variante="secundario"
                                                tamano="chico"
                                                wire:click="cerrarCambioDeClave"
                                            >
                                                Cancelar
                                            </x-boton>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        @endif

                        {{-- El selector de rol, debajo de su propia fila. --}}
                        @if ($cambiandoRolA === $fila->id)
                            <tr wire:key="cambio-rol-{{ $fila->id }}" class="bg-parte3-suave/40">
                                <td colspan="6" class="px-4 py-4">
                                    <form wire:submit="guardarCambioDeRol" class="flex flex-wrap items-end gap-3">
                                        <div class="w-full sm:w-72">
                                            <x-selector
                                                etiqueta="Rol de {{ $fila->nombre }}"
                                                nombre="rol-nuevo-{{ $fila->id }}"
                                                :opciones="$this->roles"
                                                ayuda="Solo puedes darle un rol que alcance el tuyo."
                                                wire:model="rolNuevo"
                                                :error="$errors->first('rol')"
                                            />
                                        </div>

                                        <div class="flex items-center gap-2 pb-6">
                                            <x-boton type="submit" tamano="chico">Guardar</x-boton>
                                            <x-boton
                                                type="button"
                                                variante="secundario"
                                                tamano="chico"
                                                wire:click="cerrarCambioDeRol"
                                            >
                                                Cancelar
                                            </x-boton>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
