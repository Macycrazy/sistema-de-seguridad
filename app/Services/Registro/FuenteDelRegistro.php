<?php

namespace App\Services\Registro;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Todo lo que la pantalla del registro necesita saber pasa por aquí.
 *
 * Mientras el esquema de tablas no esté acordado entre las tres partes, la única
 * implementación es RegistroInventado. Cuando lo esté, se escribe una segunda
 * implementación contra la base de datos y se cambia una línea en AppServiceProvider:
 * ni el componente Livewire ni las vistas se enteran del cambio.
 *
 * Las personas se identifican por su `id`, no por la cédula: en el listado de personal
 * real hay gente sin documento registrado y gente con pasaporte en vez de cédula.
 */
interface FuenteDelRegistro
{
    /**
     * Movimientos de un día, más reciente primero.
     *
     * @return Collection<int, Movimiento>
     */
    public function movimientosDelDia(
        CarbonImmutable $fecha,
        ?TipoDePersona $tipo = null,
        ?Ente $ente = null,
    ): Collection;

    /**
     * Cuántas personas están dentro al cierre de ese día.
     *
     * Para hoy es «cuántas están dentro en este momento»; para una fecha pasada es
     * cuántas entraron ese día y no registraron salida.
     */
    public function dentroEn(CarbonImmutable $fecha): int;

    /**
     * Busca por documento o por nombre. Se consulta de a poco y se devuelve lo mínimo:
     * nunca la lista completa del personal.
     *
     * @return Collection<int, Persona>
     */
    public function buscarPersonas(string $texto, int $limite = 8): Collection;

    /**
     * Histórico completo de una persona, más reciente primero.
     *
     * @return Collection<int, Movimiento>
     */
    public function historicoDe(string $personaId): Collection;

    public function persona(string $personaId): ?Persona;
}
