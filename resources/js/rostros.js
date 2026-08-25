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
    const encontrada = await caraDe(elemento);

    return encontrada ? encontrada.descriptor : null;
}

/**
 * La cara de un vídeo con lo que hace falta para saber si merece la pena fiarse de ella.
 *
 * Además de los 128 números vienen el tamaño en pantalla y la confianza de la detección. Una cara
 * pequeña, de perfil o borrosa da unos números pobres que caen a media distancia de todo el mundo:
 * son las que provocan que se confunda a dos personas.
 */
async function caraDe(elemento) {
    const faceapi = await motor();

    const resultado = await faceapi
        .detectSingleFace(elemento, enVivo(faceapi))
        .withFaceLandmarks(true)
        .withFaceDescriptor();

    if (!resultado) return null;

    const caja = resultado.detection.box;

    return {
        descriptor: Array.from(resultado.descriptor),
        lado: Math.min(caja.width, caja.height),
        confianza: resultado.detection.score,
    };
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
 * Tomar muestras de la cara de alguien con la cámara.
 *
 * Es lo que hace que el reconocimiento aguante el paso del tiempo: la foto del carnet es de hace
 * años, y cada muestra nueva es la misma cara con la luz, las gafas y el peinado de hoy.
 *
 * No guarda todo lo que ve. Una muestra sirve si es PARECIDA PERO NO IDÉNTICA a las que ya hay:
 *
 *   · demasiado parecida (por debajo de «yaLaTengo») no aporta nada y solo ocupa sitio;
 *   · demasiado distinta (por encima de «noEsLaMisma») probablemente no es esa persona —alguien
 *     se cruzó por delante— y guardarla envenenaría su ficha para siempre.
 *
 * En medio está la variedad útil, que es justo lo que se busca.
 */
export function muestrasDeRostro(wire) {
    return {
        ...controlesDeCamara(),

        abierto: false,
        mensaje: '',
        raf: null,

        // Las muestras que ya tiene esa persona, para comparar contra ellas.
        conocidas: [],
        guardadas: 0,
        descartadas: 0,

        // Aporta variedad si está entre estas dos distancias.
        yaLaTengo: 0.25,
        noEsLaMisma: 0.62,

        async abrir(deviceId = null) {
            this.abierto = true;
            this.mensaje = 'Preparando…';
            this.guardadas = 0;
            this.descartadas = 0;

            try {
                this.conocidas = (await wire.muestrasParaComparar()) || [];

                await motor();
                await this.encenderCamara(deviceId);

                this.mensaje = 'Mira a la cámara y muévete un poco: de frente, de lado, con y sin gafas.';
                this.buscar();
            } catch (e) {
                this.mensaje = 'No se pudo abrir la cámara: ' + (e.message || e.name);
            }
        },

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
                    } catch (e) {}

                    if (descriptor) {
                        await this.valorar(descriptor);
                    }
                }

                setTimeout(() => { if (this.abierto) this.raf = requestAnimationFrame(tick); }, 400);
            };

            tick();
        },

        /** Decide si esta cara aporta algo y, si aporta, la guarda. */
        async valorar(descriptor) {
            if (this.conocidas.length > 0) {
                const cerca = Math.min(...this.conocidas.map((d) => distancia(descriptor, d)));

                if (cerca < this.yaLaTengo) {
                    this.mensaje = 'Esa pose ya la tengo. Gira un poco la cara, o cambia la luz.';
                    return;
                }

                if (cerca > this.noEsLaMisma) {
                    this.descartadas++;
                    this.mensaje = 'Esa cara no se parece a la de esta persona: no la guardo.';
                    return;
                }
            }

            await wire.guardarMuestra(descriptor);

            this.conocidas.push(descriptor);
            this.guardadas++;
            this.avisarDeLectura();
            this.mensaje = 'Guardada (' + this.guardadas + '). Cambia un poco la pose para la siguiente.';
        },

        pararBusqueda() {
            if (this.raf) cancelAnimationFrame(this.raf);
            this.raf = null;
        },

        cerrar() {
            this.abierto = false;
            this.pararBusqueda();
            this.apagarCamara();
            wire.terminadoDeTomarMuestras();
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

        /*
         * Las cuatro condiciones que hacen falta para decir un nombre. Todas, no una.
         *
         * Con solo la primera —«el más parecido está bastante cerca»— el sistema confunde
         * personas, y eso es lo peor que puede hacer: un nombre equivocado se cree, mientras que
         * un «no lo reconozco» solo obliga a usar el carnet.
         */

        // 1. Estar cerca. Más exigente que antes: con casi trescientas caras en la galería, algo
        //    a media distancia se parece a demasiada gente.
        umbral: 0.45,

        // 2. Y estar MÁS cerca que el segundo, con holgura. Si el primero está a 0,44 y el segundo
        //    a 0,46, elegir el primero es lanzar una moneda: ahí no se dice nada.
        margen: 0.06,

        // 3. Verse bien. Una cara pequeña o de perfil da unos números pobres que caen a media
        //    distancia de todo el mundo, y son las que provocan las confusiones.
        ladoMinimo: 110,
        confianzaMinima: 0.75,

        // 4. Repetirse. El mismo candidato tiene que ganar dos cuadros seguidos: un cuadro malo
        //    puede acertar por casualidad, dos seguidos con la misma persona ya no.
        confirmacionesNecesarias: 2,

        // A quién va ganando y desde cuántos cuadros.
        candidato: null,
        vecesSeguidas: 0,

        /*
         * Se empieza por la cámara principal —la de atrás—, no por la de selfie.
         *
         * Quien sostiene el teléfono es el vigilante y a quien hay que mirar es al que tiene
         * delante: apunta, no se retrata. Además la trasera de cualquier teléfono ve mucho mejor
         * que la frontal, y aquí la calidad de la imagen es lo que decide si se reconoce o no.
         */
        caraActual: 'environment',

        async abrir(deviceId = null) {
            this.abierto = true;
            this.cargando = true;
            this.mensaje = 'Preparando…';

            try {
                if (this.galeria.length === 0) {
                    this.galeria = (await wire.galeriaParaReconocer()) || [];

                    // Lo estricto que se pone: se ajusta desde Reconocimiento facial, porque el
                    // punto bueno depende de las fotos que haya y de cuánta gente.
                    const ajustes = await wire.ajustesDeRostro();

                    if (ajustes) {
                        this.umbral = ajustes.umbral ?? this.umbral;
                        this.margen = ajustes.margen ?? this.margen;
                        this.confirmacionesNecesarias = ajustes.confirmaciones ?? this.confirmacionesNecesarias;
                    }
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
                    let cara = null;

                    try {
                        cara = await caraDe(video);
                    } catch (e) {
                        // Un cuadro que falla no es nada: se intenta con el siguiente.
                    }

                    if (cara) {
                        this.valorar(cara);

                        if (this.candidato && this.vecesSeguidas >= this.confirmacionesNecesarias) {
                            // Lo mismo que al leer un carnet: aquí el vigilante está mirando a la
                            // persona que tiene delante, no al teléfono.
                            this.avisarDeLectura();

                            this.mensaje = 'Es ' + this.candidato.nombre + '. Comprueba la foto.';
                            wire.rostroReconocido(this.candidato.cedula, Number(this.candidato.distancia.toFixed(3)));
                            this.cerrar();
                            return;
                        }
                    }
                }

                // Con pausa entre cuadros: el cálculo ocupa la pestaña y a toda velocidad deja el
                // teléfono caliente y la imagen a tirones, sin reconocer antes por ello.
                setTimeout(() => { if (this.abierto) this.raf = requestAnimationFrame(tick); }, 300);
            };

            tick();
        },

        /**
         * Decide si esta cara identifica a alguien, y lo dice cuando NO.
         *
         * Que no reconozca es un resultado, no un fallo: obliga a usar el carnet y ahí se acabó.
         * Decir un nombre equivocado, en cambio, mete a otra persona en el registro. Por eso cada
         * condición que no se cumple tiene su propio mensaje: así el vigilante sabe si acercarse,
         * si insistir o si dejarlo.
         */
        valorar(cara) {
            // Se ve mal: unos números pobres caen a media distancia de todo el mundo.
            if (cara.lado < this.ladoMinimo) {
                this.olvidarCandidato();
                this.mensaje = 'Acércate un poco: la cara se ve pequeña.';
                return;
            }

            if (cara.confianza < this.confianzaMinima) {
                this.olvidarCandidato();
                this.mensaje = 'Mira de frente a la cámara.';
                return;
            }

            // De cada persona, su MEJOR muestra. No la media: el promedio entre la cara del carnet
            // de hace años y la de hoy es una cara que no existe.
            const parecidos = this.galeria
                .map((fila) => ({
                    ...fila,
                    distancia: Math.min(...fila.descriptores.map((d) => distancia(cara.descriptor, d))),
                }))
                .sort((uno, otro) => uno.distancia - otro.distancia);

            const mejor = parecidos[0];
            const segundo = parecidos[1];

            if (!mejor || mejor.distancia > this.umbral) {
                this.olvidarCandidato();
                this.mensaje = 'No reconozco esa cara. Usa el carnet o teclea la cédula.';
                return;
            }

            // Dos candidatos igual de cerca: elegir uno sería lanzar una moneda con el nombre de
            // una persona. Se dicen los dos y decide el vigilante con el carnet.
            if (segundo && segundo.distancia - mejor.distancia < this.margen) {
                this.olvidarCandidato();
                this.mensaje = 'Puede ser ' + mejor.nombre + ' o ' + segundo.nombre + '. Usa el carnet.';
                return;
            }

            // Mismo candidato que el cuadro anterior: se acumula. Otro distinto: vuelta a empezar.
            if (this.candidato && this.candidato.cedula === mejor.cedula) {
                this.vecesSeguidas++;
            } else {
                this.candidato = mejor;
                this.vecesSeguidas = 1;
            }

            this.candidato = mejor;

            if (this.vecesSeguidas < this.confirmacionesNecesarias) {
                this.mensaje = 'Comprobando… no te muevas.';
            }
        },

        olvidarCandidato() {
            this.candidato = null;
            this.vecesSeguidas = 0;
        },

        pararBusqueda() {
            if (this.raf) cancelAnimationFrame(this.raf);
            this.raf = null;
        },

        cerrar() {
            this.abierto = false;
            this.olvidarCandidato();
            this.pararBusqueda();
            this.apagarCamara();
        },
    };
}
