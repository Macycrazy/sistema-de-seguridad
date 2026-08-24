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

import { controlesDeCamara } from './camara.js';

/** El modelo cargado, una sola vez por pestaña. */
let api = null;

const RUTA_MODELOS = '/modelos/rostros';

/**
 * Carga la librería y los modelos.
 *
 * Son DOS detectores y no uno porque los dos trabajos no se parecen:
 *
 *   · «tiny» es el rápido, y es el que mira el vídeo en vivo: ahí hay treinta oportunidades por
 *     segundo y lo que importa es no dejar la imagen a tirones.
 *   · «ssd» es el bueno, y es el que mira las fotos al indexar: eso se hace una vez, sin prisa, y
 *     una cara que no se detecte ahí deja a esa persona fuera del reconocimiento para siempre.
 *
 * Con «tiny» solo, de 296 fotos de carnet se quedaban 154 sin indexar. No era culpa de las fotos:
 * el rápido se pierde las caras pequeñas o algo giradas, que en un carnet abundan.
 *
 * El de puntos de la cara y el de los 128 números son los mismos para ambos.
 */
async function motor(conElBueno = false) {
    const faceapi = api ?? (await import('@vladmandic/face-api'));

    if (!api) {
        await Promise.all([
            faceapi.nets.tinyFaceDetector.loadFromUri(RUTA_MODELOS),
            faceapi.nets.faceLandmark68TinyNet.loadFromUri(RUTA_MODELOS),
            faceapi.nets.faceRecognitionNet.loadFromUri(RUTA_MODELOS),
        ]);

        api = faceapi;
    }

    // El bueno se baja solo cuando se va a indexar: son 5 MB más que la puerta no necesita.
    if (conElBueno && !faceapi.nets.ssdMobilenetv1.isLoaded) {
        await faceapi.nets.ssdMobilenetv1.loadFromUri(RUTA_MODELOS);
    }

    return api;
}

/** Cómo se busca en vídeo: rápido, que hay muchos cuadros por segundo. */
function enVivo(faceapi) {
    return new faceapi.TinyFaceDetectorOptions({ inputSize: 416, scoreThreshold: 0.4 });
}

/** Los 128 números de la cara de un vídeo. Null si no se ve ninguna. */
async function descriptorDe(elemento) {
    const faceapi = await motor();

    const resultado = await faceapi
        .detectSingleFace(elemento, enVivo(faceapi))
        .withFaceLandmarks(true)
        .withFaceDescriptor();

    return resultado ? Array.from(resultado.descriptor) : null;
}

/**
 * Los 128 números de una FOTO, intentándolo en serio.
 *
 * Tres pasadas, de la más fiable a la más permisiva, porque cada persona que no se detecta se
 * queda fuera del reconocimiento y eso cuesta más que unos segundos de más al indexar:
 *
 *   1. el detector bueno, con la exigencia de siempre;
 *   2. el mismo, aceptando detecciones flojas (una cara de perfil, o pequeña en el encuadre);
 *   3. el rápido mirando más fino, por si la foto es diminuta y el bueno la descartó.
 *
 * Devuelve también con cuál se consiguió, para poder decir después si una cara se coló por los
 * pelos.
 */
