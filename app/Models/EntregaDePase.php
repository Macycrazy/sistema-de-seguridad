<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un pase entregado a alguien: mientras no tenga «devuelto_en», está en la calle.
 *
 * Es el préstamo y no el pase, igual que una estadía es la visita de un vehículo y no el vehículo.
 */
class EntregaDePase extends Model
{
    protected $table = 'entregas_de_pase';

    protected $fillable = [
        'pase_id',
        'persona_id',
        'entregado_en',
        'devuelto_en',
        'usuario_id',
        'devuelto_usuario_id',
    ];

    protected function casts(): array
    {
        return [
            'entregado_en' => 'datetime',
            'devuelto_en' => 'datetime',
        ];
    }

    /** Los que siguen sin devolver. */
    public function scopeAbiertas(Builder $consulta): Builder
    {
        return $consulta->whereNull('devuelto_en');
    }

    public function pase(): BelongsTo
    {
        return $this->belongsTo(Pase::class);
    }

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }

    /** Quién lo entregó. */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /** Quién lo recibió de vuelta. */
    public function devueltoUsuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'devuelto_usuario_id');
    }

    public function estaFuera(): bool
    {
        return $this->devuelto_en === null;
    }
}
