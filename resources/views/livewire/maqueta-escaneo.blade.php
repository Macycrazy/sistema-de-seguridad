{{--
    MAQUETA · escanear la cédula con la cámara del teléfono.

    Pensada de arriba abajo para un teléfono en la mano: el visor ocupa la pantalla y los botones
    se alcanzan con el pulgar.

    Lo que de verdad hace falta resolver es leer los dígitos desde la imagen. Aquí se simula, y se
    dice claramente. El resto del recorrido es el de verdad: la cédula se busca contra la base y
    sale la persona que existe.
--}}
<div class="mx-auto max-w-md" x-data="escaneo()">

    <div class="mb-5 rounded border border-invitado/30 bg-invitado-suave px-4 py-3">
        <p class="font-mono text-xs font-bold uppercase tracking-widest text-invitado">Maqueta</p>
        <p class="mt-1 text-sm text-slate-700">
            Para ver cómo sería, no para usar en la puerta. <strong class="font-semibold">No registra
            movimientos</strong>: llega hasta el botón y ahí se detiene.
        </p>
    </div>

    {{-- EL VISOR --}}
    @if (! $this->persona() && ! $desconocida)
        <div class="overflow-hidden rounded border border-slate-200 bg-slate-900 shadow-sm">
            <div class="relative aspect-[4/3] w-full">
                {{-- La cámara de verdad, cuando el navegador la deja. --}}
                <video x-ref="video" autoplay playsinline muted
                       class="h-full w-full object-cover" x-show="camaraViva"></video>

                {{-- Y si no, un visor de mentira, para que la maqueta se entienda igual. --}}
                <div x-show="! camaraViva" class="h-full w-full bg-slate-800"></div>

                {{-- El marco donde se encuadra la cédula. --}}
                <div class="pointer-events-none absolute inset-0 flex items-center justify-center">
                    <div class="relative h-[55%] w-[85%] rounded-lg border-2 border-white/80 shadow-[0_0_0_9999px_rgba(15,23,42,0.45)]">
                        <span class="absolute -top-7 left-0 font-mono text-xs uppercase tracking-widest text-white/90">
                            Encuadra la cédula
                        </span>
                        {{-- La línea que barre, para que se vea que está mirando. --}}
                        <div x-show="leyendo" class="absolute inset-x-0 top-0 h-0.5 animate-pulse bg-ok"></div>
                    </div>
                </div>

                {{-- Por qué no hay imagen. Va debajo del marco y no dentro, para que no se
                     recorte contra el borde. --}}
                <p x-show="! camaraViva"
                   class="pointer-events-none absolute inset-x-0 bottom-3 px-4 text-center font-mono text-[0.65rem] uppercase leading-relaxed tracking-widest text-slate-400"
                   x-text="motivoSinCamara"></p>
            </div>

            <div class="bg-slate-900 px-4 pb-4">
                <p x-show="leyendo" class="mb-3 text-center font-mono text-xs uppercase tracking-widest text-ok">
                    Leyendo…
                </p>
                <button type="button" x-on:click="leer()" x-bind:disabled="leyendo"
                        class="w-full rounded bg-white px-6 py-4 text-lg font-semibold tracking-wide text-slate-900
                               transition hover:bg-slate-100 disabled:opacity-50">
                    <span x-show="! leyendo">Escanear cédula</span>
                    <span x-show="leyendo">Leyendo…</span>
                </button>
            </div>
        </div>

        <p class="mt-3 text-sm text-slate-500">
            Con el carnet delante, la cámara lee la cédula y el sistema hace lo de siempre: dice
            quién es y propone entrada o salida.
        </p>
    @endif

    {{-- LO QUE LEYÓ --}}
    @if ($this->persona())
        @php
            $persona = $this->persona();
        @endphp

        <x-tarjeta parte="1">
            <p class="font-mono text-xs uppercase tracking-widest text-slate-500">
                Cédula leída · {{ $persona->cedulaConPuntos() }}
            </p>

            <div class="mt-4 flex items-start gap-4">
                <div class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded bg-slate-100">
                    @if ($persona->tieneFoto())
                        <img src="{{ route('persona.foto', $persona) }}"
                             alt="Foto de {{ $persona->nombre }}" class="h-full w-full object-cover">
                    @else
                        <span class="font-mono text-xl font-bold text-slate-400">{{ $persona->iniciales() }}</span>
                    @endif
                </div>

                <div class="min-w-0 flex-1">
                    <p class="text-xl font-bold tracking-tight">{{ $persona->nombre }}</p>
                    <div class="mt-1"><x-etiqueta :tipo="$persona->tipo" /></div>
                    @if ($persona->esTrabajador())
                        <p class="mt-2 text-sm text-slate-600">{{ $persona->dependencia }}</p>
                    @endif
                </div>
            </div>

            <div class="mt-5 flex flex-col gap-3 border-t border-slate-100 pt-5">
                <x-boton variante="entrada" tamano="grande"
                         class="w-full {{ $this->sugerido() === 'entrada' ? 'ring-2 ring-slate-900 ring-offset-2' : 'opacity-50' }}"
                         disabled>ENTRADA</x-boton>
                <x-boton variante="salida" tamano="grande"
                         class="w-full {{ $this->sugerido() === 'salida' ? 'ring-2 ring-slate-900 ring-offset-2' : 'opacity-50' }}"
                         disabled>SALIDA</x-boton>
                <p class="text-center text-sm text-slate-500">
                    En la maqueta los botones no registran nada.
                </p>
            </div>
        </x-tarjeta>

        <div class="mt-4">
            <x-boton variante="secundario" class="w-full" wire:click="otraVez">Escanear otra</x-boton>
        </div>
    @endif

    {{-- UNA CÉDULA QUE NO ESTÁ --}}
    @if ($desconocida)
        <x-tarjeta>
            <div class="rounded bg-invitado-suave px-4 py-3">
                <p class="font-semibold text-invitado">
                    Leí la cédula {{ $cedula }} y no está en el sistema.
                </p>
                <p class="mt-1 text-sm text-slate-600">
                    En la pantalla real, aquí se pediría el nombre y el motivo de la visita.
                </p>
            </div>
            <div class="mt-4">
                <x-boton variante="secundario" class="w-full" wire:click="otraVez">Escanear otra</x-boton>
            </div>
        </x-tarjeta>
    @endif

    @script
    <script>
        Alpine.data('escaneo', () => ({
            camaraViva: false,
            leyendo: false,
            motivoSinCamara: 'Cámara no disponible · la maqueta funciona igual',

            init() {
                this.encenderCamara();
            },

            // La cámara solo la dan los navegadores en un sitio seguro: https o localhost.
            // Desde el teléfono, entrando por la IP del equipo, NO la van a dar — y por eso la
            // maqueta tiene que entenderse igual sin ella.
            async encenderCamara() {
                if (! navigator.mediaDevices?.getUserMedia) {
                    this.motivoSinCamara = 'Este navegador no da acceso a la cámara';
                    return;
                }
                if (! window.isSecureContext) {
                    this.motivoSinCamara = 'Sin https no hay cámara · así se ve el resto';
                    return;
                }
                try {
                    const flujo = await navigator.mediaDevices.getUserMedia({
                        video: { facingMode: 'environment' },
                    });
                    this.$refs.video.srcObject = flujo;
                    this.camaraViva = true;
                } catch (e) {
                    this.motivoSinCamara = 'No diste permiso de cámara · así se ve el resto';
                }
            },

            // AQUÍ está lo único que la maqueta finge.
            //
            // Leer los dígitos de la imagen es el trabajo de verdad que queda por hacer, y no es
            // poco: enfoque, brillo, cédulas gastadas. Se simula con una espera y una cédula de
            // las que ya existen, para poder enseñar el recorrido completo.
            leer() {
                this.leyendo = true;
                const inventadas = ['12345678', '22222222', '44444444', '87654321', '30303030'];
                const cedula = inventadas[Math.floor(Math.random() * inventadas.length)];

                setTimeout(() => {
                    this.leyendo = false;
                    this.$wire.leida(cedula);
                }, 1600);
            },
        }));
    </script>
    @endscript
</div>
