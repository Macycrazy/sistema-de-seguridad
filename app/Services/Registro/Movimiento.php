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
    /**
     * Con qué vehículo se hizo ESTE movimiento. Vacío = a pie.
     *
     * Es una lista y no un vehículo suelto porque el dato ya no vive en el asiento: sale de las
     * estadías del estacionamiento (ver App\Services\Estacionamiento\VehiculosPorMovimiento), y
     * quien movió dos vehículos el mismo día tiene dos —raro, pero ocultarle uno al guardia sería
     * peor que enseñar los dos—. Los asientos viejos, de cuando la puerta congelaba el vehículo
     * encima, siguen trayendo el suyo.
     *
     * @var list<DatosVehiculo>
     */
    public array $vehiculos;

    /**
     * @param  list<DatosVehiculo>  $vehiculos
     * @param  bool|null  $aPie  Lo que se dijo al marcar. Nulo = no se anotó, que NO es lo mismo
     *                           que haber venido caminando.
     */
    public function __construct(
        public string $id,
        public Persona $persona,
        public Sentido $sentido,
        public CarbonImmutable $ocurrioEn,
        public string $registradoPor,
        array $vehiculos = [],
        public ?bool $aPie = null,
    ) {
        // Los vacíos son «no trajo vehículo», no un vehículo: se caen aquí y así nadie más tiene
        // que acordarse de comprobarlo.
        $this->vehiculos = array_values(array_filter($vehiculos, fn (DatosVehiculo $v) => ! $v->vacio()));
    }

    /** Se hizo con vehículo (no a pie). */
    public function tieneVehiculo(): bool
    {
        return $this->vehiculos !== [];
    }

    /** El vehículo, para cuando solo cabe uno (una celda estrecha, una columna del Excel). */
    public function vehiculo(): ?DatosVehiculo
    {
        return $this->vehiculos[0] ?? null;
    }

    /**
     * Cómo se hizo: «A pie», las placas, o nada si no se anotó.
     *
     * Los tres casos son distintos y se distinguen a propósito. Un guion no se lee como «vino
     * caminando» —se lee como que falta algo—, y decir «a pie» de un asiento donde nadie anotó
     * nada sería afirmar algo que no consta.
     */
    public function comoFue(): ?string
    {
        if ($this->tieneVehiculo()) {
            return $this->vehiculosComoTexto();
        }

        return $this->aPie === true ? 'A pie' : null;
    }

    /** Todos, dichos de corrido: «AB123CD, XY987ZW». Para el Excel y los sitios de una línea. */
    public function vehiculosComoTexto(): string
    {
        return implode(', ', array_map(fn (DatosVehiculo $v) => $v->descripcion(), $this->vehiculos));
    }

    public function hora(): string
    {
        // 12 horas con am/pm, igual que la puerta (App\Models\Movimiento::FORMATO_HORA).
        return $this->ocurrioEn->format('g:i a');
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
