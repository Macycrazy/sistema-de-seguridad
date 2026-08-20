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
        // La gerencia que ocupa este piso/oficina. Texto, igual que «dependencia» del trabajador:
        // así al asignar el piso se ofrecen los de la gerencia. Ver la migración.
        'gerencia',
        'orden',
    ];
}
