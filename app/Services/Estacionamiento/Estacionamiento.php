<?php

namespace App\Services\Estacionamiento;

use App\Models\Movimiento;
use App\Services\Alertas\UmbralesDeAlerta;
use App\Services\DatosVehiculo;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Qué hay en el estacionamiento ahora mismo, a partir de lo que el marcaje ya guarda.
 *
 * No hace falta una tabla nueva ni tocar la puerta: cada asiento congela el vehículo con el que
 * se entró (tipo, marca, modelo, color, placa). Un vehículo está dentro si su dueño está dentro
 * —su último movimiento fue una entrada— y esa entrada traía vehículo. Cuando esa persona marca
 * la salida, su vehículo deja de contar.
 *
 * Es para el guardia del portón: ver cuántos vehículos hay, de qué tipo, y con qué placa.
 */
final class Estacionamiento
{
    public function __construct(private UmbralesDeAlerta $umbrales) {}

    /**
     * Los vehículos dentro ahora, el que entró más tarde primero.
     *
     * @return Collection<int, object{persona_id:int, nombre:string, cedula:?string, tipo_vehiculo:string, placa:?string, ocurrio_en:string, vehiculo:DatosVehiculo}>
     */
    public function vehiculosDentro(): Collection
    {
        // El último movimiento de cada persona, y de ahí solo los que están dentro (entrada) y
        // traían vehículo. El «último de cada persona» vive en el modelo y vale en las dos bases;
        // aquí iba antes un «distinct on», que es solo de PostgreSQL.
        return Movimiento::ultimoDeCadaPersona()
            ->join('personas', 'personas.id', '=', 'movimientos.persona_id')
            ->where('movimientos.tipo', Movimiento::ENTRADA)
            ->whereNotNull('movimientos.tipo_vehiculo')
            ->orderByDesc('movimientos.ocurrio_en')
            ->get([
                'movimientos.persona_id', 'movimientos.tipo_vehiculo', 'movimientos.marca',
                'movimientos.modelo', 'movimientos.color', 'movimientos.placa',
                'movimientos.ocurrio_en', 'personas.nombre', 'personas.cedula',
            ])
            ->map(function ($fila) {
                // El mismo objeto de datos que usa la puerta, para que la placa y la descripción se
                // lean igual en las dos pantallas.
                $fila->vehiculo = DatosVehiculo::desdeModelo($fila);

                return $fila;
            });
    }

    /** Cuántos vehículos hay dentro ahora. */
    public function cuantosDentro(): int
    {
        return $this->vehiculosDentro()->count();
    }

    /**
     * El desglose de lo que hay dentro por tipo.
     *
     * @return array{carro:int, moto:int}
     */
    public function porTipoDentro(): array
    {
        $dentro = $this->vehiculosDentro();

        return [
            'carro' => $dentro->where('tipo_vehiculo', DatosVehiculo::CARRO)->count(),
            'moto' => $dentro->where('tipo_vehiculo', DatosVehiculo::MOTO)->count(),
        ];
    }

    /** El aforo configurado (0 = sin tope). */
    public function aforo(): int
    {
        return $this->umbrales->aforoEstacionamiento();
    }

    /** «Desde cuándo» está un vehículo, para la lista. */
    public function desde(string $ocurrioEn): CarbonImmutable
    {
        return CarbonImmutable::parse($ocurrioEn);
    }
}
