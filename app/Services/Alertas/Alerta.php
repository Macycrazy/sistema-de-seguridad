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

    public const ESTACIONAMIENTO = 'estacionamiento';

    public const PERMANENCIA = 'permanencia';

    /** Un vehículo de la empresa que salió y no ha vuelto. */
    public const FLOTA_FUERA = 'flota-fuera';

    /** Un pase de visitante que se entregó y no ha vuelto. */
    public const PASE_FUERA = 'pase-fuera';

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
        /**
         * La cédula de esa persona.
         *
         * Va con el nombre porque el nombre no identifica: hay quien se llama parecido y quien se
         * llama igual, y de estas alertas cuelgan acciones —cerrarle la salida a alguien— que no
         * se pueden hacer sobre la persona equivocada.
         */
        public readonly ?string $personaCedula = null,
        public readonly ?CarbonImmutable $desde = null,
    ) {}

    public function esUrgente(): bool
    {
        return $this->severidad === self::URGENTE;
    }
}
