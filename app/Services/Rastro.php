<?php

namespace App\Services;

use App\Auditoria\Accion;
use App\Models\Auditoria;
use App\Models\Persona;

/**
 * El único sitio por donde se escribe el rastro.
 *
 * Misma idea que Marcaje con los movimientos: si hay una sola puerta de entrada, se puede decir con
 * certeza qué queda anotado y qué no. Quien necesite anotar algo nuevo pasa por aquí y añade su
 * caso a Accion; no se escribe en la tabla desde ningún otro lado.
 *
 * Aquí no se pregunta por permisos. El rastro se deja siempre, para cualquier rol: es la regla 4
 * del README —«todo deja rastro»— y un rastro que se puede desactivar no sirve de nada.
 */
class Rastro
{
    /**
     * Cuántos segundos hacen que dos anotaciones iguales cuenten como una.
     *
     * Es para la consulta de cédula, que el campo de la puerta dispara en cada pausa del tecleo:
     * escribir «25375258» pasa por «253752», «2537525» y «25375258», y para quien mire la
     * auditoría eso fue una sola consulta. Treinta segundos cubren el tecleo y las correcciones
     * sin llegar a tapar dos consultas de verdad separadas.
     *
     * Misma idea que Marcaje::SEGUNDOS_ANTIDUPLICADO, y por la misma razón: lo que se anota de más
     * no es inocente, entierra lo que importa.
     */
    public const SEGUNDOS_DE_AGRUPACION = 30;

    /**
     * Deja constancia. Devuelve el asiento, o el que ya había si esto fue una repetición.
     *
     * El usuario y la dirección se sacan de la petición, no se piden: así ningún sitio que anote
     * puede equivocarse de autor, ni «olvidarse» de ponerlo.
     */
    public function deja(
        Accion $accion,
        ?Persona $persona = null,
        ?string $detalle = null,
        ?int $usuarioId = null,
    ): Auditoria {
        $usuarioId ??= auth()->id();

        if ($repetido = $this->recienAnotado($accion, $usuarioId, $detalle)) {
            return $repetido;
        }

        return Auditoria::create([
            'usuario_id' => $usuarioId,
            'accion' => $accion,
            'persona_id' => $persona?->id,
            'detalle' => $detalle !== null ? mb_substr($detalle, 0, 255) : null,
            'ip' => request()->ip(),
            'ocurrio_en' => now(),
        ]);
    }

    /**
     * Lo mismo, del mismo usuario, hace un instante.
     *
     * Solo se agrupan las acciones que se repiten solas —hoy, la consulta de cédula—. Las demás se
     * anotan siempre: que alguien exporte dos veces seguidas son dos exportaciones, y taparlo
     * sería mentir sobre lo que pasó.
     */
    protected function recienAnotado(Accion $accion, ?int $usuarioId, ?string $detalle): ?Auditoria
    {
        if (! $accion->seRepiteSola()) {
            return null;
        }

        return Auditoria::query()
            ->where('accion', $accion)
            ->where('usuario_id', $usuarioId)
            ->where('detalle', $detalle)
            ->where('ocurrio_en', '>=', now()->subSeconds(self::SEGUNDOS_DE_AGRUPACION))
            ->masReciente()
            ->first();
    }
}
