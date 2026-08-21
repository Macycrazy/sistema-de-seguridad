
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
                // Pedimos cámara trasera con alta resolución (Full HD ideal, o lo que soporte)
                this.stream = await navigator.mediaDevices.getUserMedia({
                    video: { 
                        facingMode: 'environment',
                        width: { ideal: 1920 },
                        height: { ideal: 1080 }
                    },
                    audio: false,
                });
                
                this.$refs.video.srcObject = this.stream;
                await this.$refs.video.play();
                
                // Intentar aplicar un ligero zoom nativo para ver "más de cerca"
                const track = this.stream.getVideoTracks()[0];
                if (track && typeof track.getCapabilities === 'function') {
                    const capabilities = track.getCapabilities();
                    if (capabilities.zoom) {
                        // Tratar de hacer un zoom de 2x (o el máximo si es menor)
                        let targetZoom = 2.0;
                        if (targetZoom > capabilities.zoom.max) targetZoom = capabilities.zoom.max;
                        if (targetZoom < capabilities.zoom.min) targetZoom = capabilities.zoom.min;
                        
                        try {
                            await track.applyConstraints({ advanced: [{ zoom: targetZoom }] });
                        } catch (e) { console.log("No se pudo aplicar zoom nativo"); }
                    }
                }

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
                if (typeof track.getCapabilities !== 'function') return;

                const capabilities = track.getCapabilities();

                // Intentar usar pointsOfInterest si el navegador lo soporta (muy raro pero preciso)
                if (capabilities.pointsOfInterest) {
                    try {
                        await track.applyConstraints({ advanced: [{ pointsOfInterest: [{ x: 0.5, y: 0.5 }] }] });
                    } catch (e) { }
                }

                if (capabilities.focusMode) {
                    // Truco comprobado para forzar autoenfoque: Pasar a manual (para detener la lente) 
                    // y luego a continuo (para forzar un nuevo barrido).
                    if (capabilities.focusMode.includes('manual') && capabilities.focusMode.includes('continuous')) {
                        let manualConfig = { focusMode: 'manual' };
                        // Si permite forzar la distancia, lo enviamos al mínimo temporalmente
                        if (capabilities.focusDistance && capabilities.focusDistance.min !== undefined) {
                            manualConfig.focusDistance = capabilities.focusDistance.min;
                        }
                        
                        await track.applyConstraints({ advanced: [manualConfig] });
                        
                        // Y regresamos a continuous para que vuelva a enfocar automáticamente
                        setTimeout(async () => {
                            try {
                                if (this.stream && this.abierto) {
                                    const t = this.stream.getVideoTracks()[0];
                                    if (t) {
                                        await t.applyConstraints({ advanced: [{ focusMode: 'continuous' }] });
                                    }
                                }
                            } catch (err) {}
                        }, 100);
                    } else if (capabilities.focusMode.includes('single-shot')) {
                        await track.applyConstraints({ advanced: [{ focusMode: 'single-shot' }] });
                        if (capabilities.focusMode.includes('continuous')) {
                            setTimeout(async () => {
                                try {
                                    if (this.stream && this.abierto) {
                                        const t = this.stream.getVideoTracks()[0];
                                        if (t) await t.applyConstraints({ advanced: [{ focusMode: 'continuous' }] });
                                    }
                                } catch (err) {}
                            }, 500);
                        }
                    } else if (capabilities.focusMode.includes('continuous')) {
                        await track.applyConstraints({ advanced: [{ focusMode: 'continuous' }] });
                    }
                }
            } catch (err) {
                console.error("Error al aplicar enfoque en la cámara:", err);
            }
        },

        async buscar() {
            const video = this.$refs.video;
            const canvas = this.$refs.canvas;
            const ctx = canvas.getContext('2d', { willReadFrequently: true });

            // Detectar si el navegador soporta el motor nativo (Google Play Services en Android)
            let detectorNativo = null;
            if ('BarcodeDetector' in window) {
                try {
                    detectorNativo = new window.BarcodeDetector({ formats: ['qr_code'] });
                } catch (e) {
                    detectorNativo = null;
                }
            }

            const tick = async () => {
                if (!this.abierto) return;

                if (video.readyState === video.HAVE_ENOUGH_DATA) {
                    let qrEncontrado = null;

                    if (detectorNativo) {
                        try {
                            // El motor nativo lee directo del video (es muchísimo más rápido y reconoce de más lejos)
                            const barcodes = await detectorNativo.detect(video);
                            if (barcodes.length > 0) {
                                qrEncontrado = barcodes[0].rawValue;
                            }
                        } catch (err) {
                            // Si falla, el loop sigue
                        }
                    } else {
                        // Plan de respaldo: JS tradicional con jsQR (para iOS y otros navegadores)
                        canvas.width = video.videoWidth;
                        canvas.height = video.videoHeight;
                        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

                        const imagen = ctx.getImageData(0, 0, canvas.width, canvas.height);
                        const codigo = window.jsQR(imagen.data, imagen.width, imagen.height, {
                            inversionAttempts: 'dontInvert',
                        });
                        
                        if (codigo && codigo.data) {
                            qrEncontrado = codigo.data;
                        }
                    }

                    if (qrEncontrado) {
                        this.mensaje = 'Carnet leído, verificando…';
                        wire.carnetEscaneado(qrEncontrado);
                        this.cerrar();
                        return;
                    }
                }

                // Si usamos await adentro, mejor usar un ligero setTimeout para evitar saturar si falla requestAnimationFrame
                if (detectorNativo) {
                    setTimeout(() => { if (this.abierto) requestAnimationFrame(tick); }, 100);
                } else {
                    this.raf = requestAnimationFrame(tick);
                }
            };

            tick();
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
