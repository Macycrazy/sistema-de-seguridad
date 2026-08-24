<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * El rostro indexado de una persona: 128 números, no una foto.
 *
 * Ver la migración 2026_08_24_140000 para qué es un descriptor y por qué se guarda así.
 */
class Rostro extends Model
{
    protected $table = 'rostros';

    /** Cuántos números tiene un descriptor. Lo fija el modelo de reconocimiento, no nosotros. */
    public const LARGO = 128;

    protected $fillable = [
        'persona_id',
        'descriptor',
        'origen',
        'calculado_en',
    ];

    protected function casts(): array
    {
        return [
            'descriptor' => 'array',
            'calculado_en' => 'datetime',
        ];
    }

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }
}
