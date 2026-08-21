
/**
 * El escáner del carnet en la puerta.
 *
 * Abre la cámara del teléfono, busca un QR cuadro a cuadro y, al leerlo, le pasa su contenido a
 * Livewire (Marcar::carnetEscaneado), que lo verifica contra el sistema de carnets.
 *
 * El lector va EMPAQUETADO aquí —nada de CDN—: el puesto corre en red interna sin salida a
 * Internet, así que todo lo que se ejecute tiene que venir del propio servidor. Y la cámara solo
 * funciona sobre HTTPS (o localhost); por eso el puesto se sirve por HTTPS.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('escanerCarnet', (wire) => ({
        abierto: false,
        mensaje: '',
        stream: null,
        raf: null,
        mostrandoCuadro: false,
        topCuadro: '0px',
        leftCuadro: '0px',

        async abrir() {
            this.abierto = true;
            this.mensaje = 'Apunta al QR del carnet…';

            try {
                // «environment» = la cámara trasera, que es con la que se apunta al carnet.
                this.stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: 'environment' },
                    audio: false,
                });
                this.$refs.video.srcObject = this.stream;
                await this.$refs.video.play();
                this.buscar();
            } catch (e) {
                this.mensaje = 'No se pudo abrir la cámara: ' + (e.message || e.name) +
                    '. La cámara solo funciona por HTTPS.';
            }
        },

        async enfocar(e) {
            if (e) {
                const rect = e.currentTarget.getBoundingClientRect();
                const x = e.clientX - rect.left - 24; // 24 es la mitad del ancho del cuadro (48px / 2)
                const y = e.clientY - rect.top - 24;  // 24 es la mitad del alto del cuadro (48px / 2)
                this.leftCuadro = `${x}px`;
                this.topCuadro = `${y}px`;
                this.mostrandoCuadro = true;
                setTimeout(() => {
                    this.mostrandoCuadro = false;
                }, 800);
            }

            if (!this.stream) return;
            const track = this.stream.getVideoTracks()[0];
            if (!track) return;

            try {
                if (typeof track.getCapabilities !== 'function') {
                    // Si el navegador no expone capacidades avanzadas, reaplicamos las restricciones básicas
                    // para ver si el navegador re-autofoca.
                    await track.applyConstraints({ video: { facingMode: 'environment' } });
                    return;
                }

                const capabilities = track.getCapabilities();

                if (capabilities.focusMode) {
                    if (capabilities.focusMode.includes('single-shot')) {
                        await track.applyConstraints({
                            advanced: [{ focusMode: 'single-shot' }]
                        });
                        // Si soporta focus continuo, regresamos a él después de un breve instante
                        if (capabilities.focusMode.includes('continuous')) {
                            setTimeout(async () => {
                                try {
                                    if (this.stream && this.abierto) {
                                        const t = this.stream.getVideoTracks()[0];
                                        if (t) {
                                            await t.applyConstraints({
                                                advanced: [{ focusMode: 'continuous' }]
                                            });
                                        }
                                    }
                                } catch (err) {
                                    console.error("Error al volver a focus continuo:", err);
                                }
                            }, 500);
                        }
                    } else if (capabilities.focusMode.includes('continuous')) {
                        await track.applyConstraints({
                            advanced: [{ focusMode: 'continuous' }]
                        });
                    }
                }
            } catch (err) {
                console.error("Error al aplicar enfoque en la cámara:", err);
            }
        },

        buscar() {
            const video = this.$refs.video;
            const canvas = this.$refs.canvas;
            const ctx = canvas.getContext('2d', { willReadFrequently: true });

            const tick = () => {
                if (!this.abierto) return;

                if (video.readyState === video.HAVE_ENOUGH_DATA) {
                    canvas.width = video.videoWidth;
                    canvas.height = video.videoHeight;
                    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

                    const imagen = ctx.getImageData(0, 0, canvas.width, canvas.height);
                    const codigo = window.jsQR(imagen.data, imagen.width, imagen.height, {
                        inversionAttempts: 'dontInvert',
                    });

                    if (codigo && codigo.data) {
                        this.mensaje = 'Carnet leído, verificando…';
                        wire.carnetEscaneado(codigo.data);
                        this.cerrar();
                        return;
                    }
                }

                this.raf = requestAnimationFrame(tick);
            };

            this.raf = requestAnimationFrame(tick);
        },

        cerrar() {
            this.abierto = false;
            this.mostrandoCuadro = false;
            if (this.raf) cancelAnimationFrame(this.raf);
            if (this.stream) {
                this.stream.getTracks().forEach((t) => t.stop());
                this.stream = null;
            }
        },
    }));
});
