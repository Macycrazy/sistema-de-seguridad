<?php

namespace App\Services\Registro;

use App\Services\DatosVehiculo;
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
        // Con qué vehículo se hizo ESTE movimiento, congelado tal cual estaba ese día. Nulo o vacío
        // = a pie. Sin esto, saber a quién pertenece un vehículo en el registro era imposible.
        public ?DatosVehiculo $vehiculo = null,
    ) {}

    /** Se hizo con vehículo (no a pie). */
    public function tieneVehiculo(): bool
    {
        return $this->vehiculo !== null && ! $this->vehiculo->vacio();
    }

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
