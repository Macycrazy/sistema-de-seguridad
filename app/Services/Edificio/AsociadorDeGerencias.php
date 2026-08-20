<?php

namespace App\Services\Edificio;

use App\Models\Oficina;
use App\Models\Persona;

/**
 * Precarga la asociación piso → gerencia mirando al personal ya cargado.
 *
 * En vez de asociar cada oficina a mano, se aprovecha lo que el Excel ya trajo: si en el piso «4-1»
 * trabaja gente de «Gestión Humana», entonces ese piso es de esa gerencia. Para cada código de piso
 * se toma la gerencia con más trabajadores ahí; cuando conviven varias, se avisa.
 *
 * No pisa una oficina que ya tenga gerencia puesta a mano: eso lo decidió el administrador y se
 * respeta.
 */
class AsociadorDeGerencias
{
    /**
     * Qué gerencia ocupa cada piso, según el personal. Para cada código: la gerencia dominante, su
     * conteo, el detalle por gerencia y si hay conflicto (varias gerencias en el mismo piso).
     *
     * @return array<string, array{gerencia:string, total:int, detalle:array<string,int>, conflicto:bool}>
     */
    public function plan(): array
    {
        $conteo = [];

        Persona::query()
            ->where('tipo', Persona::TRABAJADOR)
            ->whereNotNull('piso')->where('piso', '!=', '')
            ->whereNotNull('dependencia')->where('dependencia', '!=', '')
            ->get(['piso', 'dependencia'])
            ->each(function (Persona $p) use (&$conteo) {
                $piso = mb_strtoupper(trim((string) $p->piso));
                $gerencia = mb_strtoupper(trim((string) $p->dependencia));
                $conteo[$piso][$gerencia] = ($conteo[$piso][$gerencia] ?? 0) + 1;
            });

        $plan = [];

        foreach ($conteo as $piso => $gerencias) {
            arsort($gerencias);
            $dominante = (string) array_key_first($gerencias);

            $plan[$piso] = [
                'gerencia' => $dominante,
                'total' => $gerencias[$dominante],
                'detalle' => $gerencias,
                'conflicto' => count($gerencias) > 1,
            ];
        }

        ksort($plan);

        return $plan;
    }

    /**
     * Escribe el plan en el catálogo de oficinas. Las que ya tienen gerencia a mano se respetan.
     *
     * @return array{creadas:int, actualizadas:int, saltadas:int}
     */
    public function aplicar(bool $simular = false): array
    {
        $creadas = 0;
        $actualizadas = 0;
        $saltadas = 0;

        foreach ($this->plan() as $piso => $info) {
            $oficina = Oficina::firstOrNew(['codigo' => $piso]);

            // Respeta lo puesto a mano: si ya tiene gerencia, no se toca.
            if ($oficina->exists && ! blank($oficina->gerencia)) {
                $saltadas++;

                continue;
            }

            $nueva = ! $oficina->exists;
            $oficina->gerencia = $info['gerencia'];

            if ($nueva) {
                $oficina->orden = (int) Oficina::max('orden') + 1;
            }

            if (! $simular) {
                $oficina->save();
            }

            $nueva ? $creadas++ : $actualizadas++;
        }

        return compact('creadas', 'actualizadas', 'saltadas');
    }
}
