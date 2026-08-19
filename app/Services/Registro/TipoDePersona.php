<?php

namespace App\Services\Registro;

// Los valores coinciden a propósito con los que acepta <x-etiqueta tipo="...">,
// para pasarlos a la vista sin traducir nada por el camino.
enum TipoDePersona: string
{
    case Trabajador = 'trabajador';
    case Invitado = 'invitado';

    /**
     * Cómo se le llama en pantalla.
     *
     * El caso se llama «Invitado» y su valor guardado es «invitado» —así está en la base y así lo
     * usan las otras partes—, pero al público se le dice VISITANTE. Cambiar el valor sería un
     * cambio de esquema; cambiar el rótulo es esto.
     */
    public function etiqueta(): string
    {
        return match ($this) {
            self::Trabajador => 'Trabajador',
            self::Invitado => 'Visitante',
        };
    }
}
