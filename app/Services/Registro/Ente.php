<?php

namespace App\Services\Registro;

/**
 * Los tres entes que comparten el edificio y, por tanto, el puesto de vigilancia.
 *
 * Sale del listado de personal real: la columna ENTE separa al personal de CIIP, al de
 * Marca País y al de VENAPP. Una persona pertenece a uno solo.
 */
enum Ente: string
{
    case Ciip = 'ciip';
    case MarcaPais = 'marca-pais';
    case Venapp = 'venapp';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Ciip => 'CIIP',
            self::MarcaPais => 'Marca País',
            self::Venapp => 'VENAPP',
        };
    }
}
