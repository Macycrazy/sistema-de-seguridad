<?php

namespace App\Services\Alertas;

use Carbon\CarbonImmutable;

/**
 * Una alerta lista para pintar: qué pasa, con qué gravedad, y sobre quién.
 *
 * Es un value object inmutable. No sabe leer de la base ni decidir si algo es alerta —de eso se
 * encarga el motor Alertas—; solo carga lo justo para mostrarse en la pantalla y contar.
 */
final class Alerta
{
    public const AFORO = 'aforo';

    public const PERMANENCIA = 'permanencia';

    /** Dos gravedades: «aviso» conviene mirarlo; «urgente» pide actuar ya. */
    public const AVISO = 'aviso';

    public const URGENTE = 'urgente';

    public function __construct(
        public readonly string $tipo,
        public readonly string $severidad,
        public readonly string $titulo,
        public readonly string $detalle,
        public readonly ?string $personaId = null,
        public readonly ?string $personaNombre = null,
        public readonly ?CarbonImmutable $desde = null,
    ) {}

    public function esUrgente(): bool
    {
        return $this->severidad === self::URGENTE;
    }
}
