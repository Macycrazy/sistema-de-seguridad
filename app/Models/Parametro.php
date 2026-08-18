<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Un parámetro del sistema: una pareja clave/valor que el administrador ajusta.
 *
 * Se lee siempre a través de App\Services\ReglasDeTiempo, no de este modelo directamente, para que
 * haya un solo sitio con los valores por omisión y los límites.
 */
class Parametro extends Model
{
    protected $table = 'parametros';

    protected $fillable = [
        'clave',
        'valor',
    ];

    protected function casts(): array
    {
        return [
            'valor' => 'integer',
        ];
    }
}
