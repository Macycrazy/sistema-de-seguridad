
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
            if (this.raf) cancelAnimationFrame(this.raf);
            if (this.stream) {
                this.stream.getTracks().forEach((t) => t.stop());
                this.stream = null;
            }
        },
    }));
});
