<?php

namespace App\Models;

use App\Services\DatosVehiculo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un vehículo de una persona. Puede tener más de uno: hay quien viene en carro unos días y en
 * moto otros, y en la puerta se marca cuál trae ese día.
 *
 * No confundir con App\Services\DatosVehiculo, que son los cinco datos sueltos, limpios y
 * validados. Esto es el vehículo guardado, con su dueño.
 *
 * La placa manda: es lo único que identifica al vehículo de verdad, así que aquí no puede ir
 * nula y se guarda normalizada. Marca, modelo y color son para reconocerlo de un vistazo.
 */
class Vehiculo extends Model
{
    use HasFactory;

    protected $table = 'vehiculos';

    protected $fillable = [
        'persona_id',
        'tipo',
        'marca',
        'modelo',
        'color',
        'placa',
    ];

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }

    /** Los cinco datos, ya limpios, para congelarlos en un asiento o para mostrarlos. */
    public function datos(): DatosVehiculo
    {
        return DatosVehiculo::desdeModelo($this);
    }

    public function esMoto(): bool
    {
        return $this->tipo === DatosVehiculo::MOTO;
    }

    /** Cómo se lee de un vistazo: «Carro · Toyota Corolla · Gris · AB123CD». */
    public function descripcion(): string
    {
        return $this->datos()->descripcion();
    }
}
