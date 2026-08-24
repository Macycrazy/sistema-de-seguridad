<?php

namespace App\Services\Carnets;

use App\Models\Persona;
use App\Services\Registro\Ente;
use Illuminate\Support\Collection;

/**
 * Compara el personal del carnets con el que tiene el sistema de seguridad.
 *
 * Son dos listas que tienen que decir lo mismo y se llevan por separado, así que se separan solas:
 * entra alguien nuevo y lo dan de alta en carnets, pero aquí nadie lo carga —y ese día se planta
 * en la puerta y no aparece—. O al revés: alguien se va, lo quitan allá, y aquí sigue activo.
 *
 * OJO CON LOS TRES ENTES, que es lo que hace esto menos obvio de lo que parece. En el edificio hay
 * CIIP, Marca País y VENAPP, y el sistema de carnets es SOLO del CIIP. Así que:
 *
 *   · «no está en carnets» solo significa algo para el personal del CIIP. De Marca País y VENAPP
 *     no está nadie allá, y por diseño: contarlos como sobrantes sería llenar la pantalla de
 *     falsos avisos y enseñar a no mirarla.
 *   · quien no tiene ente asignado —hoy, la mayoría— no se puede juzgar: podría ser del CIIP y
 *     faltarle el carnet, o de otro ente y estar bien. Se listan aparte, sin acusarlos de nada.
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
     *     desactivados: Collection<int, Persona>,
     *     inactivosEnCarnets: Collection<int, array{persona:Persona, estatus:string}>,
     *     sinEnte: Collection<int, Persona>,
     *     otrosEntes: int,
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

        // El padrón ENTERO, con su estatus. No solo los activos: hace falta distinguir a quien
        // está de baja allá —de ese se sabe qué pasó y se puede desactivar aquí con confianza— de
        // quien no aparece en absoluto, que puede ser cualquier otra cosa.
        $padronEntero = collect($padron->personal())
            ->map(fn ($ficha) => [
                'cedula' => Persona::normalizarCedula((string) ($ficha['cedula'] ?? '')),
                'nombre' => trim((string) ($ficha['nombre_completo'] ?? '')),
                'gerencia' => $ficha['gerencia'] ?? null,
                'estatus' => trim((string) ($ficha['estatus'] ?? '')),
            ])
            ->filter(fn ($ficha) => $ficha['cedula'] !== '')
            ->keyBy('cedula');

        $enCarnets = $padronEntero->filter(fn ($ficha) => $this->esActivo($ficha['estatus']));

        // Los que allá constan pero no como activos: baja, suspendido, lo que diga su estatus.
        $inactivosAlla = $padronEntero->reject(fn ($ficha) => $this->esActivo($ficha['estatus']));

        if ($padronEntero->isEmpty()) {
            // Ni una ficha: el carnets no respondió o no tiene a nadie activo. En cualquiera de los
            // dos casos no se puede afirmar que aquí sobre nadie.
            return $this->vacio(false);
        }

        // Todos, activos y desactivados: quien está aquí desactivado NO falta —su ficha existe con
        // su histórico—, y decir que falta llevaría a crearla otra vez encima y pisar lo que tenga.
        $todos = Persona::query()
            ->where('tipo', Persona::TRABAJADOR)
            ->orderBy('nombre')
            ->get()
            ->keyBy(fn (Persona $persona) => (string) $persona->cedula);

        $aqui = $todos->filter(fn (Persona $persona) => (bool) $persona->activo);

        // Solo del CIIP se puede decir «no está en carnets»: el carnets es suyo. De los otros dos
        // entes no está nadie allá, y eso es lo normal, no un problema.
        $delCiip = $aqui->filter(fn (Persona $p) => $p->ente === Ente::Ciip->value);
        $deOtroEnte = $aqui->filter(fn (Persona $p) => $p->ente !== null && $p->ente !== Ente::Ciip->value);
        $sinEnte = $aqui->filter(fn (Persona $p) => $p->ente === null || $p->ente === '');

        return [
            'disponible' => true,

            // Activos en carnets y aquí NO EXISTEN: hay que darlos de alta.
            'faltan' => $enCarnets
                ->reject(fn ($ficha, $cedula) => $todos->has((string) $cedula))
                ->values(),

            // Existen aquí pero desactivados, y en carnets siguen activos: tampoco pueden marcar,
            // pero se reactivan —no se crean otra vez, que pisaría su ficha—.
            'desactivados' => $todos
                ->filter(fn (Persona $persona, $cedula) => ! $persona->activo && $enCarnets->has((string) $cedula))
                ->values(),

            // Activos aquí y en carnets constan como NO activos: ahí no hay duda de qué pasó, y
            // el estado se puede igualar. Vale para cualquier ente: si está en el carnets, es del
            // CIIP —el carnets es suyo—.
            'inactivosEnCarnets' => $aqui
                ->filter(fn (Persona $persona, $cedula) => $inactivosAlla->has((string) $cedula))
                ->map(fn (Persona $persona) => [
                    'persona' => $persona,
                    'estatus' => $inactivosAlla[(string) $persona->cedula]['estatus'] ?: 'no activo',
                ])
                ->values(),

            // Del CIIP, activos aquí y que NO aparecen en carnets: ni activos ni inactivos, no
            // están. Eso puede ser una baja vieja o un dato mal cargado, así que solo se dice.
            'sobran' => $delCiip
                ->reject(fn (Persona $persona, $cedula) => $padronEntero->has((string) $cedula))
                ->values(),

            // Sin ente asignado: no se puede juzgar si les falta el carnet o es que no son del
            // CIIP. Se dicen para que alguien les ponga el ente, no para acusarlos de nada.
            'sinEnte' => $sinEnte
                ->reject(fn (Persona $persona, $cedula) => $padronEntero->has((string) $cedula))
                ->values(),

            'otrosEntes' => $deOtroEnte->count(),
            'coinciden' => $aqui->filter(fn ($p, $cedula) => $enCarnets->has((string) $cedula))->count(),
            'enCarnets' => $enCarnets->count(),
            'aqui' => $aqui->count(),
        ];
    }

    /**
     * Si ese estatus del carnets cuenta como estar activo.
     *
     * El carnets lo dice con una palabra —«Activo», y las demás son bajas de un tipo u otro—, así
     * que se compara con esa y no se intenta adivinar el resto: si mañana añaden «Reposo», caerá
     * del lado de «no activo», que es lo prudente.
     */
    private function esActivo(?string $estatus): bool
    {
        return mb_strtolower(trim((string) $estatus)) === 'activo';
    }

    /** @return array<string, mixed> */
    private function vacio(bool $disponible): array
    {
        return [
            'disponible' => $disponible,
            'faltan' => collect(),
            'sobran' => collect(),
            'sinEnte' => collect(),
            'desactivados' => collect(),
            'inactivosEnCarnets' => collect(),
            'otrosEntes' => 0,
            'coinciden' => 0,
            'enCarnets' => 0,
            'aqui' => Persona::query()->where('tipo', Persona::TRABAJADOR)->where('activo', true)->count(),
        ];
    }
}
