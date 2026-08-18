<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una unidad del organigrama: una gerencia, una coordinación, la presidencia.
 *
 * Se relaciona consigo misma: cada unidad puede tener una madre (parent) y varias hijas (hijas).
 * La raíz del árbol es la que no tiene madre. Ver la migración para el porqué del modelo aditivo.
 */
class Departamento extends Model
{
    protected $table = 'departamentos';

    protected $fillable = [
        'nombre',
        'codigo',
        'ente',
        'nivel',
        'parent_id',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'nivel' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function madre(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function hijas(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function personas(): HasMany
    {
        return $this->hasMany(Persona::class, 'departamento_id');
    }

    public function esRaiz(): bool
    {
        return $this->parent_id === null;
    }
}
