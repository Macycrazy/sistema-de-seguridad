<?php

namespace App\Services\Administracion;

use App\Models\Departamento;
use App\Models\Oficina;
use App\Models\Pase;
use App\Models\Persona;
use App\Models\Puesto;
use App\Models\Rostro;
use App\Models\User;
use App\Services\Respaldos\Respaldos;

/**
 * Cuánto tiene cargado cada módulo, para decirlo en su tarjeta.
 *
 * Un catálogo vacío y uno lleno se ven igual desde la portada, y eso engaña justo al principio:
 * alguien entra a Pases esperando algo, se encuentra la pantalla en blanco y no sabe si está rota,
 * si le falta un permiso o si simplemente nadie ha cargado nada todavía. Con el número delante, un
 * módulo sin estrenar se distingue de uno con problemas.
 *
 * Son cuentas, no listas: da igual que haya treinta o trescientos, lo que se quiere saber es si hay
 * algo. Por eso van con «count» y no trayendo filas.
 */
class Inventario
{
    /**
     * Lo que tiene cada módulo, por el nombre de su ruta.
     *
     * @return array<string, array{cuantos:int, etiqueta:string}>
     */
    public function porModulo(): array
    {
        return [
            'trabajadores' => $this->conteo(
                Persona::query()->where('tipo', Persona::TRABAJADOR)->where('activo', true)->count(),
                'trabajador activo', 'trabajadores activos',
            ),
            'organigrama' => $this->conteo(Departamento::query()->count(), 'unidad', 'unidades'),
            'usuarios' => $this->conteo(User::query()->count(), 'cuenta', 'cuentas'),
            'edificio' => $this->conteo(Oficina::query()->count(), 'oficina', 'oficinas'),
            'puestos' => $this->conteo(Puesto::query()->count(), 'plaza', 'plazas'),
            'pases' => $this->conteo(Pase::query()->count(), 'pase', 'pases'),

            // Personas con al menos una cara, no caras: alguien puede tener varias.
            'rostros' => $this->conteo(
                Rostro::query()->distinct('persona_id')->count('persona_id'),
                'persona con cara', 'personas con cara',
            ),

            'respaldos' => $this->conteo($this->cuantasCopias(), 'copia', 'copias'),
        ];
    }

    /**
     * Las copias que hay guardadas.
     *
     * Va con red: listar respaldos toca el disco, y que la carpeta no exista todavía no puede
     * dejar sin portada a quien entra a administración.
     */
    private function cuantasCopias(): int
    {
        try {
            return app(Respaldos::class)->listar()->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    /** @return array{cuantos:int, etiqueta:string} */
    private function conteo(int $cuantos, string $singular, string $plural): array
    {
        return [
            'cuantos' => $cuantos,
            'etiqueta' => $cuantos === 1 ? $singular : $plural,
        ];
    }
}
