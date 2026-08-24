<?php

namespace App\Services\Pases;

use App\Models\Pase;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * El catálogo de pases: darlos de alta, renombrarlos, deshabilitarlos y quitarlos.
 *
 * La pantalla no decide nada, pregunta aquí. Mismo oficio que CatalogoDePuestos con las plazas del
 * estacionamiento, y por las mismas razones: se carga una vez y de ahí vive todo lo demás.
 */
class CatalogoDePases
{
    /** @return Collection<int, Pase> */
    public function todos(): Collection
    {
        return Pase::query()->orderBy('orden')->orderBy('codigo')->get();
    }

    /**
     * Da de alta un pase o corrige el que ya existe con ese código.
     *
     * @throws ValidationException
     */
    public function guardar(?string $codigo, ?string $nota = null, ?int $orden = null): Pase
    {
        $codigo = Pase::normalizarCodigo($codigo);

        if ($codigo === null) {
            throw ValidationException::withMessages([
                'codigoPase' => 'Hace falta el código del pase: lo que va escrito en él.',
            ]);
        }

        return Pase::updateOrCreate(
            ['codigo' => $codigo],
            [
                'nota' => ($nota = trim((string) $nota)) === '' ? null : mb_substr($nota, 0, 120),
                'orden' => $orden ?? 0,
            ],
        );
    }

    /**
     * Da de alta varios de golpe: «V-01» a «V-30» sin teclearlos uno a uno.
     *
     * Cargar treinta pases a mano es lo que hace que no se carguen. Se salta los que ya existen en
     * vez de fallar, para poder ampliar la tanda más adelante sin pensar.
     *
     * @return int cuántos se crearon
     *
     * @throws ValidationException
     */
    public function crearTanda(string $prefijo, int $desde, int $hasta, int $digitos = 2): int
    {
        if ($desde < 1 || $hasta < $desde || $hasta - $desde > 499) {
            throw ValidationException::withMessages([
                'tanda' => 'El rango tiene que ir de menor a mayor y no pasar de 500 pases.',
            ]);
        }

        $prefijo = mb_strtoupper(trim($prefijo));
        $creados = 0;

        for ($numero = $desde; $numero <= $hasta; $numero++) {
            $codigo = Pase::normalizarCodigo($prefijo.str_pad((string) $numero, $digitos, '0', STR_PAD_LEFT));

            if ($codigo === null || Pase::where('codigo', $codigo)->exists()) {
                continue;
            }

            Pase::create(['codigo' => $codigo, 'orden' => $numero]);
            $creados++;
        }

        return $creados;
    }

    /** Un pase que se perdió o se estropeó: deja de ofrecerse, pero su histórico se queda. */
    public function habilitar(Pase $pase, bool $activo): void
    {
        $pase->update(['activo' => $activo]);
    }

    /**
     * Quita un pase del catálogo.
     *
     * No se puede si está en manos de alguien: primero se recupera. Borrarlo se llevaría por
     * delante el registro de a quién se le dio.
     *
     * @throws ValidationException
     */
    public function eliminar(Pase $pase): void
    {
        if ($pase->entregaAbierta() !== null) {
            throw ValidationException::withMessages([
                'pase' => 'Ese pase está entregado: hay que recuperarlo antes de quitarlo del catálogo.',
            ]);
        }

        $pase->delete();
    }
}
