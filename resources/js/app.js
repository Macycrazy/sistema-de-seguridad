
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
import { controlesDeCamara } from './camara.js';
import { indiceDeRostros, rostroEnLaPuerta } from './rostros.js';

document.addEventListener('alpine:init', () => {
    // El reconocimiento de caras vive en su propio archivo y se carga bajo demanda: ver rostros.js.
    window.Alpine.data('indiceDeRostros', indiceDeRostros);
    window.Alpine.data('rostroEnLaPuerta', rostroEnLaPuerta);

    window.Alpine.data('escanerCarnet', (wire) => ({
        // Linterna, zoom, cambiar de cámara y enfocar al tocar: viven en camara.js, y de ahí los
        // toma también el visor de reconocimiento facial. Estaban aquí y se copiaron allá; el
        // primer arreglo que se hizo en uno no llegó al otro, así que ahora hay un solo sitio.
        ...controlesDeCamara(),

        abierto: false,
        mensaje: '',
        raf: null,

        async abrir(deviceId = null) {
            this.abierto = true;
            this.mensaje = 'Apunta al QR del carnet…';

            try {
                await this.encenderCamara(deviceId);
                this.buscar();
            } catch (e) {
                this.mensaje = 'No se pudo abrir la cámara: ' + (e.message || e.name) +
                    '. Revisa los permisos o usa HTTPS.';
            }
        },

        /** Lo que camara.js necesita para cambiar de cámara sin saber qué busca este visor. */
        async reabrirCamara(deviceId) {
            this.cerrar(false);
            await this.abrir(deviceId);
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
                        // Pitido y vibración: el vigilante está mirando a la persona, no a la
                        // pantalla. Ver camara.js.
                        this.avisarDeLectura();

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
            }

            if (this.raf) cancelAnimationFrame(this.raf);
            this.raf = null;
            this.apagarCamara();
        },
    }));
});
