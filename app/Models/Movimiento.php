<?php

namespace App\Models;

use App\Services\Vehiculo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una entrada o una salida: el asiento que deja el botón de la puerta.
 *
 * No se edita ni se borra. Un error se corrige registrando un movimiento nuevo.
 * Por eso no lleva «updated_at»: su hora es «ocurrio_en» y no cambia nunca.
 */
class Movimiento extends Model
{
    use HasFactory;

    public const ENTRADA = 'entrada';

    public const SALIDA = 'salida';

    protected $table = 'movimientos';

    /** Sin created_at/updated_at: la hora del asiento es «ocurrio_en». */
    public $timestamps = false;

    protected $fillable = [
        'persona_id',
        'tipo',
        'ocurrio_en',
        'usuario_id',
        'motivo',
        // Copia congelada del vehículo de ese día. Ver docs/esquema.md.
        'tipo_vehiculo',
        'marca',
        'modelo',
        'color',
        'placa',
    ];

    protected function casts(): array
    {
        return [
            'ocurrio_en' => 'datetime',
        ];
    }

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }

    /** Quién lo registró. Nulo mientras la parte 3 (usuarios) no esté lista. */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function esEntrada(): bool
    {
        return $this->tipo === self::ENTRADA;
    }

    /** El vehículo con el que se registró este asiento, tal y como estaba ese día. */
    public function vehiculo(): Vehiculo
    {
        return Vehiculo::desdeModelo($this);
    }

    public function tieneVehiculo(): bool
    {
        return ! $this->vehiculo()->vacio();
    }
}
