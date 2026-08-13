<?php

namespace App\Services\Registro;

use Carbon\CarbonImmutable;

/**
 * Un asiento del registro: esta persona, en este sentido, a esta hora, anotado por este usuario.
 *
 * Es inmutable a propósito, y no por comodidad de PHP: la regla del módulo es que los
 * movimientos no se editan ni se borran. Un error se corrige con un movimiento nuevo.
 */
final readonly class Movimiento
{
    public function __construct(
        public string $id,
        public Persona $persona,
        public Sentido $sentido,
        public CarbonImmutable $ocurrioEn,
        public string $registradoPor,
    ) {}

    public function hora(): string
    {
        return $this->ocurrioEn->format('H:i');
    }

    public function fecha(): string
    {
        return $this->ocurrioEn->format('d/m/Y');
    }

    public function esEntrada(): bool
    {
        return $this->sentido === Sentido::Entrada;
    }
}
