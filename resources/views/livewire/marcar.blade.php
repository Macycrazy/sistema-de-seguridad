{{--
    La pantalla de la puerta. Todo lo que se ve aquí sale de los componentes de /diseno:
    x-campo, x-boton, x-etiqueta y x-tarjeta. Nada de clases sueltas inventadas.

    El foco vive en el campo de la cédula: se teclea, Enter, y el botón que corresponde ya
    está resaltado. El vigilante no debería necesitar el ratón.
--}}
<div class="mx-auto max-w-3xl" x-data="{ ayuda: false }">

    {{-- Quién hay dentro. Es el dato que el vigilante mira de reojo, y va separado en
         trabajadores e invitados porque en una emergencia no valen lo mismo: a los de casa se
         les localiza por su dependencia, y a los invitados no los conoce nadie —hay que ir a
         buscarlos al piso que visitaban—.

         Cada uno con el color que ya significa lo suyo en todo el sistema: el azul de la parte 1
         para el personal, el ámbar para el invitado. --}}
    <div class="mb-6 flex flex-wrap items-baseline justify-between gap-x-6 gap-y-2">
        <div class="flex items-center gap-2.5">
            <h1 class="text-3xl font-bold tracking-tight">Marcar</h1>
            {{-- El botón de ayuda: abre un recuadro con los pasos. Para el vigilante nuevo, o el
                 que se traba. No estorba: está cerrado hasta que se toca. --}}
            <button type="button" x-on:click="ayuda = true"
                    class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full border border-slate-300
                           text-sm font-bold text-slate-500 transition hover:bg-slate-50 hover:text-slate-800"
                    aria-label="¿Cómo se usa?">?</button>
        </div>

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
                <div class="relative w-full overflow-hidden rounded-xl bg-slate-100 cursor-pointer"
                     x-on:click="enfocar($event)"
                     style="aspect-ratio: 3 / 4; max-height: 78vh">
                    <video x-ref="video" playsinline muted
                           class="absolute inset-0 h-full w-full object-cover"></video>
                    <canvas x-ref="canvas" class="hidden"></canvas>

                    <style>
                        @keyframes laser {
                            0% { top: -2px; opacity: 0; }
                            10% { opacity: 1; }
                            90% { opacity: 1; }
                            100% { top: 100%; opacity: 0; }
                        }
                    </style>

                    {{-- Barra de herramientas (Flash, Zoom, Cambiar cámara) --}}
                    <div class="absolute inset-x-0 top-0 z-20 flex items-center justify-between bg-gradient-to-b from-black/60 to-transparent p-4">
                        {{-- Zoom Slider --}}
                        <div x-show="soportaZoom" class="flex items-center gap-2" x-cloak>
                            <span class="font-mono text-[0.625rem] font-bold uppercase tracking-widest text-white shadow-black drop-shadow-md">Zoom</span>
                            <input type="range" x-model="zoomActual" :min="zoomMin" :max="zoomMax" step="0.1"
                                   @input="aplicarZoomManual"
                                   class="w-24 accent-parte1">
                        </div>

                        <div class="ml-auto flex items-center gap-3">
                            {{-- Botón Linterna --}}
                            <button type="button" x-show="soportaLinterna" @click="toggleLinterna()" x-cloak
                                    class="flex h-10 w-10 items-center justify-center rounded-full bg-black/40 text-white backdrop-blur-md transition hover:bg-black/60"
                                    :class="linternaEncendida ? '!bg-yellow-400 !text-black shadow-[0_0_15px_rgba(250,204,21,0.5)]' : ''">
                                {{-- SVG linterna apagada --}}
                                <svg x-show="!linternaEncendida" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                                {{-- SVG linterna encendida --}}
                                <svg x-show="linternaEncendida" class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                            </button>

                            {{-- Botón Cambiar Cámara --}}
                            <button type="button" x-show="camaras.length > 1" @click="cambiarCamara()" x-cloak
                                    class="flex h-10 w-10 items-center justify-center rounded-full bg-black/40 text-white backdrop-blur-md transition hover:bg-black/60">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                            </button>
                        </div>
                    </div>

                    {{-- Diseño de Escáner Láser Tecnológico --}}
                    <div class="pointer-events-none absolute inset-0 z-10 flex flex-col items-center justify-center overflow-hidden">
                        {{-- El shadow con 9999px oscurece todo alrededor del cuadro central --}}
                        <div class="relative h-60 w-60 rounded-xl border border-white/20 shadow-[0_0_0_9999px_rgba(0,0,0,0.5)]">
                            {{-- Esquinas de mira --}}
                            <div class="absolute -left-1 -top-1 h-8 w-8 border-l-4 border-t-4 border-parte1 rounded-tl-lg"></div>
                            <div class="absolute -right-1 -top-1 h-8 w-8 border-r-4 border-t-4 border-parte1 rounded-tr-lg"></div>
                            <div class="absolute -bottom-1 -left-1 h-8 w-8 border-b-4 border-l-4 border-parte1 rounded-bl-lg"></div>
                            <div class="absolute -bottom-1 -right-1 h-8 w-8 border-b-4 border-r-4 border-parte1 rounded-br-lg"></div>
                            
                            {{-- Línea láser animada --}}
                            <div class="absolute left-0 w-full h-[2px] bg-white shadow-[0_0_12px_3px_rgba(255,255,255,0.7)]"
                                 style="animation: laser 2.5s ease-in-out infinite;"></div>
                        </div>
                    </div>

                    {{-- Pulso de enfoque animado al tocar --}}
                    <div x-show="mostrandoCuadro"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 scale-50"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="transition ease-out duration-500"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-150"
                         :style="{ top: topCuadro, left: leftCuadro }"
                         x-cloak
                         class="pointer-events-none absolute h-12 w-12 flex items-center justify-center rounded-full border-[1.5px] border-white/80 bg-white/10 shadow-[0_0_10px_rgba(255,255,255,0.5)] z-30">
                         <div class="h-1.5 w-1.5 rounded-full bg-white/90 shadow-sm"></div>
                    </div>

                    <p x-text="mensaje"
                       class="absolute inset-x-0 bottom-0 z-20 bg-gradient-to-t from-black/80 to-transparent px-4 pb-4 pt-12
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

    {{-- Pantalla en blanco: la guía sola. Mientras no haya nadie en pantalla ni se esté anotando
         un invitado, se dice qué hacer, en el hueco donde después saldrá la persona. Así el
         vigilante nuevo no se queda mirando una pantalla que no le dice nada. --}}
    @if (! $this->persona && ! $invitadoNuevo)
        <div class="mt-5 rounded border border-dashed border-slate-300 bg-white px-4 py-8 text-center">
            <p class="text-base font-semibold text-slate-700">Escanea el carnet o escribe la cédula</p>
            <p class="mt-1 text-sm text-slate-500">Aquí saldrá la persona, con su foto, para marcarle la entrada o la salida.</p>
        </div>
    @endif

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

                {{-- EL VEHÍCULO, EN EL MISMO GESTO.

                     Va justo encima de los botones: se ve al ir a marcar, sin buscarlo. Es
                     opcional y de un toque —quien viene a pie no toca nada y la pantalla se
                     comporta como siempre—, porque aquí hay cola detrás.

                     Al entrar se elige con qué viene; al salir, qué se lleva. Nunca las dos, para
                     que no haya dos cosas que decidir a la vez. --}}
                @if ($puedeEntrar)
                    <div class="mt-5 border-t border-slate-100 pt-5">
                        <p class="font-mono text-xs font-bold uppercase tracking-widest text-slate-500">
                            ¿Cómo entra?
                        </p>

                        <div class="mt-2.5 flex flex-wrap gap-2">
                            {{-- «A pie» tiene su botón y no es «no tocar nada»: al lado de unos
                                 botones con placas, lo que no se toca no se lee como una opción
                                 sino como algo que falta por hacer. Va primero y viene elegido,
                                 que es como llega la mayoría. --}}
                            <button type="button" wire:click="elegirVehiculo('')"
                                    class="flex items-center gap-2 rounded border px-3 py-2 text-sm transition
                                           {{ $vehiculoEntrada === ''
                                              ? 'border-parte1 bg-parte1-suave font-semibold text-parte1'
                                              : 'border-slate-300 text-slate-600 hover:bg-slate-50' }}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                     stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4">
                                    <circle cx="12" cy="4.5" r="2"/><path d="M10 21l1.5-5-2.5-2.5V9l3-1.5 2.5 3 2.5 1"/><path d="M9 12.5 7 15m6.5 1L15 21"/>
                                </svg>
                                A pie
                            </button>

                            {{-- Los suyos: un toque y ya está anotado a su nombre. --}}
                            @foreach ($this->susVehiculos as $suyo)
                                <button type="button" wire:click="elegirVehiculo('{{ $suyo->placa }}')"
                                        class="flex items-center gap-2 rounded border px-3 py-2 text-sm transition
                                               {{ $vehiculoEntrada === $suyo->placa
                                                  ? 'border-parte1 bg-parte1-suave font-semibold text-parte1'
                                                  : 'border-slate-300 text-slate-600 hover:bg-slate-50' }}">
                                    <x-etiqueta :tipo="$suyo->tipo" tamano="chico" />
                                    <span class="font-mono font-semibold tracking-wider">{{ $suyo->placa }}</span>
                                </button>
                            @endforeach

                            <button type="button" wire:click="elegirVehiculo('otro')"
                                    class="rounded border px-3 py-2 text-sm transition
                                           {{ $vehiculoEntrada === 'otro'
                                              ? 'border-parte1 bg-parte1-suave font-semibold text-parte1'
                                              : 'border-slate-300 text-slate-600 hover:bg-slate-50' }}">
                                {{ $this->susVehiculos->isEmpty() ? 'Anotar una placa' : 'Otro…' }}
                            </button>
                        </div>

                        {{-- Solo cuando hace falta teclear. La primera vez se teclea; a partir de
                             ahí el vehículo queda en su ficha y sale ahí arriba. --}}
                        @if ($vehiculoEntrada === 'otro')
                            <div class="mt-3 flex flex-wrap items-end gap-3">
                                <div class="w-44">
                                    <x-campo etiqueta="Placa" nombre="placaNueva" maxlength="15" autocomplete="off"
                                             wire:model="placaNueva" :error="$errors->first('placaEntrada') ?: $errors->first('placaFija')" />
                                </div>
                                <div class="w-36">
                                    <x-selector etiqueta="Tipo" nombre="tipoNuevo" wire:model="tipoNuevo"
                                                :opciones="['carro' => 'Carro', 'moto' => 'Moto']" />
                                </div>
                                <p class="pb-2.5 text-xs text-slate-500">Se guarda en su ficha: la próxima vez es un toque.</p>
                            </div>
                        @elseif ($errors->has('placaEntrada') || $errors->has('placaFija'))
                            <x-error class="mt-3">{{ $errors->first('placaEntrada') ?: $errors->first('placaFija') }}</x-error>
                        @endif
                    </div>
                @endif

                @if ($puedeSalir && ($this->susVehiculosDentro->isNotEmpty() || $this->otrosVehiculosDentro !== []))
                    <div class="mt-5 border-t border-slate-100 pt-5">
                        <p class="font-mono text-xs font-bold uppercase tracking-widest text-slate-500">
                            ¿Cómo sale?
                        </p>

                        <div class="mt-2.5 flex flex-wrap gap-2">
                            {{-- Igual que al entrar: salir a pie es un botón, no la ausencia de
                                 uno. Viene elegido, porque el vehículo se queda salvo que se diga
                                 lo contrario. --}}
                            <button type="button" wire:click="salirAPie"
                                    class="flex items-center gap-2 rounded border px-3 py-2 text-sm transition
                                           {{ $vehiculosSalida === []
                                              ? 'border-parte1 bg-parte1-suave font-semibold text-parte1'
                                              : 'border-slate-300 text-slate-600 hover:bg-slate-50' }}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                     stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4">
                                    <circle cx="12" cy="4.5" r="2"/><path d="M10 21l1.5-5-2.5-2.5V9l3-1.5 2.5 3 2.5 1"/><path d="M9 12.5 7 15m6.5 1L15 21"/>
                                </svg>
                                A pie
                            </button>

                            @foreach ($this->susVehiculosDentro as $dentro)
                                <button type="button" wire:click="alternarVehiculoSalida({{ $dentro->id }})"
                                        class="flex items-center gap-2 rounded border px-3 py-2 text-sm transition
                                               {{ in_array($dentro->id, $vehiculosSalida)
                                                  ? 'border-parte1 bg-parte1-suave font-semibold text-parte1'
                                                  : 'border-slate-300 text-slate-600 hover:bg-slate-50' }}">
                                    <x-etiqueta :tipo="$dentro->tipo_vehiculo" tamano="chico" />
                                    <span class="font-mono font-semibold tracking-wider">{{ $dentro->placa }}</span>
                                    @if ($dentro->puesto)
                                        <span class="font-mono text-xs text-slate-400">{{ $dentro->puesto->codigo }}</span>
                                    @endif
                                </button>
                            @endforeach

                            {{-- Los que no son suyos y ya se añadieron: se ven igual que los demás
                                 y se quitan tocándolos, para que no haya que recordar de dónde
                                 salió cada uno. --}}
                            @foreach ($vehiculosSalida as $elegido)
                                @if (isset($this->otrosVehiculosDentro[(string) $elegido]))
                                    <button type="button" wire:click="alternarVehiculoSalida({{ $elegido }})"
                                            class="flex items-center gap-2 rounded border border-parte1 bg-parte1-suave px-3 py-2 text-sm font-semibold text-parte1 transition">
                                        <span class="font-mono tracking-wider">{{ $this->otrosVehiculosDentro[(string) $elegido] }}</span>
                                        <span aria-hidden="true">✕</span>
                                    </button>
                                @endif
                            @endforeach
                        </div>

                        {{-- Un vehículo que no es suyo: de la empresa, o el de un compañero. Se
                             llega en lo propio y se sale en lo de otro más de lo que parece, y si
                             no se puede anotar aquí, esa estadía se queda abierta y el vehículo
                             figura dentro sin estar.

                             Va en un desplegable y no en botones: son muchos, y llevarse el
                             vehículo de otro no debería costar lo mismo que equivocarse. --}}
                        @if ($this->otrosVehiculosDentro !== [])
                            <div class="mt-3 flex flex-wrap items-end gap-3">
                                <div class="w-full sm:w-80">
                                    <x-selector etiqueta="…o se lleva otro que está dentro"
                                                nombre="otroVehiculoSalida"
                                                wire:model="otroVehiculoSalida"
                                                :opciones="['' => 'Elegir vehículo…'] + $this->otrosVehiculosDentro" />
                                </div>
                                <div class="pb-1.5">
                                    <x-boton type="button" variante="secundario" tamano="chico"
                                             wire:click="llevarseOtro">Añadir</x-boton>
                                </div>
                            </div>
                        @endif

                        <p class="mt-2 text-xs text-slate-500">
                            «A pie» deja su vehículo dentro, anotado. Solo se puede salir con un vehículo que esté dentro.
                        </p>

                        @error('vehiculoSalida')
                            <x-error class="mt-3">{{ $message }}</x-error>
                        @enderror
                    </div>
                @endif

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

    {{-- El recuadro de ayuda (pop-up). Se abre con el «?» de arriba y se cierra tocando fuera, la
         equis, Escape o «Entendido». Corto y con los tres pasos: para el vigilante nuevo. --}}
    <div x-show="ayuda" x-cloak x-transition.opacity
         x-on:click.self="ayuda = false" x-on:keydown.escape.window="ayuda = false"
         class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/50 p-4 sm:items-center">
        <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
            <div class="flex items-start justify-between gap-4">
                <h2 class="text-xl font-bold tracking-tight">Cómo marcar</h2>
                <button type="button" x-on:click="ayuda = false" aria-label="Cerrar"
                        class="-mr-1 -mt-1 flex h-8 w-8 items-center justify-center rounded-full text-slate-400 hover:bg-slate-100 hover:text-slate-700">✕</button>
            </div>

            <ol class="mt-5 space-y-4">
                <li class="flex items-start gap-3">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-marca text-sm font-bold text-white">1</span>
                    <p class="text-slate-700"><b class="font-semibold text-slate-900">Escanea el carnet</b> con la cámara, o escribe la cédula.</p>
                </li>
                <li class="flex items-start gap-3">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-marca text-sm font-bold text-white">2</span>
                    <p class="text-slate-700"><b class="font-semibold text-slate-900">Mira la foto</b> y confirma que es la persona.</p>
                </li>
                <li class="flex items-start gap-3">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-marca text-sm font-bold text-white">3</span>
                    <p class="text-slate-700"><b class="font-semibold text-slate-900">Di cómo entra:</b> «A pie», o su vehículo. Si tocas el vehículo queda anotado a su nombre ahí mismo, sin ir al estacionamiento.</p>
                </li>
                <li class="flex items-start gap-3">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-marca text-sm font-bold text-white">4</span>
                    <p class="text-slate-700"><b class="font-semibold text-slate-900">Pulsa Entrada o Salida.</b> El sistema ya resalta el botón que toca.</p>
                </li>
            </ol>

            <p class="mt-5 rounded-lg bg-invitado-suave px-3 py-2.5 text-sm font-medium text-invitado">
                ¿No tiene carnet y no aparece? Es un <b>invitado</b>: escribe su nombre, el motivo y a qué piso va.
            </p>

            <p class="mt-3 rounded-lg bg-slate-100 px-3 py-2.5 text-sm text-slate-600">
                Al <b>salir</b>, los suyos salen de un toque; si se lleva el de un compañero o uno de la
                empresa, elígelo en «se lleva otro que está dentro». Solo se puede sacar lo que está dentro.
                Si sale «a pie», su carro se queda anotado.
            </p>

            <x-boton type="button" x-on:click="ayuda = false" class="mt-6 w-full">Entendido</x-boton>
        </div>
    </div>
</div>
