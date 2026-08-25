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

    /**
     * De dónde salió la cara.
     *
     * La del carnet es la de referencia: hay una por persona y se sustituye al reindexar. Las de la
     * cámara se acumulan —cada una es la misma cara con otra luz, otras gafas, otro día— y son las
     * que hacen que el reconocimiento aguante el paso del tiempo.
     */
    public const DEL_CARNET = 'carnet';

    public const DE_LA_CAMARA = 'camara';

    protected $fillable = [
        'persona_id',
        'descriptor',
        'origen',
        // El hash de la foto con la que se hizo, para saber si se quedó viejo. Ver la migración
        // 2026_08_24_150000.
        'hash_foto',
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
