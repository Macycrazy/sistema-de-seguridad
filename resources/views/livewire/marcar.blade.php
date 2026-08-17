{{--
    La pantalla de la puerta. Todo lo que se ve aquí sale de los componentes de /diseno:
    x-campo, x-boton, x-etiqueta y x-tarjeta. Nada de clases sueltas inventadas.

    El foco vive en el campo de la cédula: se teclea, Enter, y el botón que corresponde ya
    está resaltado. El vigilante no debería necesitar el ratón.
--}}
<div class="mx-auto max-w-3xl">

    {{-- Cuántos están dentro. Es el dato que el vigilante mira de reojo. --}}
    <div class="mb-6 flex items-baseline justify-between">
        <h1 class="text-3xl font-bold tracking-tight">Marcar</h1>
        <p class="font-mono text-xs uppercase tracking-widest text-slate-500">
            Dentro ahora:
            <span class="text-base font-bold text-slate-900">{{ $this->dentro }}</span>
        </p>
    </div>

    {{-- Confirmación del último marcaje. Se va sola en cuanto se teclea la siguiente cédula. --}}
    @if ($confirmacion !== '')
        <div class="mb-5 rounded border border-ok/30 bg-ok-suave px-4 py-3 text-sm font-semibold text-ok"
             wire:key="confirmacion">
            {{ $confirmacion }}
        </div>
    @endif

    {{-- EL CAMPO DE LA CÉDULA --}}
    <x-tarjeta parte="1">
        <form wire:submit="buscar">
            {{--
                «live.debounce» busca sola en cuanto se deja de teclear, sin pulsar nada. Los
                400 ms son el rato que se espera: bastante para no consultar en cada tecla, y
                poco para que no se note la espera.

                El formulario se queda igualmente: el lector de carnets termina con un Enter, y
                quien tenga la costumbre de pulsarlo no tiene por qué perderla.
            --}}
            {{--
                El campo solo admite dígitos, y como máximo los de una cédula.

                «inputmode» solo elige el teclado del teléfono: no impide teclear nada. Lo que de
                verdad lo limita son «maxlength» y el «oninput», que borra al instante cualquier
                cosa que no sea un dígito — también lo que se intente pegar.

                Esto es comodidad para quien teclea, NO seguridad: el servidor vuelve a revisarlo
                en Marcaje::exigirCedulaValida(), porque cualquiera puede enviar lo que quiera sin
                pasar por esta pantalla.
            --}}
            <x-campo
                etiqueta="Cédula"
                nombre="cedula"
                tamano="grande"
                placeholder="Solo números"
                autofocus
                autocomplete="off"
                inputmode="numeric"
                pattern="[0-9]*"
                maxlength="{{ $this->maximoDigitos() }}"
                oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, {{ $this->maximoDigitos() }})"
                wire:model.live.debounce.400ms="cedula"
                :error="$errors->first('cedula')"
                ayuda="Teclea la cédula o pasa el carnet: los datos salen solos."
            />
            <button type="submit" class="sr-only">Buscar</button>

            {{-- Señal de que el sistema está mirando. Sin esto, el rato entre dejar de teclear
                 y ver la ficha parece que no pasa nada. --}}
            <p wire:loading.delay.shortest wire:target="cedula"
               class="mt-2 font-mono text-xs uppercase tracking-widest text-slate-400">
                Buscando…
            </p>
        </form>
    </x-tarjeta>

    {{-- QUIÉN ES --}}
    @if ($this->persona)
        {{-- Siempre en bloque: la forma en línea de esta directiva no cierra su etiqueta. --}}
        @php
            $persona = $this->persona;
        @endphp

        <x-tarjeta class="mt-5" wire:key="persona-{{ $persona->id }}">
            <div class="flex items-start gap-5">

                {{-- La foto sale por su ruta, no de una carpeta pública. Si no hay, las
                     iniciales: no se piden imágenes a Internet. --}}
                <div class="flex h-24 w-24 shrink-0 items-center justify-center overflow-hidden rounded bg-slate-100">
                    @if ($persona->tieneFoto())
                        <img src="{{ route('persona.foto', $persona) }}"
                             alt="Foto de {{ $persona->nombre }}"
                             class="h-full w-full object-cover">
                    @else
                        <span class="font-mono text-2xl font-bold text-slate-400">
                            {{ $persona->iniciales() }}
                        </span>
                    @endif
                </div>

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-3">
                        <p class="text-2xl font-bold tracking-tight">{{ $persona->nombre }}</p>
                        <x-etiqueta :tipo="$persona->tipo" />
                        @unless ($persona->activo)
                            <x-etiqueta tipo="inactivo" />
                        @endunless
                    </div>

                    <p class="mt-1 font-mono text-sm text-slate-500">{{ $persona->cedulaConPuntos() }}</p>

                    {{-- Dónde está y desde cuándo. No se guarda en ninguna columna: sale del
                         último asiento, que es la única fuente de verdad de dónde está alguien.

                         Los minutos solo se dicen si entró HOY. A quien se le quedó la entrada
                         de ayer sin salida —el caso que avisa exigirQueElMovimientoTengaSentido—
                         un «lleva 940 minutos dentro» no le dice nada a nadie: lo que hace falta
                         ver es la fecha, para caer en cuenta de que falta marcarle la salida. --}}
                    @php
                        $ultimo = $persona->ultimoMovimiento();
                        $estaDentroAhora = $persona->estaDentro();
                    @endphp

                    <p class="mt-2 text-sm text-slate-600">
                        @if (! $ultimo)
                            Sin movimientos registrados: es la primera vez.
                        @elseif ($estaDentroAhora && $ultimo->ocurrio_en->isToday())
                            <span class="font-semibold text-ok">Dentro</span>
                            desde las {{ $ultimo->ocurrio_en->format('H:i') }},
                            hace {{ (int) abs(now()->diffInMinutes($ultimo->ocurrio_en)) }} min.
                        @elseif ($estaDentroAhora)
                            <span class="font-semibold text-invitado">
                                Dentro desde el {{ $ultimo->ocurrio_en->format('d/m') }}
                                a las {{ $ultimo->ocurrio_en->format('H:i') }}.
                            </span>
                            <span class="block text-slate-500">
                                Quedó sin marcar la salida: márcasela y ya podrá entrar.
                            </span>
                        @else
                            Fuera. Último movimiento:
                            {{ $ultimo->tipo }}
                            {{ $ultimo->ocurrio_en->isToday() ? 'hoy' : 'el '.$ultimo->ocurrio_en->format('d/m') }}
                            a las {{ $ultimo->ocurrio_en->format('H:i') }}.
                        @endif
                    </p>

                    @if ($persona->esTrabajador())
                        {{-- La gerencia va rotulada y no como un texto suelto: el vigilante
                             tiene que poder decir de un vistazo de dónde es quien tiene delante,
                             y un renglón gris sin título no se lee, se adivina.

                             En la base la columna se llama «dependencia»; aquí se rotula
                             «Gerencia», que es como se dice en el CIIP. Renombrar la columna es
                             un cambio de esquema, y eso se habla con las otras dos partes. --}}
                        {{-- Gerencia y piso van juntos: son las dos cosas que el vigilante
                             necesita saber de quien labora aquí. El piso del trabajador es fijo
                             —viene de su ficha— así que se muestra, no se pregunta. --}}
                        <div class="mt-3 flex flex-wrap gap-x-10 gap-y-3">
                            <div>
                                <p class="font-mono text-xs font-semibold uppercase tracking-widest text-slate-500">
                                    Gerencia
                                </p>
                                <p class="mt-0.5 text-lg font-semibold text-slate-900">
                                    {{ $persona->dependencia ?: 'Sin gerencia asignada' }}
                                </p>
                            </div>

                            <div>
                                <p class="font-mono text-xs font-semibold uppercase tracking-widest text-slate-500">
                                    Piso
                                </p>
                                <p class="mt-0.5 font-mono text-lg font-semibold tracking-wide text-slate-900">
                                    {{ $persona->piso ?: '—' }}
                                </p>
                            </div>
                        </div>
                    @else
                        {{-- Del invitado que vuelve se corrigen el motivo y el piso de hoy: la
                             vez anterior pudo venir a otra cosa y a otro sitio. Al invitado el
                             piso se le pregunta SIEMPRE, no se da por sabido.

                             El piso se acomoda solo mientras se teclea —sin espacios y en
                             mayúsculas— igual que la placa y por la misma razón: si la casilla
                             dejara ver «2 - 1» y en la base quedara «2-1», lo que se ve y lo que
                             se guarda no serían lo mismo.

                             OJO: ese «oninput» va pegado a los demás atributos. Un comentario
                             de Blade metido entre los atributos de un <x-...> rompe el análisis
                             de la etiqueta y se come en silencio lo que venga detrás. --}}
                        <div class="mt-3 flex flex-wrap items-start gap-4">
                            <div class="min-w-0 flex-1 basis-64">
                                <x-campo
                                    etiqueta="Motivo de visita"
                                    nombre="motivo"
                                    wire:model="motivo"
                                    :error="$errors->first('motivo')"
                                />
                            </div>

                            <div class="w-28 shrink-0">
                                <x-campo
                                    etiqueta="Piso"
                                    nombre="piso"
                                    wire:model="piso"
                                    autocomplete="off"
                                    placeholder="ej. 2-1"
                                    class="font-mono"
                                    maxlength="{{ \App\Models\Persona::LARGO_PISO }}"
                                    oninput="this.value = this.value.toUpperCase().replace(/\s+/g, '')"
                                    :error="$errors->first('piso')"
                                />
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- El vehículo con el que llega, sea invitado o trabajador: el personal también
                 estaciona aquí. Sale ya escrito el de la última vez, que casi siempre es el
                 mismo; si hoy vino caminando, se vacían las casillas y el asiento de hoy queda
                 sin vehículo sin tocar los de los días anteriores.

                 Va fuera de la fila de la foto para que las casillas tengan el ancho entero de
                 la tarjeta, igual que en el alta. --}}
            <div class="mt-5">
                <x-vehiculo
                    :error="$errors->first('placa')"
                    :error-tipo="$errors->first('tipoVehiculo')"
                    :vehiculos="$this->vehiculos"
                    :trae-hoy="$traeHoy"
                />
            </div>

            {{-- LOS DOS BOTONES --}}
            @if ($persona->activo)
                @php
                    // Solo se puede pulsar el botón que toca: quien ya está dentro no vuelve a
                    // entrar, y quien no ha entrado no puede salir. El otro se apaga de verdad,
                    // con «disabled», y el servidor lo rechaza igual — esconder un botón no es
                    // seguridad.
                    //
                    // Cada botón conserva SIEMPRE su color: el verde significa entrada en todo el
                    // sistema y no se le presta al otro botón. Y los botones nunca se mueven de
                    // sitio: el vigilante los busca por posición, no por color.
                    $realce = 'ring-2 ring-slate-900 ring-offset-2';

                    // Se calculan aquí y se pasan con «:class», que es la forma de dar una
                    // expresión PHP a un componente sin meterla dentro del atributo.
                    //
                    // En el teléfono los dos botones ocupan todo el ancho y van uno debajo del
                    // otro: a 320 px, dos botones en fila quedan tan estrechos que el texto se
                    // parte y el dedo falla. Desde tableta en adelante van en fila.
                    $ancho = 'w-full sm:w-auto';

                    $estaDentro = $this->sugerido === 'salida';

                    // Entró hace poco y ya salió: hay que esperar. Es el único caso en que NO
                    // se puede marcar nada, ni entrada ni salida.
                    $espera = $estaDentro ? null : $this->esperaHasta;

                    $puedeEntrar = ! $estaDentro && $espera === null;
                    $claseEntrada = $ancho.($puedeEntrar ? ' '.$realce : '');
                    $claseSalida = $ancho.($estaDentro ? ' '.$realce : '');
                @endphp

                {{-- LOS DOS BOTONES.

                     Se desplazan con la ficha, como todo lo demás: NO van pegados al borde de
                     abajo. Se probó pegándolos y en el teléfono estorba — la barra tapa lo que
                     hay debajo mientras se desliza, que es justo lo que se está leyendo. --}}
                <div class="mt-6 flex flex-col gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:flex-wrap sm:items-center">
                    <x-boton
                        variante="entrada"
                        tamano="grande"
                        :class="$claseEntrada"
                        wire:click="marcarEntrada"
                        wire:loading.attr="disabled"
                        :disabled="! $puedeEntrar"
                    >ENTRADA</x-boton>

                    <x-boton
                        variante="salida"
                        tamano="grande"
                        :class="$claseSalida"
                        wire:click="marcarSalida"
                        wire:loading.attr="disabled"
                        :disabled="! $estaDentro"
                    >SALIDA</x-boton>

                    <p class="text-sm sm:ml-auto sm:max-w-[16rem] sm:text-right
                              {{ $espera ? 'font-semibold text-invitado' : 'text-slate-500' }}">
                        @if ($estaDentro)
                            Ya tiene la entrada marcada: solo se le puede marcar la salida.
                        @elseif ($espera)
                            Entró hace menos de {{ $this->minutosEntreEntradas() }} minutos.
                            Se le puede marcar otra entrada <strong>a partir de las {{ $espera }}</strong>.
                            {{-- El porqué, en pequeño: sin esta frase el vigilante cree que el
                                 sistema está fallando. --}}
                            <span class="mt-1 block font-normal text-slate-500">
                                El plazo se cuenta desde su entrada anterior, no desde la salida.
                            </span>
                        @else
                            No está dentro: solo se le puede marcar la entrada.
                        @endif
                    </p>
                </div>

                @error('tipo')
                    <p class="mt-3 text-sm font-semibold text-alto">{{ $message }}</p>
                @enderror
            @else
                <p class="mt-6 border-t border-slate-100 pt-5 text-sm font-semibold text-alto">
                    Esta persona está desactivada y no se le puede marcar. Avisa al supervisor.
                </p>
            @endif

            <div class="mt-4">
                <x-boton variante="secundario" tamano="chico" wire:click="limpiar">
                    Cancelar y empezar de nuevo
                </x-boton>
            </div>
        </x-tarjeta>
    @endif

    {{-- INVITADO NUEVO: la cédula no está en el sistema --}}
    @if ($invitadoNuevo)
        <x-tarjeta class="mt-5" wire:key="invitado-nuevo">
            <div class="mb-4 rounded bg-invitado-suave px-4 py-3">
                <p class="flex flex-wrap items-center gap-2 font-semibold text-invitado">
                    <x-etiqueta tipo="invitado" />
                    Esta cédula no está en el sistema: es un invitado.
                </p>
                <p class="mt-1 text-sm text-slate-600">
                    Hacen falta tres datos: nombre, motivo y el piso al que va. La próxima vez que
                    venga, con la cédula bastará —salvo el piso, que se pregunta siempre.
                </p>
            </div>

            <form wire:submit="guardarInvitado" class="space-y-5">
                <div class="max-w-md space-y-5">
                    <x-campo
                        etiqueta="Nombre y apellido"
                        nombre="nombre"
                        wire:model="nombre"
                        autocomplete="off"
                        ayuda="Como aparece en el documento."
                        :error="$errors->first('nombre')"
                    />

                    {{-- El piso al que va es obligatorio, igual que el motivo: es lo que
                         permite saber quién hay en cada piso, que es media razón de ser de
                         este registro. --}}
                    <div class="flex flex-wrap items-start gap-4">
                        <div class="min-w-0 flex-1 basis-56">
                            <x-campo
                                etiqueta="Motivo de visita"
                                nombre="motivo"
                                wire:model="motivo"
                                autocomplete="off"
                                :error="$errors->first('motivo')"
                            />
                        </div>

                        <div class="w-28 shrink-0">
                            <x-campo
                                etiqueta="Piso"
                                nombre="piso"
                                wire:model="piso"
                                autocomplete="off"
                                placeholder="ej. 2-1"
                                class="font-mono"
                                maxlength="{{ \App\Models\Persona::LARGO_PISO }}"
                                oninput="this.value = this.value.toUpperCase().replace(/\s+/g, '')"
                                ayuda="A dónde va."
                                :error="$errors->first('piso')"
                            />
                        </div>
                    </div>
                </div>

                {{-- En el alta no hay nada anotado todavía, así que la clase se elige libre. --}}
                <x-vehiculo
                    :error="$errors->first('placa')"
                    :error-tipo="$errors->first('tipoVehiculo')"
                />

                {{-- En el teléfono, uno debajo del otro y a todo el ancho: en fila, «Guardar y
                     continuar» se parte en dos líneas. Se desplazan con el formulario, igual que
                     los botones de la puerta. --}}
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <x-boton type="submit" class="w-full sm:w-auto" wire:loading.attr="disabled">
                        Guardar y continuar
                    </x-boton>
                    <x-boton variante="secundario" class="w-full sm:w-auto" wire:click="limpiar" type="button">
                        Cancelar
                    </x-boton>
                </div>
            </form>
        </x-tarjeta>
    @endif
</div>
