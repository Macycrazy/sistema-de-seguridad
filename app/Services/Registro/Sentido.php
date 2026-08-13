<?php

namespace App\Services\Registro;

// Igual que TipoDePersona: los valores son los que entiende <x-etiqueta tipo="...">.
enum Sentido: string
{
    case Entrada = 'entrada';
    case Salida = 'salida';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Entrada => 'Entrada',
            self::Salida => 'Salida',
        };
    }
}
