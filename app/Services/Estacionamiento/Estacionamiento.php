<?php

namespace App\Services\Estacionamiento;

use App\Models\Movimiento;
use App\Services\Alertas\UmbralesDeAlerta;
use App\Services\DatosVehiculo;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
        // El último movimiento de cada persona (DISTINCT ON), y de ahí solo los que están dentro
        // (entrada) y traían vehículo.
        $ultimos = Movimiento::query()
            ->selectRaw('distinct on (persona_id) persona_id, tipo, tipo_vehiculo, marca, modelo, color, placa, ocurrio_en')
            ->orderBy('persona_id')
            ->orderByDesc('ocurrio_en')
            ->orderByDesc('id');

        return DB::query()
            ->fromSub($ultimos, 'u')
            ->join('personas', 'personas.id', '=', 'u.persona_id')
            ->where('u.tipo', Movimiento::ENTRADA)
            ->whereNotNull('u.tipo_vehiculo')
            ->orderByDesc('u.ocurrio_en')
            ->get(['u.persona_id', 'u.tipo_vehiculo', 'u.marca', 'u.modelo', 'u.color', 'u.placa', 'u.ocurrio_en', 'personas.nombre', 'personas.cedula'])
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
