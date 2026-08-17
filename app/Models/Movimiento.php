<?php

namespace App\Models;

use App\Services\DatosVehiculo;
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

    /**
     * Cómo se dice una hora en la pantalla de la puerta: «8:12am», «1:45pm».
     *
     * Es UNA SOLA definición a propósito. La hora aparece en la etiqueta de quien está dentro, en
     * los últimos movimientos, en los dos avisos de espera y en la confirmación de cada marcaje;
     * escrita a mano en cada sitio, acabaría diciéndose de tres maneras distintas en la misma
     * pantalla.
     *
     * Con am/pm y no de 0 a 23: es como se dice la hora aquí. «Entró a la una y cuarto», no «a
     * las trece quince».
     */
    public const FORMATO_HORA = 'g:ia';

    protected $table = 'movimientos';

    /** Sin created_at/updated_at: la hora del asiento es «ocurrio_en». */
    public $timestamps = false;

    protected $fillable = [
        'persona_id',
        'tipo',
        'ocurrio_en',
        'usuario_id',
        'motivo',
        // Copia congelada del piso al que fue ese día. Ver docs/esquema.md.
        'piso',
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
    public function vehiculo(): DatosVehiculo
    {
        return DatosVehiculo::desdeModelo($this);
    }

    public function tieneVehiculo(): bool
    {
        return ! $this->vehiculo()->vacio();
    }
}
