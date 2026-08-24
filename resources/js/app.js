
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
import { indiceDeRostros, rostroEnLaPuerta } from './rostros.js';

document.addEventListener('alpine:init', () => {
    // El reconocimiento de caras vive en su propio archivo y se carga bajo demanda: ver rostros.js.
    window.Alpine.data('indiceDeRostros', indiceDeRostros);
    window.Alpine.data('rostroEnLaPuerta', rostroEnLaPuerta);

    window.Alpine.data('escanerCarnet', (wire) => ({
        abierto: false,
        mensaje: '',
        stream: null,
        raf: null,
        mostrandoCuadro: false,
        topCuadro: '0px',
        leftCuadro: '0px',
        soportaLinterna: false,
        linternaEncendida: false,
        soportaZoom: false,
        zoomMin: 1,
        zoomMax: 1,
        zoomActual: 1,
        camaras: [],
        camaraActivaId: null,

        async abrir(deviceId = null) {
            this.abierto = true;
            this.mensaje = 'Apunta al QR del carnet…';

            try {
                // Cargar lista de cámaras si aún no la tenemos
                if (this.camaras.length === 0) {
                    const devices = await navigator.mediaDevices.enumerateDevices();
                    this.camaras = devices.filter(d => d.kind === 'videoinput');
                }

                let constraints = {
                    video: { 
                        width: { ideal: 1920 },
                        height: { ideal: 1080 }
                    },
                    audio: false,
                };

                // Si pedimos una cámara específica (al rotar), la usamos. Si no, intentamos la trasera por defecto.
                if (deviceId) {
                    constraints.video.deviceId = { exact: deviceId };
                } else {
                    constraints.video.facingMode = 'environment';
                }

                this.stream = await navigator.mediaDevices.getUserMedia(constraints);
                this.$refs.video.srcObject = this.stream;
                await this.$refs.video.play();
                
                // Actualizar ID de la cámara actual
                const track = this.stream.getVideoTracks()[0];
                this.camaraActivaId = track.getSettings().deviceId;

                // Revisar capacidades (Linterna y Zoom)
                if (track && typeof track.getCapabilities === 'function') {
                    const capabilities = track.getCapabilities();
                    
                    // LINTERNA
                    this.soportaLinterna = !!capabilities.torch;
                    this.linternaEncendida = false;

                    // ZOOM
                    if (capabilities.zoom) {
                        this.soportaZoom = true;
                        this.zoomMin = capabilities.zoom.min || 1;
                        this.zoomMax = capabilities.zoom.max || 5;
                        // Intentar hacer zoom ligero inicial
                        this.zoomActual = Math.min(this.zoomMin + (this.zoomMax - this.zoomMin) * 0.2, 2.0);
                        if (this.zoomActual > this.zoomMax) this.zoomActual = this.zoomMax;
                        if (this.zoomActual < this.zoomMin) this.zoomActual = this.zoomMin;
                        
                        try {
                            await track.applyConstraints({ advanced: [{ zoom: this.zoomActual }] });
                        } catch (e) {}
                    } else {
                        this.soportaZoom = false;
                    }
                }

                this.buscar();
            } catch (e) {
                this.mensaje = 'No se pudo abrir la cámara: ' + (e.message || e.name) +
                    '. Revisa los permisos o usa HTTPS.';
            }
        },

        async toggleLinterna() {
            if (!this.stream || !this.soportaLinterna) return;
            const track = this.stream.getVideoTracks()[0];
            this.linternaEncendida = !this.linternaEncendida;
            try {
                await track.applyConstraints({ advanced: [{ torch: this.linternaEncendida }] });
            } catch (e) {
                console.error("Error con linterna", e);
            }
        },

        async aplicarZoomManual() {
            if (!this.stream || !this.soportaZoom) return;
            const track = this.stream.getVideoTracks()[0];
            try {
                await track.applyConstraints({ advanced: [{ zoom: parseFloat(this.zoomActual) }] });
            } catch (e) {}
        },

        async cambiarCamara() {
            if (this.camaras.length < 2) return;
            let idx = this.camaras.findIndex(c => c.deviceId === this.camaraActivaId);
            idx = (idx + 1) % this.camaras.length;
            const nuevaCamara = this.camaras[idx].deviceId;
            
            // Cerrar stream actual y reabrir con la nueva
            this.cerrar(false); // false para no cerrar la UI
            await this.abrir(nuevaCamara);
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
                        // 1. Reproducir Beep
                        try {
                            const ctx = new (window.AudioContext || window.webkitAudioContext)();
                            const osc = ctx.createOscillator();
                            osc.type = 'sine';
                            osc.frequency.setValueAtTime(880, ctx.currentTime);
                            osc.connect(ctx.destination);
                            osc.start();
                            osc.stop(ctx.currentTime + 0.1);
                        } catch(e) {}

                        // 2. Vibrar
                        if (window.navigator.vibrate) {
                            window.navigator.vibrate([200]);
                        }

                        this.mensaje = 'Carnet leído, verificando…';
                        wire.carnetEscaneado(qrEncontrado);
                        this.cerrar(true);
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

        cerrar(cerrarUi = true) {
            if (cerrarUi) {
                this.abierto = false;
                this.mostrandoCuadro = false;
            }
            if (this.raf) cancelAnimationFrame(this.raf);
            if (this.stream) {
                this.stream.getTracks().forEach((t) => t.stop());
                this.stream = null;
            }
        },
    }));
});
