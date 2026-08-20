<?php

namespace App\Services\Estacionamiento;

use App\Models\Puesto;
use App\Services\DatosVehiculo;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * El catálogo de plazas del estacionamiento, que administra el edificio.
 *
 * La pantalla no decide nada: pregunta aquí, donde se valida en el servidor. Un puesto se busca por
 * su código y se actualiza (no se duplica); quitarlo lo borra del catálogo, pero no toca el
 * histórico —«movimientos.puesto_id» es «nullOnDelete»—.
 */
class CatalogoDePuestos
{
    /** Los tipos que admite un puesto, con su etiqueta. El vacío es «cualquiera». */
    public const TIPOS = [
        '' => 'Cualquiera',
        DatosVehiculo::CARRO => 'Carro',
        DatosVehiculo::MOTO => 'Moto',
    ];

    /** @return Collection<int, Puesto> */
    public function todos(): Collection
    {
        return Puesto::query()->orderBy('orden')->orderBy('codigo')->get();
    }

    public function guardar(string $codigo, ?string $tipo = null, ?string $zona = null, ?int $orden = null): Puesto
    {
        $codigo = mb_strtoupper(trim($codigo));
        $tipo = trim((string) $tipo);
        $zona = trim((string) $zona);

        if ($codigo === '') {
            throw ValidationException::withMessages([
                'codigo' => 'Hace falta el código del puesto, como «A-1» o «S2-14».',
            ]);
        }

        if ($tipo !== '' && ! in_array($tipo, [DatosVehiculo::CARRO, DatosVehiculo::MOTO], true)) {
            throw ValidationException::withMessages([
                'tipo' => 'El tipo del puesto es carro, moto o cualquiera.',
            ]);
        }

        return Puesto::updateOrCreate(
            ['codigo' => $codigo],
            [
                'tipo' => $tipo === '' ? null : $tipo,
                'zona' => $zona === '' ? null : mb_substr($zona, 0, 60),
                'orden' => $orden ?? ((int) Puesto::max('orden') + 1),
            ],
        );
    }

    public function activar(Puesto $puesto, bool $activo): void
    {
        $puesto->update(['activo' => $activo]);
    }

    public function eliminar(Puesto $puesto): void
    {
        $puesto->delete();
    }
}
