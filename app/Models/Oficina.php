<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Una oficina del edificio: un sitio a donde puede ir un invitado.
 *
 * El catálogo lo gestiona el administrador desde la pantalla del edificio. La pantalla de marcar
 * lo lee a través de CatalogoDelEdificio, no de este modelo directamente.
 */
class Oficina extends Model
{
    protected $table = 'oficinas';

    protected $fillable = [
        'codigo',
        'nombre',
        'orden',
    ];
}
