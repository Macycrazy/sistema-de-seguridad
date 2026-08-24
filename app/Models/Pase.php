<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un pase de visitante: la credencial numerada que Seguridad presta en la puerta.
 *
 * El catálogo se carga una vez, como las plazas del estacionamiento. Que un pase exista no dice
 * nada de dónde está: eso lo dicen sus entregas (App\Models\EntregaDePase).
 */
class Pase extends Model
{
    protected $table = 'pases';

    protected $fillable = [
        'codigo',
        'nota',
        'activo',
        'orden',
    ];

    /** Nace habilitado, igual que en la base. */
    protected $attributes = [
        'activo' => true,
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'orden' => 'integer',
        ];
    }

    public function scopeActivos(Builder $consulta): Builder
    {
        return $consulta->where('activo', true);
    }

    public function entregas(): HasMany
    {
        return $this->hasMany(EntregaDePase::class);
    }

    /** La entrega abierta, si está en manos de alguien ahora. */
    public function entregaAbierta(): ?EntregaDePase
    {
        return $this->entregas()->whereNull('devuelto_en')->latest('entregado_en')->first();
    }

    /** Cómo se lee de un vistazo: «V-01 · amarillos». */
    public function descripcion(): string
    {
        return trim($this->codigo.($this->nota ? ' · '.$this->nota : ''));
    }

    /** El código, limpio: sin espacios sobrantes y en mayúscula, como se escribe en el pase. */
    public static function normalizarCodigo(?string $codigo): ?string
    {
        $codigo = mb_strtoupper(trim(preg_replace('/\s+/', ' ', (string) $codigo) ?? ''));

        return $codigo === '' ? null : mb_substr($codigo, 0, 20);
    }
}
