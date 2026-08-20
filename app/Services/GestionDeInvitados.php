<?php

namespace App\Services;

use App\Models\Persona;
use Illuminate\Validation\ValidationException;

/**
 * Corregir los datos de un invitado ya registrado, desde la administración del personal.
 *
 * El invitado nace en la puerta (App\Services\Marcaje::registrarInvitado) con lo mínimo. Esto es
 * solo para arreglar después un dato mal tecleado —el nombre, el motivo, el piso al que iba—, no
 * para crearlo ni para marcarlo.
 *
 * Dos cosas NO se tocan por aquí, a propósito:
 *   · la cédula, que es su identidad —si está mal, es otra persona—;
 *   · el tipo: un invitado no se vuelve trabajador con un botón de edición. Esa conversión, si de
 *     verdad hace falta, se decide aparte.
 */
class GestionDeInvitados
{
    /**
     * @throws ValidationException
     */
    public function editar(Persona $invitado, string $nombre, string $nacionalidad, string $motivo, ?string $piso): Persona
    {
        // Guardia dura: aunque la pantalla solo ofrezca editar invitados, la petición puede llegar
        // sin pasar por ella. Un trabajador no se edita como si fuera visita.
        if (! $invitado->esInvitado()) {
            throw ValidationException::withMessages([
                'nombre' => 'Esa persona no es un invitado.',
            ]);
        }

        $nombre = trim($nombre);
        $motivo = trim($motivo);

        if ($nombre === '') {
            throw ValidationException::withMessages([
                'nombre' => 'Hace falta el nombre del invitado.',
            ]);
        }

        if ($motivo === '') {
            throw ValidationException::withMessages([
                'motivo' => 'Hace falta el motivo de la visita.',
            ]);
        }

        $invitado->update([
            'nombre' => mb_strtoupper($nombre),
            'nacionalidad' => Persona::normalizarNacionalidad($nacionalidad),
            'motivo' => $motivo,
            'piso' => Persona::normalizarPiso($piso),
        ]);

        return $invitado;
    }
}
