/**
 * El reconocimiento de caras, entero en el navegador.
 *
 * Aquí no hay ninguna llamada a un servicio de nadie: los modelos se sirven desde este mismo
 * servidor (public/modelos/rostros) y el cálculo ocurre en el equipo que tiene la pantalla
 * abierta. Ninguna imagen sale de ahí —ni al servidor, que solo recibe 128 números—.
 *
 * Se carga en trozos aparte y bajo demanda (import dinámico): el paquete pesa varios megas y la
 * puerta no puede tardar más en abrir por una función que casi no se usa.
 */

/** El modelo cargado, una sola vez por pestaña. */
let api = null;

const RUTA_MODELOS = '/modelos/rostros';

/**
 * Carga la librería y los tres modelos que hacen falta.
 *
 * Son tres y no más a propósito: detectar dónde hay una cara, colocarle los puntos de la cara, y
 * sacar los 128 números. Lo demás que trae el paquete —edad, expresión— no se usa y no se baja.
 */
async function motor() {
    if (api) return api;

    const faceapi = await import('@vladmandic/face-api');

    await Promise.all([
        faceapi.nets.tinyFaceDetector.loadFromUri(RUTA_MODELOS),
        faceapi.nets.faceLandmark68TinyNet.loadFromUri(RUTA_MODELOS),
        faceapi.nets.faceRecognitionNet.loadFromUri(RUTA_MODELOS),
    ]);

    api = faceapi;
    return api;
}

/** Cómo se busca una cara. inputSize 416 va sobrado para una foto de carnet y para un vídeo. */
function opciones(faceapi) {
    return new faceapi.TinyFaceDetectorOptions({ inputSize: 416, scoreThreshold: 0.5 });
}

/** Los 128 números de la cara más grande de una imagen o un vídeo. Null si no se ve ninguna. */
async function descriptorDe(elemento) {
    const faceapi = await motor();

    const resultado = await faceapi
        .detectSingleFace(elemento, opciones(faceapi))
        .withFaceLandmarks(true)
        .withFaceDescriptor();

    return resultado ? Array.from(resultado.descriptor) : null;
}

/** Carga una foto por su URL. Mismo origen, así que el lienzo no queda «manchado». */
function imagen(url) {
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.onload = () => resolve(img);
        img.onerror = () => reject(new Error('no se pudo cargar la foto'));
        img.src = url;
    });
}

/**
 * A qué distancia están dos caras. Cuanto menor, más se parecen.
 *
 * Es la distancia euclídea de siempre. Por debajo de ~0,5 son la misma persona casi seguro; por
 * encima de ~0,6 son distintas. En medio es donde conviene que decida una persona y no el
 * programa, y por eso la puerta enseña el resultado en vez de marcarlo sola.
 */
function distancia(uno, otro) {
    let suma = 0;
    for (let i = 0; i < uno.length; i++) {
        const d = uno[i] - otro[i];
        suma += d * d;
    }
    return Math.sqrt(suma);
}

/**
 * El indexado: recorre las fotos del personal y manda sus descriptores a Livewire.
 *
 * De una en una y no todas a la vez: son fotos que hay que descargar y un cálculo que ocupa la
 * pestaña, y treinta a la vez dejarían el navegador clavado sin que nadie sepa por qué.
 */
export function indiceDeRostros(wire) {
    return {
        trabajando: false,
        hechas: 0,
        total: 0,
        actual: '',
        error: '',

        async indexar(pendientes) {
            if (this.trabajando || pendientes.length === 0) return;

            this.trabajando = true;
            this.error = '';
            this.hechas = 0;
            this.total = pendientes.length;

            try {
                await motor();
            } catch (e) {
                this.error = 'No se pudieron cargar los modelos: ' + (e.message || e);
                this.trabajando = false;
                return;
            }

            for (const persona of pendientes) {
                this.actual = persona.nombre;

                try {
                    const foto = await imagen(persona.foto);
                    const descriptor = await descriptorDe(foto);

                    if (descriptor) {
                        await wire.guardarRostro(persona.id, descriptor);
                    } else {
                        await wire.noSePudo(persona.id, persona.nombre, 'no se ve una cara en su foto');
                    }
                } catch (e) {
                    await wire.noSePudo(persona.id, persona.nombre, e.message || 'no se pudo leer su foto');
                }

                this.hechas++;
            }

            this.actual = '';
            this.trabajando = false;
            await wire.terminado();
        },
    };
}

/**
 * El reconocimiento en la puerta: mira por la cámara y propone a quién se parece.
 *
 * NUNCA marca por su cuenta. Cuando encuentra a alguien rellena la cédula y deja la ficha delante
 * del vigilante, que es quien confirma mirando la foto —igual que hace hoy con el carnet—. Un
 * parecido no es una identificación.
 */
export function rostroEnLaPuerta(wire) {
    return {
        abierto: false,
        cargando: false,
        mensaje: '',
        stream: null,
        raf: null,
        galeria: [],

        // Por debajo de esto se considera la misma persona. 0,5 es prudente: prefiere no decir
        // nada a decir un nombre equivocado, que en la puerta es lo caro.
        umbral: 0.5,

        async abrir(galeria) {
            this.galeria = galeria || [];

            if (this.galeria.length === 0) {
                this.mensaje = 'Todavía no hay ningún rostro indexado.';
                this.abierto = true;
                return;
            }

            this.abierto = true;
            this.cargando = true;
            this.mensaje = 'Preparando la cámara…';

            try {
                await motor();

                this.stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 720 } },
                    audio: false,
                });

                this.$refs.video.srcObject = this.stream;
                await this.$refs.video.play();

                this.cargando = false;
                this.mensaje = 'Mira a la cámara…';
                this.buscar();
            } catch (e) {
                this.cargando = false;
                this.mensaje = 'No se pudo abrir la cámara: ' + (e.message || e.name);
            }
        },

        async buscar() {
            const video = this.$refs.video;

            const tick = async () => {
                if (!this.abierto) return;

                if (video.readyState === video.HAVE_ENOUGH_DATA) {
                    let descriptor = null;

                    try {
                        descriptor = await descriptorDe(video);
                    } catch (e) {
                        // Un cuadro que falla no es nada: se intenta con el siguiente.
                    }

                    if (descriptor) {
                        const parecidos = this.galeria
                            .map((fila) => ({ ...fila, distancia: distancia(descriptor, fila.descriptor) }))
                            .sort((uno, otro) => uno.distancia - otro.distancia);

                        const mejor = parecidos[0];

                        if (mejor && mejor.distancia <= this.umbral) {
                            this.mensaje = 'Es ' + mejor.nombre + '. Comprueba la foto.';
                            wire.rostroReconocido(mejor.cedula, Number(mejor.distancia.toFixed(3)));
                            this.cerrar();
                            return;
                        }

                        this.mensaje = 'No reconozco esa cara. Usa el carnet o teclea la cédula.';
                    }
                }

                // Con pausa entre cuadros: el cálculo ocupa la pestaña y a toda velocidad deja el
                // teléfono caliente y la imagen a tirones, sin reconocer antes por ello.
                setTimeout(() => { if (this.abierto) this.raf = requestAnimationFrame(tick); }, 300);
            };

            tick();
        },

        cerrar() {
            this.abierto = false;
            if (this.raf) cancelAnimationFrame(this.raf);
            if (this.stream) {
                this.stream.getTracks().forEach((t) => t.stop());
                this.stream = null;
            }
        },
    };
}
