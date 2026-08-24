<?php

namespace App\Services\Carnets;

use App\Models\Persona;
use Illuminate\Support\Collection;

/**
 * Compara el personal del carnets con el que tiene el sistema de seguridad.
 *
 * Son dos listas que tienen que decir lo mismo y se llevan por separado, así que se separan solas:
 * entra alguien nuevo y lo dan de alta en carnets, pero aquí nadie lo carga —y ese día se planta
 * en la puerta y no aparece—. O al revés: alguien se va, lo quitan allá, y aquí sigue activo.
 *
 * Esto no arregla nada por su cuenta: dice qué no cuadra. Dar de alta a una persona es una
 * decisión de nómina, no algo que deba pasar solo porque dos listas difieran.
 */
final class CotejoConCarnets
{
    /**
     * Qué no cuadra entre las dos listas.
     *
     * @return array{
     *     disponible: bool,
     *     faltan: Collection<int, array{cedula:string, nombre:string, gerencia:?string}>,
     *     sobran: Collection<int, Persona>,
     *     coinciden: int,
     *     enCarnets: int,
     *     aqui: int,
     * }
     */
    public function comparar(): array
    {
        $padron = app(PadronDelCarnet::class);

        if (! $padron->configurado()) {
            return $this->vacio(false);
        }

        // Solo los activos de allá: quien está de baja en carnets no tiene por qué estar aquí.
        $enCarnets = collect($padron->personal(soloActivos: true))
            ->map(fn ($ficha) => [
                'cedula' => Persona::normalizarCedula((string) ($ficha['cedula'] ?? '')),
                'nombre' => trim((string) ($ficha['nombre_completo'] ?? '')),
                'gerencia' => $ficha['gerencia'] ?? null,
            ])
            ->filter(fn ($ficha) => $ficha['cedula'] !== '')
            ->keyBy('cedula');

        if ($enCarnets->isEmpty()) {
            // Ni una ficha: el carnets no respondió o no tiene a nadie activo. En cualquiera de los
            // dos casos no se puede afirmar que aquí sobre nadie.
            return $this->vacio(false);
        }

        $aqui = Persona::query()
            ->where('tipo', Persona::TRABAJADOR)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get()
            ->keyBy(fn (Persona $persona) => (string) $persona->cedula);

        return [
            'disponible' => true,

            // Están activos en carnets y aquí no: son los que se van a plantar en la puerta.
            'faltan' => $enCarnets
                ->reject(fn ($ficha, $cedula) => $aqui->has((string) $cedula))
                ->values(),

            // Están activos aquí y ya no en carnets: se fueron, o cambiaron de estatus allá.
            'sobran' => $aqui
                ->reject(fn (Persona $persona, $cedula) => $enCarnets->has((string) $cedula))
                ->values(),

            'coinciden' => $aqui->filter(fn ($p, $cedula) => $enCarnets->has((string) $cedula))->count(),
            'enCarnets' => $enCarnets->count(),
            'aqui' => $aqui->count(),
        ];
    }

    /** @return array{disponible:bool, faltan:Collection, sobran:Collection, coinciden:int, enCarnets:int, aqui:int} */
    private function vacio(bool $disponible): array
    {
        return [
            'disponible' => $disponible,
            'faltan' => collect(),
            'sobran' => collect(),
            'coinciden' => 0,
            'enCarnets' => 0,
            'aqui' => Persona::query()->where('tipo', Persona::TRABAJADOR)->where('activo', true)->count(),
        ];
    }
}
