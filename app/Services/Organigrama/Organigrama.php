<?php

namespace App\Services\Organigrama;

use App\Models\Departamento;
use App\Models\Persona;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * El organigrama: alta y arreglo de las unidades, y el árbol para pintarlas.
 *
 * No decide nada la pantalla; todo pasa por aquí, que valida en el servidor: un nombre no puede
 * ir vacío, una unidad no puede colgar de sí misma ni de una de sus propias hijas (eso rompería
 * el árbol en un bucle), y una madre con hijas no se borra sin resolver antes las hijas.
 *
 * El texto «dependencia» de personas se deja intacto: enlazar o mover unidades no reescribe las
 * fichas. La FK «departamento_id» es la que se mueve.
 */
class Organigrama
{
    /** Los entes válidos, con su etiqueta. Iguales a los de GestionDeTrabajadores. */
    public const ENTES = [
        Persona::ENTE_CIIP => 'CIIP',
        Persona::ENTE_MARCA_PAIS => 'Marca País',
        Persona::ENTE_VENAPP => 'VENAPP',
    ];

    /**
     * Todas las unidades en orden de árbol, cada una con su profundidad y su número de personas.
     *
     * Se recorre en profundidad desde las raíces: así la lista sale sangrada y una hija siempre
     * aparece bajo su madre. La profundidad va en el atributo dinámico «_profundidad».
     *
     * @return Collection<int, Departamento>
     */
    public function enOrden(): Collection
    {
        $todas = Departamento::query()
            ->withCount('personas')
            ->orderBy('nivel')
            ->orderBy('nombre')
            ->get();

        // Agrupadas por su madre (la raíz cuelga de 0), para armar el árbol sin volver a la base.
        $porMadre = $todas->groupBy(fn (Departamento $d) => $d->parent_id ?? 0);
        $orden = collect();

        $bajar = function ($madreId, int $prof) use (&$bajar, $porMadre, $orden) {
            foreach ($porMadre->get($madreId, collect()) as $dep) {
                $dep->_profundidad = $prof;
                $orden->push($dep);
                $bajar($dep->id, $prof + 1);
            }
        };

        $bajar(0, 0);

        return $orden;
    }

    /** Para elegir madre en la pantalla: id => nombre, sin la propia unidad ni sus descendientes. */
    public function posiblesMadres(?Departamento $excluir = null): array
    {
        $prohibidas = $excluir ? $this->ramaDe($excluir) : collect();

        return $this->enOrden()
            ->reject(fn (Departamento $d) => $prohibidas->contains($d->id))
            ->mapWithKeys(fn (Departamento $d) => [$d->id => str_repeat('· ', $d->_profundidad ?? 0).$d->nombre])
            ->all();
    }

    /**
     * Da de alta una unidad.
     *
     * @throws ValidationException
     */
    public function crear(string $nombre, ?string $ente = null, ?int $parentId = null, ?string $codigo = null): Departamento
    {
        $nombre = $this->exigirNombre($nombre);
        $madre = $this->madreValida($parentId, null);

        return Departamento::create([
            'nombre' => $nombre,
            'codigo' => $this->recorta($codigo),
            'ente' => $this->enteValido($ente),
            'nivel' => $madre ? min($madre->nivel + 1, 5) : $this->nivelDe($nombre),
            'parent_id' => $madre?->id,
            'activo' => true,
        ]);
    }

    /**
     * Renombra y reasigna ente/código de una unidad. La madre se cambia con mover().
     *
     * @throws ValidationException
     */
    public function editar(Departamento $dep, string $nombre, ?string $ente = null, ?string $codigo = null): Departamento
    {
        $dep->update([
            'nombre' => $this->exigirNombre($nombre),
            'ente' => $this->enteValido($ente),
            'codigo' => $this->recorta($codigo),
        ]);

        return $dep;
    }

    /**
     * Cuelga una unidad de otra madre (o de la raíz con null). No puede colgar de sí misma ni de
     * una de sus propias hijas: eso haría un bucle.
     *
     * @throws ValidationException
     */
    public function mover(Departamento $dep, ?int $parentId): Departamento
    {
        $madre = $this->madreValida($parentId, $dep);

        $dep->update([
            'parent_id' => $madre?->id,
            'nivel' => $madre ? min($madre->nivel + 1, 5) : $this->nivelDe($dep->nombre),
        ]);

        return $dep;
    }

