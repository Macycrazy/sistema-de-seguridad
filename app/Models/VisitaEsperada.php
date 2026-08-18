<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una visita agendada: alguien que se espera, con su día y a quién viene a ver.
 *
 * Su estado vive en una columna con tres valores; «vencida» no es uno de ellos, se deduce (sigue
 * esperada y su día ya pasó). Ver la migración para el porqué de guardar cédula y nombre sueltos.
 */
class VisitaEsperada extends Model
{
    public const ESPERADA = 'esperada';

    public const LLEGO = 'llego';

    public const CANCELADA = 'cancelada';

    protected $table = 'visitas_esperadas';

    protected $fillable = [
        'cedula',
        'nombre',
        'a_quien_visita',
        'motivo',
        'fecha_esperada',
        'estado',
        'notas',
        'registrada_por',
    ];

    protected function casts(): array
    {
        return [
            'fecha_esperada' => 'date',
        ];
    }

    public function registradaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrada_por');
    }

    public function estaEsperada(): bool
    {
        return $this->estado === self::ESPERADA;
    }

    /** Sigue esperada pero su día ya pasó: nadie la marcó como llegada ni la canceló. */
    public function esVencida(): bool
    {
        return $this->estaEsperada() && $this->fecha_esperada->lt(CarbonImmutable::today());
    }
}