async function descriptorDeFoto(imagen) {
    const faceapi = await motor(true);

    const intentos = [
        ['normal', new faceapi.SsdMobilenetv1Options({ minConfidence: 0.5 })],
        ['flojo', new faceapi.SsdMobilenetv1Options({ minConfidence: 0.2 })],
        ['fino', new faceapi.TinyFaceDetectorOptions({ inputSize: 608, scoreThreshold: 0.2 })],
    ];

    for (const [comoSalio, opciones] of intentos) {
        const resultado = await faceapi
            .detectSingleFace(imagen, opciones)
            .withFaceLandmarks(true)
            .withFaceDescriptor();

        if (resultado) {
            return { descriptor: Array.from(resultado.descriptor), comoSalio };
        }
    }

    return null;
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
 * Trae la foto diciendo QUÉ pasó cuando no se puede.
 *
 * Con «new Image()» a secas, un 404, un servidor caído y un archivo corrupto dan el mismo error
 * —«no se pudo cargar»—, y entonces la lista de fallos no ayuda a arreglar nada: no es lo mismo
 * que a alguien le falte la foto en carnets que que la foto esté rota.
 *
 * @returns {Promise<{img: HTMLImageElement}>} o lanza un Error con un motivo que se puede leer
 */
async function traerFoto(url) {
    let respuesta;

    try {
        respuesta = await fetch(url, { credentials: 'same-origin' });
    } catch (e) {
        throw new Error('no se pudo hablar con el servidor al pedir su foto');
    }

    if (respuesta.status === 404) {
        throw new Error('no tiene foto cargada en el sistema de carnets');
    }

    if (!respuesta.ok) {
        throw new Error('el servidor respondió ' + respuesta.status + ' al pedir su foto');
    }

    const blob = await respuesta.blob();

    if (blob.size === 0) {
        throw new Error('su foto está vacía (0 bytes)');
    }

    if (!blob.type.startsWith('image/')) {
        throw new Error('lo que hay en su foto no es una imagen (' + (blob.type || 'sin tipo') + ')');
    }

    const url64 = URL.createObjectURL(blob);

    try {
        const img = await imagen(url64);

        if (img.naturalWidth < 80 || img.naturalHeight < 80) {
            throw new Error('su foto es diminuta (' + img.naturalWidth + '×' + img.naturalHeight + ' píxeles)');
        }

        return { img, url64 };
    } catch (e) {
        URL.revokeObjectURL(url64);

        if (e.message && e.message.startsWith('su foto')) throw e;

        throw new Error('su foto está dañada o en un formato que el navegador no abre');
    }
}

/**
 * Cuánto de nítida está una imagen, para poder decir «borrosa» en vez de «no sé qué pasa».
 *
 * Se mide con la varianza del laplaciano, que es el modo habitual: se mira cuánto cambia el brillo
 * de un píxel al de al lado. En una foto nítida los bordes son bruscos y ese número es alto; en una
 * borrosa todo son transiciones suaves y baja mucho.
 *
 * El umbral no es una ciencia exacta —depende del tamaño y del recorte—, pero por debajo de 60 una
 * foto de carnet está claramente movida o desenfocada.
 */
function nitidez(img) {
    const lado = 320;
    const lienzo = document.createElement('canvas');
    lienzo.width = lado;
    lienzo.height = lado;

    const ctx = lienzo.getContext('2d', { willReadFrequently: true });
    ctx.drawImage(img, 0, 0, lado, lado);

    const { data } = ctx.getImageData(0, 0, lado, lado);

    // A gris, que el color no dice nada del enfoque.
    const gris = new Float32Array(lado * lado);
    for (let i = 0; i < gris.length; i++) {
        const p = i * 4;
        gris[i] = 0.299 * data[p] + 0.587 * data[p + 1] + 0.114 * data[p + 2];
    }

    let suma = 0;
    let sumaCuadrados = 0;
    let cuantos = 0;

    for (let y = 1; y < lado - 1; y++) {
        for (let x = 1; x < lado - 1; x++) {
            const i = y * lado + x;
            const lap =
                gris[i - lado] + gris[i + lado] + gris[i - 1] + gris[i + 1] - 4 * gris[i];

            suma += lap;
            sumaCuadrados += lap * lap;
            cuantos++;
        }
    }

    const media = suma / cuantos;

    return sumaCuadrados / cuantos - media * media;
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

        /**
         * La lista se le PIDE a Livewire; no viaja en el HTML.
         *
         * Meterla en un atributo estaba mal por partida doble: el JSON lleva comillas dobles y el
         * atributo también, así que el navegador cortaba el valor por la mitad y Alpine se
         * quejaba de un paréntesis que faltaba; y con casi trescientas personas ese atributo pesa
         * más que la página entera.
         */
        async indexar(cual) {
            if (this.trabajando) return;

            this.trabajando = true;
            this.error = '';
            this.actual = 'buscando a quién mirar…';

            let pendientes = [];

            try {
                pendientes = await wire.listaParaIndexar(cual);
            } catch (e) {
                this.error = 'No se pudo pedir la lista: ' + (e.message || e);
                this.trabajando = false;
                return;
            }

            if (!pendientes || pendientes.length === 0) {
                this.trabajando = false;
                this.actual = '';
                return;
            }

            await this.mirar(pendientes);
        },

        async mirar(pendientes) {

            this.trabajando = true;
            this.error = '';
            this.hechas = 0;
            this.total = pendientes.length;

            // Se dice ANTES de empezar: cargar los modelos son varios megas y unos segundos en
            // los que no pasa nada visible. Sin esto, el botón parece que no hizo nada y se
            // vuelve a pulsar.
            this.actual = 'cargando los modelos (la primera vez tarda)…';

            try {
                await motor();
            } catch (e) {
                this.error = 'No se pudieron cargar los modelos: ' + (e.message || e);
                this.trabajando = false;
                return;
            }

            for (const persona of pendientes) {
                this.actual = persona.nombre;
                await this.mirarUna(persona);
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
        // Linterna, zoom, cambiar de cámara y enfocar al tocar: los mismos que el escáner del
        // carnet, del mismo sitio. Ver camara.js.
        ...controlesDeCamara(),

        abierto: false,
        cargando: false,
        mensaje: '',
        raf: null,

        // Se pide al abrir, por lo mismo que la lista del índice: son 128 números por persona, y
        // en un atributo del HTML serían cientos de kilos con las comillas rotas por medio.
        galeria: [],

        // Por debajo de esto se considera la misma persona. 0,5 es prudente: prefiere no decir
        // nada a decir un nombre equivocado, que en la puerta es lo caro.
        umbral: 0.5,

        // Para mirar una cara se empieza por la frontal, al revés que para leer un carnet.
        caraActual: 'user',

        async abrir(deviceId = null) {
            this.abierto = true;
            this.cargando = true;
            this.mensaje = 'Preparando…';

            try {
                if (this.galeria.length === 0) {
                    this.galeria = (await wire.galeriaParaReconocer()) || [];
                }

                if (this.galeria.length === 0) {
                    this.cargando = false;
                    this.mensaje = 'Todavía no hay ningún rostro indexado.';
                    return;
                }

                await motor();
                await this.encenderCamara(deviceId);

                this.cargando = false;
                this.mensaje = 'Mira a la cámara…';
                this.buscar();
            } catch (e) {
                this.cargando = false;
                this.mensaje = 'No se pudo abrir la cámara: ' + (e.message || e.name);
            }
        },

        /** Lo que camara.js necesita para poder cambiar de cámara sin saber qué busca este visor. */
        async reabrirCamara(deviceId) {
            this.pararBusqueda();
            this.apagarCamara();
            await this.abrir(deviceId);
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

        /**
         * Una persona: trae su foto, le busca la cara y guarda o explica POR QUÉ no se pudo.
         *
         * El motivo importa tanto como el resultado. Cuando todos los fallos decían «no se pudo
         * cargar la foto», la lista no servía para arreglar nada: no es lo mismo que alguien no
         * esté dado de alta en carnets, que esté sin foto, que la foto esté movida o que salga de
         * medio lado. Cada uno se arregla en un sitio distinto.
         */
        async mirarUna(persona) {
            let foto;

            try {
                foto = await traerFoto(persona.foto);
            } catch (e) {
                // «No tiene foto» y «no está fichado allá» llegan igual —un 404— y se distinguen
                // con el padrón, que dice quién está.
                const motivo = persona.enCarnets === false
                    ? 'no está en el sistema de carnets'
                    : (e.message || 'no se pudo abrir su foto');

                await wire.noSePudo(persona.id, persona.nombre, motivo);
                return;
            }

            try {
                const encontrada = await descriptorDeFoto(foto.img);

                if (encontrada) {
                    // Con el hash de la foto que se acaba de mirar: es lo que después permite
                    // saber a quién le cambiaron la cara sin volver a mirarlos a todos.
                    await wire.guardarRostro(persona.id, encontrada.descriptor, persona.hash ?? null);
                    return;
                }

                // No se le encontró cara: se mide el enfoque para poder decir si es que la foto
                // está movida —que se arregla tomándola otra vez— o si hay otra cosa.
                let motivo = 'no se distingue una cara en su foto';

                try {
                    if (nitidez(foto.img) < 60) {
                        motivo = 'su foto está borrosa o movida';
                    }
                } catch (e) {
                    // Medirlo es un extra: si no se puede, se queda el motivo general.
                }

                await wire.noSePudo(persona.id, persona.nombre, motivo);
            } finally {
                if (foto && foto.url64) URL.revokeObjectURL(foto.url64);
            }
        },

        pararBusqueda() {
            if (this.raf) cancelAnimationFrame(this.raf);
            this.raf = null;
        },

        cerrar() {
            this.abierto = false;
            this.pararBusqueda();
            this.apagarCamara();
        },
    };
}
