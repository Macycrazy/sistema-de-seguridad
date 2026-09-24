/**
 * El «¿seguro?» del sistema, en vez del cuadro gris del navegador.
 *
 * Antes esto lo hacía «wire:confirm», que por dentro llama al confirm() del navegador: un cuadro
 * que no se parece en nada al resto, que cada navegador pinta a su manera y que además enseña la
 * dirección IP del servidor encima del mensaje. Para quien lo usa, un aviso que no se parece al
 * sistema es un aviso que parece de otro —y los avisos que parecen de otro se cierran sin leer—.
 *
 * Esto no trae ninguna librería. Un cuadro de confirmación son treinta líneas y una librería
 * traería su propio aspecto, que habría que pelear para que se pareciera a este.
 *
 * CÓMO SE USA, desde el Blade:
 *
 *     <x-boton data-confirmar="¿Quitar el puesto A-1?">Quitar</x-boton>
 *
 * y, si hace falta afinarlo:
 *
 *     data-confirmar-titulo="¿Quitar la plaza?"     el encabezado (por omisión, «¿Seguro?»)
 *     data-confirmar-aceptar="Sí, quitar"           el botón de confirmar
 *     data-confirmar-tono="peligro"                 lo pinta de rojo: para lo que no se deshace
 *
 * POR QUÉ SE ENGANCHA EN «document» Y EN FASE DE CAPTURA: el botón lleva su propio wire:click, y
 * Livewire escucha en el elemento. Un oyente en el documento y en captura corre ANTES que ese, que
 * es la única forma de poder detener la acción y dejarla pendiente de la respuesta. Al aceptar se
 * marca el botón y se le vuelve a dar, y entonces sí pasa de largo.
 *
 * Va en JavaScript pelado, sin Alpine, a propósito: Alpine solo existe en las páginas que montan
 * un componente de Livewire, y esto tiene que funcionar en todas.
 */

const COLORES = {
    peligro: 'var(--color-alto)',
    normal: 'var(--color-parte1)',
};

let abierto = null;

/** Pinta el cuadro y resuelve a true o false según lo que se conteste. */
function preguntar({ titulo, mensaje, aceptar, tono }) {
    return new Promise((resolver) => {
        const fondo = document.createElement('div');
        fondo.setAttribute('role', 'dialog');
        fondo.setAttribute('aria-modal', 'true');
        fondo.setAttribute('aria-label', titulo);
        fondo.style.cssText = `
            position: fixed; inset: 0; z-index: 9999;
            display: flex; align-items: center; justify-content: center; padding: 16px;
            background: rgb(15 23 42 / 0.55); backdrop-filter: blur(2px);
            animation: confirmar-entra 120ms ease-out;
        `;

        const panel = document.createElement('div');
        panel.style.cssText = `
            width: 100%; max-width: 27rem; background: #fff; border-radius: 8px;
            box-shadow: 0 20px 40px rgb(15 23 42 / 0.25); padding: 22px 22px 18px;
            animation: confirmar-sube 140ms cubic-bezier(.2,.8,.3,1);
        `;

        const h = document.createElement('p');
        h.textContent = titulo;
        h.style.cssText = 'margin:0 0 6px; font-size:1.05rem; font-weight:700; color:#0f172a;';

        const p = document.createElement('p');
        p.textContent = mensaje;
        p.style.cssText = 'margin:0; font-size:.875rem; line-height:1.5; color:#475569;';

        const pie = document.createElement('div');
        pie.style.cssText = 'margin-top:20px; display:flex; gap:10px; justify-content:flex-end; flex-wrap:wrap;';

        const comun = `
            border-radius:4px; padding:10px 16px; font-size:.875rem; font-weight:600;
            letter-spacing:.01em; cursor:pointer; border:1px solid transparent;
        `;

        const noBoton = document.createElement('button');
        noBoton.type = 'button';
        noBoton.textContent = 'Cancelar';
        noBoton.style.cssText = comun + 'background:#fff; color:#334155; border-color:#cbd5e1;';

        const siBoton = document.createElement('button');
        siBoton.type = 'button';
        siBoton.textContent = aceptar;
        siBoton.style.cssText = comun + `background:${COLORES[tono] || COLORES.normal}; color:#fff;`;

        pie.append(noBoton, siBoton);
        panel.append(h, p, pie);
        fondo.append(panel);

        const devolvereElFoco = document.activeElement;

        const cerrar = (respuesta) => {
            if (abierto !== fondo) return;
            abierto = null;
            document.removeEventListener('keydown', teclado, true);
            fondo.remove();
            // El foco vuelve a donde estaba: quien navega con el teclado no acaba en la nada.
            if (devolvereElFoco && devolvereElFoco.focus) devolvereElFoco.focus();
            resolver(respuesta);
        };

        const teclado = (e) => {
            if (e.key === 'Escape') { e.preventDefault(); cerrar(false); }
            if (e.key === 'Enter') { e.preventDefault(); cerrar(true); }
        };

        noBoton.addEventListener('click', () => cerrar(false));
        siBoton.addEventListener('click', () => cerrar(true));
        // Tocar fuera es cancelar, nunca aceptar: lo que se toca sin querer no debe borrar nada.
        fondo.addEventListener('click', (e) => { if (e.target === fondo) cerrar(false); });
        document.addEventListener('keydown', teclado, true);

        document.body.append(fondo);
        abierto = fondo;
        siBoton.focus();
    });
}

/** Deja el cuadro enganchado a todo lo que lleve «data-confirmar», ahora y lo que venga después. */
export function instalarConfirmaciones() {
    if (!document.getElementById('confirmar-animaciones')) {
        const estilos = document.createElement('style');
        estilos.id = 'confirmar-animaciones';
        estilos.textContent = `
            @keyframes confirmar-entra { from { opacity: 0 } to { opacity: 1 } }
            @keyframes confirmar-sube { from { opacity: 0; transform: translateY(8px) scale(.98) } to { opacity: 1; transform: none } }
            @media (prefers-reduced-motion: reduce) {
                [role="dialog"] { animation: none !important }
            }
        `;
        document.head.append(estilos);
    }

    document.addEventListener('click', (evento) => {
        const boton = evento.target.closest('[data-confirmar]');

        if (!boton) return;

        // Segunda vuelta: ya contestó que sí, se le deja pasar hasta Livewire.
        if (boton.dataset.confirmado === 'si') {
            delete boton.dataset.confirmado;

            return;
        }

        evento.preventDefault();
        evento.stopImmediatePropagation();

        preguntar({
            titulo: boton.dataset.confirmarTitulo || '¿Seguro?',
            mensaje: boton.dataset.confirmar,
            aceptar: boton.dataset.confirmarAceptar || 'Sí, continuar',
            tono: boton.dataset.confirmarTono || 'normal',
        }).then((siDijoQueSi) => {
            if (!siDijoQueSi) return;

            boton.dataset.confirmado = 'si';
            boton.click();
        });
    }, true);
}
