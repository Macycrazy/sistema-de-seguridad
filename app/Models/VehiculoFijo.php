<?php

namespace App\Models;

use App\Services\DatosVehiculo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un vehículo fijo del estacionamiento: de la empresa, o uno que ya estaba y se queda. Ocupa un
 * puesto sin pasar por el marcaje de una persona. Ver la migración y App\Services\Estacionamiento.
 */
class VehiculoFijo extends Model
{
    protected $table = 'vehiculos_fijos';

    protected $fillable = [
        'puesto_id',
        'placa',
        'tipo_vehiculo',
        'marca',
        'color',
        'nota',
        'entro_en',
        'salio_en',
        'usuario_id',
    ];

    protected function casts(): array
    {
        return [
            'entro_en' => 'datetime',
            'salio_en' => 'datetime',
        ];
    }

    /** Los que siguen dentro: aún no se les ha marcado la salida. */
    public function scopeAbiertos(Builder $consulta): Builder
    {
        return $consulta->whereNull('salio_en');
    }

    public function puesto(): BelongsTo
    {
        return $this->belongsTo(Puesto::class);
    }

    /** «Carro» o «Moto», para la pantalla. */
    public function etiquetaTipo(): string
    {
        return $this->tipo_vehiculo === DatosVehiculo::MOTO ? 'Moto' : 'Carro';
    }
}
