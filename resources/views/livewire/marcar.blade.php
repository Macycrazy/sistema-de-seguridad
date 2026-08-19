{{--
    La pantalla de la puerta. Todo lo que se ve aquí sale de los componentes de /diseno:
    x-campo, x-boton, x-etiqueta y x-tarjeta. Nada de clases sueltas inventadas.

    El foco vive en el campo de la cédula: se teclea, Enter, y el botón que corresponde ya
    está resaltado. El vigilante no debería necesitar el ratón.
--}}
<div class="mx-auto max-w-3xl">

    {{-- Quién hay dentro. Es el dato que el vigilante mira de reojo, y va separado en
         trabajadores e invitados porque en una emergencia no valen lo mismo: a los de casa se
         les localiza por su dependencia, y a los invitados no los conoce nadie —hay que ir a
         buscarlos al piso que visitaban—.

         Cada uno con el color que ya significa lo suyo en todo el sistema: el azul de la parte 1
         para el personal, el ámbar para el invitado. --}}
    <div class="mb-6 flex flex-wrap items-baseline justify-between gap-x-6 gap-y-2">
        <h1 class="text-3xl font-bold tracking-tight">Marcar</h1>

        <dl class="flex items-baseline gap-5">
            <div class="flex items-baseline gap-2">
                <dt class="font-mono text-xs uppercase tracking-widest text-slate-500">Trabajadores</dt>
                <dd class="text-xl font-bold text-parte1">{{ $this->dentroPorTipo['trabajador'] }}</dd>
            </div>

            <div class="flex items-baseline gap-2">
                <dt class="font-mono text-xs uppercase tracking-widest text-slate-500">Invitados</dt>
                <dd class="text-xl font-bold text-invitado">{{ $this->dentroPorTipo['invitado'] }}</dd>
            </div>
        </dl>
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
        {{--
            Escanear el carnet con la cámara va ARRIBA DEL TODO, antes del campo: es la forma más
            rápida en la puerta, y ponerlo abajo confundía. Lee el QR, lo verifica contra el sistema
            de carnets y trae la ficha. La cámara solo funciona por HTTPS —por eso el puesto se sirve
            así—; el lector va empaquetado (resources/js/app.js), sin CDN.
        --}}
        <div x-data="escanerCarnet($wire)" class="mb-4 border-b border-slate-100 pb-4">
            <div x-show="!abierto">
                <x-boton type="button" x-on:click="abrir()" class="w-full">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                         stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
                        <rect x="3" y="6.5" width="18" height="13" rx="2.5"/>
                        <circle cx="12" cy="13" r="3.2"/>
                        <path d="M9 6.5 10.2 4h3.6L15 6.5"/>
                    </svg>
                    Escanear carnet con la cámara
                </x-boton>
                <p class="mt-2 text-center font-mono text-xs uppercase tracking-widest text-slate-400">
                    o teclea la cédula abajo
                </p>
            </div>

            {{-- El visor: grande, para que se vea bien. Sin marco oscuro por encima —la imagen va
                 clara y entera—; solo una guía fina de enfoque. Vertical y alto (ocupa casi toda la
                 pantalla del teléfono), con el mensaje sobre un degradado suave abajo. --}}
            <div x-show="abierto" x-cloak>
                <div class="relative w-full overflow-hidden rounded-xl bg-slate-100"
                     style="aspect-ratio: 3 / 4; max-height: 78vh">
                    <video x-ref="video" playsinline muted
                           class="absolute inset-0 h-full w-full object-cover"></video>
                    <canvas x-ref="canvas" class="hidden"></canvas>

                    {{-- Guía de enfoque suave: un marco claro y fino, sin oscurecer nada. --}}
                    <div class="pointer-events-none absolute inset-4 rounded-2xl border-2 border-white/70"></div>

                    <p x-text="mensaje"
                       class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/60 to-transparent px-4 pb-4 pt-12
                              text-center font-mono text-sm font-semibold uppercase tracking-widest text-white"></p>
                </div>

                <x-boton type="button" variante="secundario" x-on:click="cerrar()" class="mt-3 w-full">
                    Cerrar cámara
                </x-boton>
            </div>
        </div>

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
            {{--
                La letra va DELANTE del número, como en el documento y como se dice en voz alta:
                «uve doce millones…». Es un desplegable y no algo que se teclee, para que el
                vigilante solo escoja.

                Cambiarla vuelve a buscar: el mismo número con otra letra es otra persona, así que
                la ficha que hubiera en pantalla deja de valer en cuanto se toca.
            --}}
            {{-- Se alinean por ARRIBA y no por abajo: debajo del campo de la cédula va su renglón
                 de ayuda, así que alineando por abajo la casilla de la letra quedaba un renglón
                 más baja que la de al lado.

                 Las dos casillas miden lo mismo de alto —h-16— para que la fila sea una fila y no
                 dos cosas puestas juntas. Y el rótulo va con «tracking-tight» porque
                 «NACIONALIDAD» es larga: con el espaciado de los demás se partiría en dos
                 renglones y volvería a descuadrar la fila. --}}
            <div class="flex items-start gap-3">
                <div class="w-24 shrink-0">
                    <label for="nacionalidad"
                           class="mb-1.5 block font-mono text-xs font-semibold uppercase tracking-tight text-slate-500">
                        Nacionalidad
                    </label>

                    <select id="nacionalidad" name="nacionalidad" wire:model.live="nacionalidad"
                            class="block h-16 w-full rounded border-2 border-parte1 bg-white px-2 text-center
                                   font-mono text-2xl font-semibold text-slate-900
                                   focus:border-parte1 focus:outline-none focus:ring-4 focus:ring-parte1/25">
                        @foreach (\App\Models\Persona::NACIONALIDADES as $letra => $nombre)
                            <option value="{{ $letra }}" title="{{ $nombre }}">{{ $letra }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="min-w-0 flex-1">
            <x-campo
                etiqueta="Cédula"
                nombre="cedula"
                tamano="puerta"
                autofocus
                autocomplete="off"
                inputmode="numeric"
                pattern="[0-9]*"
                maxlength="{{ $this->maximoDigitos() }}"
                oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, {{ $this->maximoDigitos() }})"
                wire:model.live.debounce.400ms="cedula"
                :error="$errors->first('cedula')"
                ayuda="Introduce la cédula"
            />
                </div>
            </div>

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
            {{-- Dónde está y desde cuándo. No se guarda en ninguna columna: sale del último
                 asiento, que es la única fuente de verdad de dónde está alguien.

                 Los minutos solo se dicen si entró HOY. A quien se le quedó la entrada de ayer
                 sin salida —el caso que avisa exigirQueElMovimientoTengaSentido— un «lleva 940
                 minutos dentro» no le dice nada a nadie: lo que hace falta ver es la fecha, para
                 caer en cuenta de que falta marcarle la salida. --}}
            @php
                $ultimo = $persona->ultimoMovimiento();
                $estaDentroAhora = $persona->estaDentro();
                $dentroDeHoy = $estaDentroAhora && $ultimo?->ocurrio_en->isToday();
            @endphp

            <div class="flex items-start gap-4 sm:gap-5">

                {{-- La foto sale por su ruta, no de una carpeta pública. Si no hay, las
                     iniciales: no se piden imágenes a Internet.

                     Redonda y sobre el azul de la parte 1: en el teléfono es lo primero que se
                     mira, y un cuadro gris se confundía con un hueco por rellenar. --}}
                <div class="relative flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-full
                            border-2 border-slate-200 bg-parte1-suave">
                    {{-- Las iniciales van debajo; la foto —directo del carnets— encima. Si el carnets
                         no tiene foto de esa persona, la imagen falla al cargar y se quita sola,
                         dejando ver las iniciales. Se pide para el trabajador (el invitado no tiene
                         carnet), o si ya hubiera una copia local. --}}
                    <span class="font-mono text-xl font-bold text-parte1">
                        {{ $persona->iniciales() }}
                    </span>
                    @if ($persona->esTrabajador() || $persona->tieneFoto())
                        <img src="{{ route('persona.foto', $persona) }}"
                             alt="Foto de {{ $persona->nombre }}"
                             onerror="this.remove()"
                             class="absolute inset-0 h-full w-full object-cover">
                    @endif
                </div>

                <div class="min-w-0 flex-1">
                    <p class="text-2xl font-bold leading-tight tracking-tight">{{ $persona->nombre }}</p>
                    {{-- Con su letra delante, como en el documento: es lo que el vigilante tiene
                         en la mano para comprobar que es quien dice ser. --}}
                    <p class="mt-0.5 font-mono text-sm text-slate-500">{{ $persona->cedulaCompleta() }}</p>

                    {{-- Quién es y dónde está, en una sola fila de etiquetas: es justo lo que se
                         mira antes de pulsar, y así no hay que leer ningún renglón entero. --}}
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <x-etiqueta :tipo="$persona->tipo" />

                        @unless ($persona->activo)
                            <x-etiqueta tipo="inactivo" />
                        @endunless

                        @if ($dentroDeHoy)
                            {{-- La hora a la que entró, no los minutos que lleva. Un «54 min» hay
                                 que restarlo mentalmente para saber de cuándo se habla; «08:12»
                                 se compara de un vistazo con el reloj de la pared. En 24 h, como
                                 el resto del sistema. --}}
                            <x-etiqueta tipo="entrada">
                                Dentro · {{ $ultimo->ocurrio_en->format(\App\Models\Movimiento::FORMATO_HORA) }}
                            </x-etiqueta>
                        @elseif ($estaDentroAhora)
                            {{-- Se le quedó la entrada de otro día sin salida. Va en rojo porque
                                 es algo que hay que arreglar, no un estado normal. --}}
                            <x-etiqueta tipo="inactivo">
                                Dentro desde el {{ $ultimo->ocurrio_en->format('d/m') }}
                            </x-etiqueta>
                        @elseif ($ultimo)
                            <x-etiqueta tipo="salida">Fuera</x-etiqueta>
                        @endif
                    </div>
                </div>
            </div>

            @if ($persona->esTrabajador())
                {{-- Toda la ficha en una rejilla rotulada y a lo ancho de la tarjeta. Cada dato
                     con su título encima: un renglón gris sin rótulo no se lee, se adivina, y en
                     la puerta se marca de pie y apurado.

                     En la base la columna se llama «dependencia»; aquí se rotula «Gerencia», que
                     es como se dice en el CIIP. Renombrar la columna es un cambio de esquema, y
                     eso se habla con las otras dos partes. --}}
                <dl class="mt-4 grid grid-cols-2 gap-px overflow-hidden rounded border border-slate-200 bg-slate-200">
                    <div class="col-span-2 bg-white px-3 py-2">
                        <dt class="font-mono text-[0.625rem] font-semibold uppercase tracking-widest text-slate-500">
                            Gerencia
                        </dt>
                        <dd class="mt-0.5 font-semibold text-slate-900">
                            {{ $persona->dependencia ?: 'Sin gerencia asignada' }}
                        </dd>
                    </div>

                    {{-- El piso del trabajador es fijo —viene de su ficha— así que se muestra,
                         no se pregunta. --}}
                    <div class="bg-white px-3 py-2">
                        <dt class="font-mono text-[0.625rem] font-semibold uppercase tracking-widest text-slate-500">
                            Piso
                        </dt>
                        <dd class="mt-0.5 font-mono font-semibold tracking-wide text-slate-900">
                            {{ $persona->piso ?: '—' }}
                        </dd>
                    </div>

                    <div class="bg-white px-3 py-2">
                        <dt class="font-mono text-[0.625rem] font-semibold uppercase tracking-widest text-slate-500">
                            Estado
                        </dt>
                        <dd class="mt-0.5 font-semibold {{ $persona->activo ? 'text-slate-900' : 'text-alto' }}">
                            {{ $persona->activo ? 'Activo' : 'Inactivo' }}
                        </dd>
                    </div>

                    {{-- La entrada Y la salida juntas, no solo la última.

                         Con un solo renglón —«salida, 09:03»— hay que adivinar a qué hora entró,
                         que es justo la otra mitad de lo que el vigilante quiere saber. Las dos
                         una debajo de otra se leen de un golpe: entró a las 08:12 y salió a las
                         09:03.

                         Cada una es la ÚLTIMA de su clase, así que pueden ser de días distintos:
                         por eso se dice la fecha cuando no es de hoy. --}}
                    <div class="col-span-2 bg-white px-3 py-2">
                        <dt class="font-mono text-[0.625rem] font-semibold uppercase tracking-widest text-slate-500">
                            Últimos movimientos
                        </dt>

                        @php
                            $ultimaEntrada = $persona->ultimaEntrada();
                            $ultimaSalida = $persona->ultimaSalida();
                        @endphp

                        <dd class="mt-1 space-y-0.5 text-slate-900">
                            @if (! $ultimo)
                                Sin movimientos: es la primera vez que se le marca.
                            @else
                                <span class="flex items-baseline gap-2">
                                    <span class="w-16 shrink-0 font-mono text-[0.625rem] font-bold uppercase tracking-widest text-ok">
                                        Entrada
                                    </span>
                                    <span>
                                        @if ($ultimaEntrada)
                                            {{ $ultimaEntrada->ocurrio_en->isToday()
                                                ? 'hoy'
                                                : 'el '.$ultimaEntrada->ocurrio_en->format('d/m') }}
                                            a las {{ $ultimaEntrada->ocurrio_en->format(\App\Models\Movimiento::FORMATO_HORA) }}
                                        @else
                                            <span class="text-slate-400">sin registrar</span>
                                        @endif
                                    </span>
                                </span>

                                <span class="flex items-baseline gap-2">
                                    <span class="w-16 shrink-0 font-mono text-[0.625rem] font-bold uppercase tracking-widest text-slate-500">
                                        Salida
                                    </span>
                                    <span>
                                        @if ($ultimaSalida)
                                            {{ $ultimaSalida->ocurrio_en->isToday()
                                                ? 'hoy'
                                                : 'el '.$ultimaSalida->ocurrio_en->format('d/m') }}
                                            a las {{ $ultimaSalida->ocurrio_en->format(\App\Models\Movimiento::FORMATO_HORA) }}
                                        @else
                                            <span class="text-slate-400">sin registrar</span>
                                        @endif
                                    </span>
                                </span>

                                @if ($estaDentroAhora && ! $dentroDeHoy)
                                    <span class="mt-1 block text-sm font-semibold text-alto">
                                        Quedó sin marcar la salida: márcasela y ya podrá entrar.
                                    </span>
                                @endif
                            @endif
                        </dd>
                    </div>
                </dl>
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
                <div class="mt-4 space-y-4">
                    <x-campo
                        etiqueta="Motivo de visita"
                        nombre="motivo"
                        wire:model="motivo"
                        :error="$errors->first('motivo')"
                    />

                    <x-piso
                        :mapa="$this->oficinasPorPiso"
                        :nombres="$this->nombresDePiso"
                        :nivel="$nivel"
                        :piso="$piso"
                        :a-mano="$pisoAMano"
                        :error="$errors->first('piso')"
                    />
                </div>
            @endif

            {{-- El vehículo con el que llega, sea invitado o trabajador: el personal también
                 estaciona aquí. Sale ya escrito el de la última vez, que casi siempre es el
                 mismo; si hoy vino caminando, se vacían las casillas y el asiento de hoy queda
                 sin vehículo sin tocar los de los días anteriores.

                 Va fuera de la fila de la foto para que las casillas tengan el ancho entero de
                 la tarjeta, igual que en el alta.

                 CUANDO LO QUE TOCA ES LA SALIDA no se pregunta nada: se sale en lo mismo con lo
                 que se entró, y el servidor lo exige. Ofrecer una lista donde solo una respuesta
                 vale es invitar al vigilante a equivocarse y luego decirle que no. Se enseña lo
                 que entró y ya. --}}
            <div class="mt-5">
                @if ($this->sugerido === 'salida')
                    <div class="rounded border border-slate-200 p-4">
                        <p class="font-mono text-xs font-semibold uppercase tracking-widest text-slate-500">
                            Entró en
                        </p>

                        @php $entrada = $persona->ultimaEntrada(); @endphp

                        <p class="mt-1 text-slate-900">
                            @if ($entrada?->tieneVehiculo())
                                <span class="font-semibold">{{ $entrada->vehiculo()->etiquetaTipo() }}</span>
                                <span class="font-mono tracking-wide">· {{ $entrada->placa }}</span>
                            @else
                                A pie
                            @endif
                        </p>

                        <p class="mt-1 text-sm text-slate-500">
                            Sale en lo mismo con lo que entró.
                        </p>
                    </div>
                @else
                    <x-vehiculo
                        :error="$errors->first('placa')"
                        :error-tipo="$errors->first('tipoVehiculo')"
                        :vehiculos="$this->vehiculos"
                        :trae-hoy="$traeHoy"
                    />
                @endif
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

                    // Está dentro pero acaba de entrar: la salida todavía no. Son dos plazos
                    // distintos y no tienen por qué valer igual.
                    $esperaSalida = $estaDentro ? $this->esperaSalidaHasta : null;

                    $puedeEntrar = ! $estaDentro && $espera === null;
                    $puedeSalir = $estaDentro && $esperaSalida === null;

                    $claseEntrada = $ancho.($puedeEntrar ? ' '.$realce : '');
                    $claseSalida = $ancho.($puedeSalir ? ' '.$realce : '');
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
                        :disabled="! $puedeSalir"
                    >SALIDA</x-boton>

                    <p class="text-sm sm:ml-auto sm:max-w-[16rem] sm:text-right
                              {{ $espera || $esperaSalida ? 'font-semibold text-invitado' : 'text-slate-500' }}">
                        @if ($esperaSalida)
                            Entró hace menos de {{ $this->minutosEntreEntradaYSalida() }} minutos.
                            Se le puede marcar la salida <strong>a partir de las {{ $esperaSalida }}</strong>.
                        @elseif ($estaDentro)
                            Ya tiene la entrada marcada: solo se le puede marcar la salida.
                        @elseif ($espera)
                            {{-- La frase la redacta el servicio: hay dos plazos —uno desde su
                                 entrada anterior y otro desde su salida— y solo él sabe cuál
                                 manda ahora. Si la armara la pantalla, el vigilante leería el
                                 motivo equivocado la mitad de las veces. --}}
                            {{ $this->motivoEspera }}
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
            {{-- El aviso se puede cerrar con la equis. Al vigilante que ya entendió de qué va,
                 solo le quita sitio a las casillas que tiene que rellenar — y en un teléfono ese
                 sitio se nota. Vuelve a salir con cada cédula nueva, porque entonces es
                 información y no un estorbo. --}}
            @if ($avisoInvitado)
                <div class="mb-4 flex items-start gap-3 rounded bg-invitado-suave px-4 py-3">
                    <div class="min-w-0 flex-1">
                        <p class="flex flex-wrap items-center gap-2 font-semibold text-invitado">
                            <x-etiqueta tipo="invitado" />
                            Esta cédula no está en el sistema: es un invitado.
                        </p>
                        <p class="mt-1 text-sm text-slate-600">
                            Hacen falta tres datos: nombre, motivo y el piso al que va. La próxima vez
                            que venga, con la cédula bastará —salvo el piso, que se pregunta siempre.
                        </p>
                    </div>

                    {{-- Cerrar el aviso NO cancela el alta: solo esconde el texto. Por eso no
                         dice «cancelar» ni se parece al botón que sí lo hace. --}}
                    <button type="button"
                            wire:click="$set('avisoInvitado', false)"
                            aria-label="Cerrar el aviso"
                            title="Cerrar el aviso"
                            class="-mr-1 -mt-1 shrink-0 rounded p-2 text-xl leading-none text-invitado
                                   transition hover:bg-invitado/10
                                   focus:outline-none focus-visible:ring-4 focus-visible:ring-invitado/25">
                        &times;
                    </button>
                </div>
            @endif

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
                    <x-campo
                        etiqueta="Motivo de visita"
                        nombre="motivo"
                        wire:model="motivo"
                        autocomplete="off"
                        :error="$errors->first('motivo')"
                    />

                    <x-piso
                        :mapa="$this->oficinasPorPiso"
                        :nombres="$this->nombresDePiso"
                        :nivel="$nivel"
                        :piso="$piso"
                        :a-mano="$pisoAMano"
                        :error="$errors->first('piso')"
                    />
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
