<?php

namespace App\Services\Estacionamiento;

use App\Models\VehiculoDeFlota;
use App\Models\VehiculoFijo;
use App\Services\DatosVehiculo;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * El catálogo de la flota de la empresa: los vehículos propios, cargados una vez para elegirlos al
 * anotarlos, sin teclear la placa cada vez.
 *
 * La pantalla no decide nada: pregunta aquí, donde se valida. La administra el del estacionamiento.
 */
class Flota
{
    /** @return Collection<int, VehiculoDeFlota> */
    public function todos(): Collection
    {
        return VehiculoDeFlota::query()->orderBy('placa')->get();
    }

    /**
     * Los que se pueden anotar ahora: activos y que no estén ya dentro (sin una estadía abierta).
     *
     * @return Collection<int, VehiculoDeFlota>
     */
    public function disponibles(): Collection
    {
        $dentro = VehiculoFijo::query()->abiertos()->whereNotNull('flota_id')->pluck('flota_id')->all();

        return VehiculoDeFlota::query()
            ->activos()
            ->when($dentro !== [], fn ($q) => $q->whereNotIn('id', $dentro))
            ->orderBy('placa')
            ->get();
    }

    /**
     * Los vehículos de la empresa que salieron y no han vuelto, del que lleva más fuera al que
     * menos: con quién se lo llevó, quién lo dejó salir y desde cuándo.
     *
     * Un vehículo de la empresa que sale para un trámite vuelve. Si no vuelve, alguien tiene que
     * enterarse sin ir a mirarlo vehículo por vehículo, que es lo que no se hace nunca.
     *
     * Se salta los que NUNCA han entrado: están en el catálogo pero no han pisado el sitio, así
     * que no se han «ido» a ninguna parte —avisar de ellos sería ruido desde el día que se cargan.
     *
     * @return Collection<int, object>
     */
    public function fuera(): Collection
    {
        $activos = VehiculoDeFlota::query()->activos()->get()->keyBy('id');

        if ($activos->isEmpty()) {
            return collect();
        }

        // La flota de una empresa cabe de sobra en memoria: se agrupa aquí y no en SQL, que además
        // se dice distinto en Postgres y en SQLite.
        $porVehiculo = VehiculoFijo::query()
            ->whereIn('flota_id', $activos->keys())
            ->with('salidaUsuario')
            ->orderByDesc('entro_en')
            ->orderByDesc('id')
            ->get()
            ->groupBy('flota_id');

        return $activos
            ->map(function (VehiculoDeFlota $vehiculo) use ($porVehiculo) {
                $ultima = ($porVehiculo->get($vehiculo->id) ?? collect())->first();

                // Nunca estuvo aquí, o está aquí ahora: en ninguno de los dos casos falta.
                if ($ultima === null || $ultima->salio_en === null) {
                    return null;
                }

                return (object) [
                    'placa' => $vehiculo->placa,
                    'descripcion' => $vehiculo->descripcion(),
                    'tipo_vehiculo' => $vehiculo->tipo_vehiculo,
                    'salio_en' => CarbonImmutable::parse($ultima->salio_en),
                    'seLoLlevo' => $ultima->salida_conductor_nombre,
                    'loDejoSalir' => $ultima->salidaUsuario?->nombre ?? $ultima->salidaUsuario?->usuario,
                ];
            })
            ->filter()
            ->sortBy('salio_en')
            ->values();
    }

    /**
     * @throws ValidationException
     */
    public function guardar(string $placa, string $tipoVehiculo, ?string $marca = null, ?string $color = null, ?string $nota = null): VehiculoDeFlota
    {
        $placa = DatosVehiculo::normalizarPlaca($placa);
        $tipo = DatosVehiculo::normalizarTipo($tipoVehiculo);

        if ($placa === null) {
            throw ValidationException::withMessages([
                'placaFlota' => 'Hace falta la placa del vehículo.',
            ]);
        }

        return VehiculoDeFlota::updateOrCreate(
            ['placa' => $placa],
            [
                'tipo_vehiculo' => $tipo,
                'marca' => ($marca = trim((string) $marca)) === '' ? null : mb_substr($marca, 0, 40),
                'color' => ($color = trim((string) $color)) === '' ? null : mb_substr($color, 0, 30),
                'nota' => ($nota = trim((string) $nota)) === '' ? null : mb_substr($nota, 0, 120),
            ],
        );
    }

    public function eliminar(VehiculoDeFlota $vehiculo): void
    {
        $vehiculo->delete();
    }
}