    /**
     * La unidad que corresponde a un texto de «dependencia», creándola si no existe.
     *
     * Es el puente entre el texto de las fichas y el organigrama: al dar de alta o importar un
     * trabajador se le enlaza su unidad sin que nadie tenga que crearla antes. Se busca sin
     * distinguir mayúsculas para no duplicar «Gestión Humana» y «GESTIÓN HUMANA».
     */
    public function paraTexto(string $nombre, ?string $ente = null): ?Departamento
    {
        $nombre = trim($nombre);

        if ($nombre === '') {
            return null;
        }

        /*
         * La comparación se hace en PHP, con mb_strtolower, y no con el lower() del SQL.
         *
         * El motivo es una diferencia entre bases que muerde justo aquí: el lower() de SQLite solo
         * baja letras ASCII, así que «GESTIÓN HUMANA» no casaba con «Gestión Humana» —la Ó se
         * quedaba en mayúscula— y esto creaba una unidad duplicada. El de PostgreSQL sí las baja,
         * de modo que el sistema hacía una cosa y las pruebas otra.
         *
         * Se pueden traer todas sin miedo: un organigrama son decenas de unidades, no miles.
         */
        $existente = Departamento::all()->first(
            fn (Departamento $unidad) => mb_strtolower($unidad->nombre) === mb_strtolower($nombre)
        );

        return $existente ?? $this->crear($nombre, $ente);
    }

    public function activar(Departamento $dep, bool $activo): Departamento
    {
        $dep->update(['activo' => $activo]);

        return $dep;
    }

    /**
     * Quita una unidad. No se borra una que tenga hijas: primero hay que moverlas o quitarlas, para
     * no dejarlas sueltas sin avisar. La gente enlazada no se pierde: vuelve a mostrarse por su
     * texto (la FK cae a null sola).
     *
     * @throws ValidationException
     */
    public function eliminar(Departamento $dep): void
    {
        if ($dep->hijas()->exists()) {
            throw ValidationException::withMessages([
                'general' => 'Esa unidad tiene subunidades. Muévelas o quítalas antes.',
            ]);
        }

        $dep->delete();
    }

    private function exigirNombre(string $nombre): string
    {
        $nombre = trim($nombre);

        if ($nombre === '') {
            throw ValidationException::withMessages(['nombre' => 'Hace falta el nombre de la unidad.']);
        }

        return mb_substr($nombre, 0, 150);
    }

    private function enteValido(?string $ente): ?string
    {
        $ente = $ente ? trim($ente) : null;

        return array_key_exists((string) $ente, self::ENTES) ? $ente : null;
    }

    private function recorta(?string $codigo): ?string
    {
        $codigo = trim((string) $codigo);

        return $codigo === '' ? null : mb_strtoupper(mb_substr($codigo, 0, 20));
    }

    /**
     * Resuelve y valida la madre elegida.
     *
     * @throws ValidationException
     */
    private function madreValida(?int $parentId, ?Departamento $mueveA): ?Departamento
    {
        if ($parentId === null) {
            return null;
        }

        $madre = Departamento::find($parentId);

        if (! $madre) {
            throw ValidationException::withMessages(['parent_id' => 'Esa unidad madre no existe.']);
        }

        // Al mover: la madre no puede ser la propia unidad ni una de sus descendientes.
        if ($mueveA && $this->ramaDe($mueveA)->contains($madre->id)) {
            throw ValidationException::withMessages([
                'parent_id' => 'Una unidad no puede colgar de sí misma ni de una de sus subunidades.',
            ]);
        }

        return $madre;
    }

    /**
     * Los ids de una unidad y de todo lo que cuelga de ella. Para no dejar que se cuelgue de su
     * propia rama.
     *
     * @return Collection<int, int>
     */
    private function ramaDe(Departamento $dep): Collection
    {
        $ids = collect([$dep->id]);
        $frente = collect([$dep->id]);

        // Se baja nivel a nivel en vez de recursión por relación: una sola consulta por nivel.
        while ($frente->isNotEmpty()) {
            $frente = Departamento::whereIn('parent_id', $frente)->pluck('id');
            $ids = $ids->merge($frente);
        }

        return $ids->unique()->values();
    }

    /** El nivel según cómo el CIIP nombra sus unidades, para una unidad de raíz. */
    private function nivelDe(string $nombre): int
    {
        $n = mb_strtoupper($nombre);

        return match (true) {
            str_starts_with($n, 'PRESIDENCIA') => 0,
            str_contains($n, 'GERENCIA GENERAL') => 1,
            str_starts_with($n, 'GERENCIA') => 2,
            str_starts_with($n, 'COORDINACIÓN') || str_starts_with($n, 'COORDINACION') => 3,
            default => 2,
        };
    }
}
