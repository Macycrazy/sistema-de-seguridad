<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una entrada de la bitácora de auditoría. Se escribe por App\Services\Auditoria\Auditoria y se
 * lee desde la pantalla de auditoría; no se toca directamente.
 *
 * Inmutable, como el movimiento: sin timestamps de Eloquent, la hora es «ocurrio_en».
 */
class Bitacora extends Model
{
    protected $table = 'bitacora';

    public $timestamps = false;

    protected $fillable = [
        'usuario_id',
        'accion',
        'sobre',
        'detalle',
        'ip',
        'ocurrio_en',
    ];

    protected function casts(): array
    {
        return [
            'ocurrio_en' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
