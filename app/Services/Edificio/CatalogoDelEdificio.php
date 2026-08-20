<?php

namespace App\Services\Edificio;

use App\Models\Oficina;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * El catálogo de oficinas del edificio.
 *
 * La pantalla de marcar le pide la lista y los nombres en la misma forma en que antes los tenía
 * config/edificio.php, así que ahí solo cambió de dónde salen. Si la tabla estuviera vacía —una
 * base recién creada, antes de sembrar— cae a la config de fábrica, para no dejar la puerta sin
 * catálogo.
 *
 * El administrador la gestiona desde la pantalla del edificio, y todo pasa por aquí: validar en el
 * servidor, igual que el resto del sistema.
 */
class CatalogoDelEdificio
{
    /** Los códigos de oficina, en orden. Lo que la pantalla ofrece como botones. */
    public function oficinas(): array
    {
        $enLaBase = Oficina::query()->orderBy('orden')->orderBy('codigo')->pluck('codigo')->all();

        return $enLaBase !== [] ? $enLaBase : array_values((array) config('edificio.oficinas', []));
    }

    /** Mapa «código de oficina => nombre de respaldo», solo las que tienen uno. */
    public function nombres(): array
    {
        $enLaBase = Oficina::query()->whereNotNull('nombre')->pluck('nombre', 'codigo')->all();

        return $enLaBase !== [] ? $enLaBase : (array) config('edificio.nombres', []);
    }

    /** Para la pantalla de gestión: todas, en orden, como registros. */
    public function todas(): Collection
    {
        return Oficina::query()->orderBy('orden')->orderBy('codigo')->get();
    }

    /**
     * Da de alta o renombra una oficina, buscándola por su código. El código es la identidad y se
     * normaliza a mayúsculas, como en las fichas.
     *
     * @throws ValidationException
     */
    public function guardar(string $codigo, ?string $nombre = null, ?int $orden = null, ?string $gerencia = null): Oficina
    {
        $codigo = mb_strtoupper(trim($codigo));
        $nombre = trim((string) $nombre);
        $gerencia = trim((string) $gerencia);

        if ($codigo === '') {
            throw ValidationException::withMessages([
                'codigo' => 'Hace falta el código de la oficina, como «2-1» o «LOBBY».',
            ]);
        }

        return Oficina::updateOrCreate(
            ['codigo' => $codigo],
            [
                'nombre' => $nombre === '' ? null : mb_substr($nombre, 0, 60),
                // La gerencia se guarda en MAYÚSCULAS, igual que «dependencia» del trabajador, para
                // que casen al ofrecer los pisos.
                'gerencia' => $gerencia === '' ? null : mb_strtoupper(mb_substr($gerencia, 0, 120)),
                // Al final de la lista si es nueva; el que se pase, si se indica.
                'orden' => $orden ?? (Oficina::max('orden') + 1),
            ],
        );
    }

    /**
     * Quita una oficina del catálogo. No arrastra nada: los movimientos guardan el piso como
     * texto, así que borrar una oficina no toca ningún histórico, solo deja de ofrecerla.
     */
    public function eliminar(Oficina $oficina): void
    {
        $oficina->delete();
    }
}
