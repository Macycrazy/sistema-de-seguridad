/**
 * Comprueba que los componentes de Alpine no se llamen a sí mismos métodos que no tienen.
 *
 * Es el fallo que ya se ha colado tres veces y que no se ve desde PHP: el navegador dice
 * «mirarUna is not a function» y hasta ahí. Aquí se cargan los componentes de verdad, se miran
 * todas las llamadas «this.algo(» de su código y se comprueba que ese «algo» exista.
 *
 * No sustituye a probarlo en un navegador —no hay cámara ni DOM— pero caza lo barato:
 * un método mal puesto, un nombre cambiado a medias, una propiedad que se quedó sin mover.
 *
 * Se corre con «npm run comprobar».
 */
import { readFileSync } from 'node:fs';

/** Un doble de $wire: responde a cualquier método con una promesa vacía. */
const wireFalso = new Proxy({}, { get: () => async () => [] });

/** Lo que Alpine añade por su cuenta y no está en el objeto. */
const DE_ALPINE = new Set(['$refs', '$wire', '$el', '$watch', '$dispatch', '$nextTick', '$store']);

const componentes = [];

const { indiceDeRostros, rostroEnLaPuerta } = await import('../resources/js/rostros.js');
componentes.push(['indiceDeRostros', indiceDeRostros(wireFalso)]);
componentes.push(['rostroEnLaPuerta', rostroEnLaPuerta(wireFalso)]);

const { controlesDeCamara } = await import('../resources/js/camara.js');
componentes.push(['controlesDeCamara', controlesDeCamara()]);

let problemas = 0;

for (const [nombre, componente] of componentes) {
    const tiene = new Set(Object.keys(componente));

    // Los métodos se leen del código fuente de cada función del componente.
    for (const [clave, valor] of Object.entries(componente)) {
        if (typeof valor !== 'function') continue;

        const cuerpo = valor.toString();

        for (const [, llamado] of cuerpo.matchAll(/this\.([a-zA-Z_$][\w$]*)\s*\(/g)) {
            if (tiene.has(llamado) || DE_ALPINE.has(llamado)) continue;

            console.error(`  ${nombre}.${clave}() llama a this.${llamado}(), que no existe en el componente`);
            problemas++;
        }
    }
}

// Y que las plantillas no llamen a métodos inexistentes en su x-data.
const VISTAS = [
    ['resources/views/livewire/rostros/indice.blade.php', 'indiceDeRostros'],
    ['resources/views/livewire/marcar.blade.php', 'rostroEnLaPuerta'],
];

for (const [ruta, cual] of VISTAS) {
    const componente = componentes.find(([n]) => n === cual)?.[1];
    if (!componente) continue;

    const html = readFileSync(new URL('../' + ruta, import.meta.url), 'utf8');

    // Un JSON dentro de un atributo rompe el valor: las comillas del uno chocan con las del otro.
    if (html.includes(`${cual}($wire, [`) || html.includes(`${cual}($wire, {`)) {
        console.error(`  ${ruta}: el x-data de ${cual} lleva datos dentro; se piden a Livewire.`);
        problemas++;
    }
}

if (problemas > 0) {
    console.error(`\n${problemas} problema(s) en el JavaScript de los componentes.`);
    process.exit(1);
}

console.log('JavaScript de los componentes: sin llamadas a métodos que no existan.');
