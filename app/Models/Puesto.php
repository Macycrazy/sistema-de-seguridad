<?php

namespace App\Models;

use App\Services\DatosVehiculo;
use Illuminate\Database\Eloquent\Model;

/**
 * Una plaza numerada del estacionamiento: dónde se para un vehículo.
 *
 * El catálogo lo administra el edificio. Un vehículo que entra se asigna a un puesto (ver
 * «movimientos.puesto_id»), y de ahí sale qué plazas están tomadas y cuáles libres.
 */
class Puesto extends Model
{
    protected $table = 'puestos';

    protected $fillable = [
        'codigo',
        'tipo',
        'zona',
        'activo',
        'orden',
    ];

    /** Un puesto nace habilitado, igual que en la base. Así el modelo recién creado ya lo refleja. */
    protected $attributes = [
        'activo' => true,
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'orden' => 'integer',
        ];
    }

    /** Si esta plaza admite un vehículo de ese tipo. Sin tipo propio (nulo), admite cualquiera. */
    public function admite(?string $tipoVehiculo): bool
    {
        if (blank($this->tipo)) {
            return true;
        }

        return $this->tipo === DatosVehiculo::normalizarTipo($tipoVehiculo);
    }

    /** «Carro», «Moto» o «Cualquiera», para la pantalla. */
    public function etiquetaTipo(): string
    {
        return match ($this->tipo) {
            DatosVehiculo::CARRO => 'Carro',
            DatosVehiculo::MOTO => 'Moto',
            default => 'Cualquiera',
        };
    }
}
