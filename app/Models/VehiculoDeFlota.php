<?php

namespace App\Models;

use App\Services\DatosVehiculo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Un vehículo de la flota de la empresa: cargado una vez para elegirlo al anotarlo, sin teclear la
 * placa cada vez. Entra y sale las veces que haga falta (cada estadía va en «vehiculos_fijos»).
 */
class VehiculoDeFlota extends Model
{
    protected $table = 'vehiculos_flota';

    protected $fillable = [
        'placa',
        'tipo_vehiculo',
        'marca',
        'color',
        'nota',
        'activo',
    ];

    protected $attributes = [
        'activo' => true,
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function scopeActivos(Builder $consulta): Builder
    {
        return $consulta->where('activo', true);
    }

    public function etiquetaTipo(): string
    {
        return $this->tipo_vehiculo === DatosVehiculo::MOTO ? 'Moto' : 'Carro';
    }

    /** «AB123CD · Toyota Gris», para el desplegable. */
    public function descripcion(): string
    {
        return trim($this->placa.' · '.trim(($this->marca ?? '').' '.($this->color ?? '')));
    }
}
