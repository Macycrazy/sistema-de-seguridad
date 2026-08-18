<?php

namespace App\Models;

use App\Auditoria\Accion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un asiento del rastro.
 *
 * No se edita ni se borra: se escribe una vez y se queda. Por eso no lleva «updated_at» —igual que
 * Movimiento— y por eso el modelo no ofrece nada para modificarlo.
 */
class Auditoria extends Model
{
    protected $table = 'auditorias';

    /** Solo «created_at». La hora que importa es «ocurrio_en». */
    public const UPDATED_AT = null;

    protected $fillable = [
        'usuario_id',
        'accion',
        'persona_id',
        'detalle',
        'ip',
        'ocurrio_en',
    ];

    protected function casts(): array
    {
        return [
            'accion' => Accion::class,
            'ocurrio_en' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }

    /**
     * Quién es el autor, en texto.
     *
     * El ingreso fallido no tiene usuario —todavía no se sabía quién era—, y ahí el nombre que se
     * intentó queda en «detalle».
     */
    public function autor(): string
    {
        return $this->usuario?->nombre ?? 'Sin identificar';
    }

    /**
     * Lo más reciente primero.
     *
     * Se desempata por id porque varios asientos pueden caer en el mismo segundo: sin esto, el
     * orden entre ellos lo decidiría la base a su antojo y la pantalla bailaría entre recargas.
     *
     * @param  Builder<Auditoria>  $query
     */
    public function scopeMasReciente(Builder $query): void
    {
        $query->orderByDesc('ocurrio_en')->orderByDesc('id');
    }
}
