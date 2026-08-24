/**
 * Los controles de la cámara del teléfono: linterna, zoom, cambiar de cámara y enfocar al tocar.
 *
 * Están aquí y no en cada visor porque son los mismos para todos. Nacieron en el escáner del
 * carnet, y cuando llegó el reconocimiento de caras se vio lo que pasa al copiarlos: un arreglo
 * hecho en uno —la lista de cámaras que hay que pedir DESPUÉS de conceder el permiso— no llegaba
 * al otro. Un solo sitio, y los dos visores se comportan igual.
 *
 * Se mezcla en un componente de Alpine con «...controlesDeCamara()». Quien lo use pone su propio
 * «abierto», su mensaje y lo que haga con la imagen; esto solo maneja el aparato.
 */
export function controlesDeCamara() {
    return {
        stream: null,
        camaras: [],
        camaraActivaId: null,

        // Qué cara del teléfono se pide cuando no hay identificadores con los que elegir.
        caraActual: 'environment',

        soportaLinterna: false,
        linternaEncendida: false,

        soportaZoom: false,
        zoomMin: 1,
        zoomMax: 1,
        zoomActual: 1,

        // El pulso que aparece donde se toca para enfocar.
        mostrandoCuadro: false,
        topCuadro: '0px',
        leftCuadro: '0px',

        /**
         * Abre la cámara y deja listos los controles que ese aparato admita.
         *
         * Devuelve el track de vídeo, o lanza si no se pudo abrir: cada visor decide qué decir.
         */
        async encenderCamara(deviceId = null, refVideo = 'video') {
            const constraints = {
                video: { width: { ideal: 1920 }, height: { ideal: 1080 } },
                audio: false,
            };

            // Si se pide una cámara concreta (al rotar), esa. Si no, la cara que toque.
            if (deviceId) {
                constraints.video.deviceId = { exact: deviceId };
            } else {
                constraints.video.facingMode = this.caraActual;
            }

            this.stream = await navigator.mediaDevices.getUserMedia(constraints);
            this.$refs[refVideo].srcObject = this.stream;
            await this.$refs[refVideo].play();

            const track = this.stream.getVideoTracks()[0];
            this.camaraActivaId = track.getSettings().deviceId;

            /*
             * La lista de cámaras se pide AQUÍ, con el permiso ya concedido, y no antes.
             *
             * Sin permiso el navegador las esconde: devuelve la lista incompleta y con el
             * «deviceId» en blanco, para que una página no pueda reconocer un equipo por sus
             * dispositivos. Preguntando antes, un teléfono con frontal y trasera parecía tener una
             * sola y el botón de cambiar no salía.
             */
            try {
                const dispositivos = await navigator.mediaDevices.enumerateDevices();
                this.camaras = dispositivos.filter((d) => d.kind === 'videoinput');
            } catch (e) {
                this.camaras = [];
            }

            this.mirarCapacidades(track);

            return track;
        },

        /** Qué admite esta cámara: linterna y zoom. Lo que no, se queda sin botón. */
        async mirarCapacidades(track) {
            if (!track || typeof track.getCapabilities !== 'function') return;

            const capacidades = track.getCapabilities();

            this.soportaLinterna = !!capacidades.torch;
            this.linternaEncendida = false;

            if (!capacidades.zoom) {
                this.soportaZoom = false;
                return;
            }

            this.soportaZoom = true;
            this.zoomMin = capacidades.zoom.min || 1;
            this.zoomMax = capacidades.zoom.max || 5;

            // Un acercamiento suave de salida: ayuda a leer un QR y a encuadrar una cara.
            this.zoomActual = Math.min(this.zoomMin + (this.zoomMax - this.zoomMin) * 0.2, 2.0);
            if (this.zoomActual > this.zoomMax) this.zoomActual = this.zoomMax;
            if (this.zoomActual < this.zoomMin) this.zoomActual = this.zoomMin;

            try {
                await track.applyConstraints({ advanced: [{ zoom: this.zoomActual }] });
            } catch (e) {}
        },

        async toggleLinterna() {
            if (!this.stream || !this.soportaLinterna) return;

            const track = this.stream.getVideoTracks()[0];
            this.linternaEncendida = !this.linternaEncendida;

            try {
                await track.applyConstraints({ advanced: [{ torch: this.linternaEncendida }] });
            } catch (e) {
                console.error('Error con linterna', e);
            }
        },

        async aplicarZoomManual() {
            if (!this.stream || !this.soportaZoom) return;

            const track = this.stream.getVideoTracks()[0];

            try {
                await track.applyConstraints({ advanced: [{ zoom: parseFloat(this.zoomActual) }] });
            } catch (e) {}
        },

        /**
         * Si tiene sentido ofrecer el cambio de cámara.
         *
         * Con varias enumeradas, claro. Pero también cuando no se pudo enumerar ninguna o vinieron
         * sin identificador: en un teléfono siempre hay frontal y trasera, y esconder el botón por
         * no haber podido preguntar deja al vigilante sin poder girar la cámara.
         */
        get puedeCambiarCamara() {
            return this.camaras.length > 1 || !this.camaras.some((c) => c.deviceId);
        },

        /**
         * Cómo se vuelve a arrancar el visor con otra cámara. LO PONE QUIEN USE ESTOS CONTROLES.
         *
         * Aquí no se puede saber: uno busca un QR y el otro una cara, y cada uno tiene que parar
         * lo suyo antes de reabrir. Se deja declarado —y reventando con un mensaje claro— para que
         * el contrato esté escrito y no se descubra en el navegador.
         */
        async reabrirCamara(deviceId) {
            throw new Error('Quien use controlesDeCamara() tiene que definir reabrirCamara(deviceId).');
        },

        /**
         * Cambia de cámara. Se apoya en «reabrirCamara», que trae quien use estos controles.
         */
        async cambiarCamara() {
            const conId = this.camaras.filter((c) => c.deviceId);

            if (conId.length > 1) {
                let idx = conId.findIndex((c) => c.deviceId === this.camaraActivaId);
                idx = (idx + 1) % conId.length;

                await this.reabrirCamara(conId[idx].deviceId);
                return;
            }

            // Sin identificadores se pide la otra cara del teléfono: es lo que funciona cuando el
            // navegador no quiere decir qué cámaras hay.
            this.caraActual = this.caraActual === 'environment' ? 'user' : 'environment';
            await this.reabrirCamara(null);
        },

        /** Enfoca donde se toca, y deja un pulso ahí para que se vea que hizo caso. */
        async enfocar(e) {
            if (e) {
                const rect = e.currentTarget.getBoundingClientRect();
                // 24 es la mitad del pulso (48 px), para que quede centrado en el dedo.
                this.leftCuadro = `${e.clientX - rect.left - 24}px`;
                this.topCuadro = `${e.clientY - rect.top - 24}px`;
                this.mostrandoCuadro = true;
                setTimeout(() => { this.mostrandoCuadro = false; }, 800);
            }

            if (!this.stream) return;

            const track = this.stream.getVideoTracks()[0];
            if (!track || typeof track.getCapabilities !== 'function') return;

            try {
                const capacidades = track.getCapabilities();

                if (capacidades.pointsOfInterest) {
                    try {
                        await track.applyConstraints({ advanced: [{ pointsOfInterest: [{ x: 0.5, y: 0.5 }] }] });
                    } catch (e) {}
                }

                if (!capacidades.focusMode) return;

                /*
                 * Truco comprobado para forzar un barrido de enfoque: pasar a manual —que detiene
                 * la lente— y volver a continuo enseguida. Pedirle «continuous» a una lente que ya
                 * está en continuo no hace nada.
                 */
                if (capacidades.focusMode.includes('manual') && capacidades.focusMode.includes('continuous')) {
                    const manual = { focusMode: 'manual' };

                    if (capacidades.focusDistance && capacidades.focusDistance.min !== undefined) {
                        manual.focusDistance = capacidades.focusDistance.min;
                    }

                    await track.applyConstraints({ advanced: [manual] });
                    this.volverAContinuo(100);
                    return;
                }

                if (capacidades.focusMode.includes('single-shot')) {
                    await track.applyConstraints({ advanced: [{ focusMode: 'single-shot' }] });

                    if (capacidades.focusMode.includes('continuous')) {
                        this.volverAContinuo(500);
                    }

                    return;
                }

                if (capacidades.focusMode.includes('continuous')) {
                    await track.applyConstraints({ advanced: [{ focusMode: 'continuous' }] });
                }
            } catch (err) {
                console.error('Error al aplicar enfoque en la cámara:', err);
            }
        },

        /** Devuelve la lente al enfoque automático un instante después. */
        volverAContinuo(despuesDe) {
            setTimeout(async () => {
                try {
                    if (!this.stream || !this.abierto) return;

                    const track = this.stream.getVideoTracks()[0];
                    if (track) await track.applyConstraints({ advanced: [{ focusMode: 'continuous' }] });
                } catch (err) {}
            }, despuesDe);
        },

        /**
         * El aviso de que se leyó algo: un pitido y una vibración.
         *
         * En la puerta el vigilante está mirando a la persona, no a la pantalla. Sin esto hay que
         * comprobar el teléfono cada vez para saber si ya leyó, que es justo lo que se quería
         * evitar. Los dos son «si se puede»: un navegador sin sonido o un equipo sin vibrador no
         * son un error, solo se quedan sin ese aviso.
         */
        avisarDeLectura() {
            try {
                const audio = new (window.AudioContext || window.webkitAudioContext)();
                const osc = audio.createOscillator();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(880, audio.currentTime);
                osc.connect(audio.destination);
                osc.start();
                osc.stop(audio.currentTime + 0.1);
            } catch (e) {}

            try {
                if (window.navigator.vibrate) window.navigator.vibrate([200]);
            } catch (e) {}
        },

        /** Apaga la cámara y suelta el aparato: sin esto la luz del teléfono se queda encendida. */
        apagarCamara() {
            if (this.stream) {
                this.stream.getTracks().forEach((t) => t.stop());
                this.stream = null;
            }

            this.linternaEncendida = false;
            this.mostrandoCuadro = false;
        },
    };
}
