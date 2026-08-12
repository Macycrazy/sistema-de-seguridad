<?php

namespace App\Services\Registro;

// Los valores coinciden a propósito con los que acepta <x-etiqueta tipo="...">,
// para pasarlos a la vista sin traducir nada por el camino.
enum TipoDePersona: string
{
    case Trabajador = 'trabajador';
    case Invitado = 'invitado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Trabajador => 'Trabajador',
            self::Invitado => 'Invitado',
        };
    }
}
