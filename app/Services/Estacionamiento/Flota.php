<?php

namespace App\Services\Estacionamiento;

use App\Models\VehiculoDeFlota;
use App\Models\VehiculoFijo;
use App\Services\DatosVehiculo;
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
