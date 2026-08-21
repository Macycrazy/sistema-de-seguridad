<?php

namespace App\Services\Estacionamiento;

use App\Models\Movimiento;
use App\Models\VehiculoFijo;
use App\Services\DatosVehiculo;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Con qué vehículo entró —y con cuál salió— cada quien: el puente entre el registro de personas y
 * el estacionamiento.
 *
 * Hasta que la puerta dejó de manejar vehículos, cada movimiento llevaba el suyo congelado encima.
 * Ya no: la persona se marca en la puerta y el vehículo se anota como una ESTADÍA aparte
 * (App\Models\VehiculoFijo), con su conductor de entrada y su conductor de salida —que pueden ser
 * distintos—. Los campos de vehículo del movimiento quedaron ahí, vacíos para siempre.
 *
 * Esto vuelve a unir las dos mitades sin inventar nada: el vehículo de un asiento es el que ESA
 * persona movió, en ESE sentido, en ESE día. Si metió un carro por la mañana, sale en su entrada;
 * si se llevó otro por la tarde, sale en su salida. Y si un carro entró con uno y salió con otro,
 * cada quien carga con el suyo —que es justo lo que hay que poder ver.
 *
 * Se empareja por día y no por cercanía de hora a propósito: la hora de la estadía la teclea el
 * guardia cuando puede, y adivinar «este de las 8:02 va con aquel de las 8:00» acertaría casi
 * siempre, que en seguridad es otra manera de decir que a veces miente. Quien movió dos vehículos
 * el mismo día ve los dos.
 */
final class VehiculosPorMovimiento
{
    /**
     * El mapa para un lote de movimientos, en UNA consulta: clave → los vehículos de ese asiento.
     *
     * Se resuelve de golpe y no asiento por asiento porque el registro pinta 25 filas por página y
     * el histórico de una persona, todas las suyas.
     *
     * @param  Collection<int, Movimiento>  $movimientos
     * @return array<string, list<DatosVehiculo>>
     */
    public function mapa(Collection $movimientos): array
    {
        if ($movimientos->isEmpty()) {
            return [];
        }

        $personas = $movimientos->pluck('persona_id')->filter()->unique()->values()->all();

        if ($personas === []) {
            return [];
        }

        $momentos = $movimientos->map(fn (Movimiento $m) => CarbonImmutable::parse($m->ocurrio_en));
        $desde = $momentos->min()->startOfDay();
        $hasta = $momentos->max()->endOfDay();

        $estadias = VehiculoFijo::query()
            ->where(function ($consulta) use ($personas, $desde, $hasta) {
                $consulta
                    ->where(fn ($q) => $q->whereIn('conductor_id', $personas)->whereBetween('entro_en', [$desde, $hasta]))
                    ->orWhere(fn ($q) => $q->whereIn('salida_conductor_id', $personas)->whereBetween('salio_en', [$desde, $hasta]));
            })
            ->get();

        $mapa = [];

        foreach ($estadias as $estadia) {
            $datos = DatosVehiculo::desde(
                $estadia->tipo_vehiculo,
                $estadia->marca,
                null,
                $estadia->color,
                $estadia->placa,
            );

            // Quien lo metió carga con la entrada; quien se lo llevó, con la salida. Cada extremo
            // por su lado: un carro puede entrar con uno y salir con otro.
            $extremos = [
                [$estadia->conductor_id, Movimiento::ENTRADA, $estadia->entro_en],
                [$estadia->salida_conductor_id, Movimiento::SALIDA, $estadia->salio_en],
            ];

            foreach ($extremos as [$personaId, $sentido, $cuando]) {
                if ($personaId === null || $cuando === null || ! in_array($personaId, $personas)) {
                    continue;
                }

                $momento = CarbonImmutable::parse($cuando);

                if ($momento->lt($desde) || $momento->gt($hasta)) {
                    continue;
                }

                $mapa[self::clave($personaId, $sentido, $momento)][] = $datos;
            }
        }

        return $mapa;
    }

    /** Los vehículos de un asiento concreto dentro de un mapa ya resuelto. */
    public static function de(array $mapa, Movimiento $movimiento): array
    {
        $clave = self::clave(
            $movimiento->persona_id,
            $movimiento->tipo,
            CarbonImmutable::parse($movimiento->ocurrio_en),
        );

        return $mapa[$clave] ?? [];
    }

    /** Persona + sentido + día: lo que hace que un vehículo sea de este asiento y no de otro. */
    public static function clave(int|string $personaId, string $sentido, CarbonImmutable $cuando): string
    {
        return $personaId.'|'.$sentido.'|'.$cuando->toDateString();
    }
}
