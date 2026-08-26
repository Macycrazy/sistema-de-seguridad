<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un aviso que alguien miró y decidió que no hace falta por ahora.
 *
 * Ver la migración 2026_08_26_120100 para por qué se silencia HASTA una hora y no para siempre.
 */
class AlertaSilenciada extends Model
{
    protected $table = 'alertas_silenciadas';

    protected $fillable = [
        'tipo',
        'persona_id',
        'hasta',
        'motivo',
        'usuario_id',
    ];

    protected function casts(): array
    {
        return ['hasta' => 'datetime'];
    }

    /** Las que siguen valiendo ahora. Las vencidas se quedan como histórico de quién silenció qué. */
    public function scopeVigentes(Builder $consulta): Builder
    {
        return $consulta->where('hasta', '>', now());
    }

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /** Si ese aviso, para esa persona, está silenciado ahora mismo. */
    public static function estaSilenciada(string $tipo, ?string $personaId): bool
    {
        if ($personaId === null) {
            return false;
        }

        return static::query()->vigentes()->where('tipo', $tipo)->where('persona_id', $personaId)->exists();
    }

    /** Silencia un aviso hasta la hora que se diga. */
    public static function silenciar(string $tipo, string $personaId, CarbonInterface $hasta, ?string $motivo = null): self
    {
        return static::create([
            'tipo' => $tipo,
            'persona_id' => $personaId,
            'hasta' => $hasta,
            'motivo' => $motivo,
            'usuario_id' => auth()->id(),
        ]);
    }
}
