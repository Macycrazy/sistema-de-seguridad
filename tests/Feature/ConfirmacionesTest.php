<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * El «¿seguro?» es el del sistema, no el del navegador.
 *
 * «wire:confirm» llama por dentro al confirm() del navegador: un cuadro gris que no se parece en
 * nada al resto, distinto en cada navegador, y que enseña la dirección IP del servidor encima del
 * mensaje. Un aviso que no parece del sistema es un aviso que se cierra sin leer.
 *
 * Se comprueba aquí y no a ojo porque escribir «wire:confirm» es lo natural —está en toda la
 * documentación de Livewire— y volvería a colarse en el primer botón que alguien añada.
 */
class ConfirmacionesTest extends TestCase
{
    #[Test]
    public function ninguna_vista_usa_el_cuadro_del_navegador(): void
    {
        $culpables = [];

        foreach ($this->vistas() as $vista) {
            if (str_contains(file_get_contents($vista), 'wire:confirm')) {
                $culpables[] = str_replace(resource_path('views/'), '', $vista);
            }
        }

        $this->assertSame([], $culpables,
            'Estas vistas usan wire:confirm, que abre el cuadro del navegador. Se usa data-confirmar.');
    }

    #[Test]
    public function el_cuadro_propio_se_carga_en_todas_las_paginas(): void
    {
        // Va en el punto de entrada de Vite, que sí está en todas. Si se colgara de Alpine solo
        // funcionaría en las páginas que montan un componente de Livewire.
        $entrada = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString("from './confirmar.js'", $entrada);
        $this->assertStringContainsString('instalarConfirmaciones()', $entrada);
    }

    #[Test]
    public function lo_que_borra_de_verdad_pide_confirmacion(): void
    {
        /*
         * La regla es por lo que HACE la acción, no por el color. El rojo aquí significa varias
         * cosas —«quita acceso», «abre el formulario de salida»— y desactivar, por ejemplo, se
         * deshace reactivando: pedir confirmación para todo enseña a aceptar sin leer.
         *
         * Lo que no se deshace es borrar una fila del catálogo. Eso sí se pregunta, siempre.
         */
        $sinConfirmar = [];

        foreach ($this->vistas() as $vista) {
            $texto = file_get_contents($vista);

            foreach (explode('<x-boton', $texto) as $i => $trozo) {
                if ($i === 0 || ! preg_match('/^((?:"[^"]*"|\'[^\']*\'|[^>"\'])*)>/', $trozo, $partes)) {
                    continue;
                }

                $etiqueta = $partes[1];

                if (! preg_match('/wire:click="(eliminar|borrar)[A-Za-z]*\(/i', $etiqueta)) {
                    continue;
                }

                if (! str_contains($etiqueta, 'data-confirmar')) {
                    $sinConfirmar[] = str_replace(resource_path('views/'), '', $vista)
                        .' · '.trim(strip_tags(explode('</x-boton>', $trozo)[0] ?? ''));
                }
            }
        }

        $this->assertSame([], $sinConfirmar, 'Estos botones borran sin preguntar.');
    }

    /** @return list<string> */
    private function vistas(): array
    {
        $encontradas = [];
        $arbol = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views')));

        foreach ($arbol as $archivo) {
            if ($archivo->isFile() && str_ends_with($archivo->getFilename(), '.blade.php')) {
                $encontradas[] = $archivo->getPathname();
            }
        }

        return $encontradas;
    }
}
