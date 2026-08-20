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
        'flota_id',
        'puesto_id',
        'placa',
        'tipo_vehiculo',
        'marca',
        'color',
        'nota',
        'conductor_id',
        'conductor_nombre',
        'entro_en',
        'salio_en',
        'salida_conductor_id',
        'salida_conductor_nombre',
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

    public function flota(): BelongsTo
    {
        return $this->belongsTo(VehiculoDeFlota::class, 'flota_id');
    }

    public function conductor(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'conductor_id');
    }

    public function salidaConductor(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'salida_conductor_id');
    }

    /** «Carro» o «Moto», para la pantalla. */
    public function etiquetaTipo(): string
    {
        return $this->tipo_vehiculo === DatosVehiculo::MOTO ? 'Moto' : 'Carro';
    }
}
