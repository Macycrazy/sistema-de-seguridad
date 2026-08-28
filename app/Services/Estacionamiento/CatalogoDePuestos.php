<?php

namespace App\Services\Estacionamiento;

use App\Models\Puesto;
use App\Services\DatosVehiculo;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * El catálogo de plazas del estacionamiento, que administra el edificio.
 *
 * La pantalla no decide nada: pregunta aquí, donde se valida en el servidor. Un puesto se busca por
 * su código y se actualiza (no se duplica); quitarlo lo borra del catálogo, pero no toca el
 * histórico —«movimientos.puesto_id» es «nullOnDelete»—.
 */
class CatalogoDePuestos
{
    /** Los tipos que admite un puesto, con su etiqueta. El vacío es «cualquiera». */
    public const TIPOS = [
        '' => 'Cualquiera',
        DatosVehiculo::CARRO => 'Carro',
        DatosVehiculo::MOTO => 'Moto',
    ];

    /** @return Collection<int, Puesto> */
    public function todos(): Collection
    {
        return Puesto::query()->orderBy('orden')->orderBy('codigo')->get();
    }

    /**
     * Da de alta una plaza, o cambia la que se pase en «$puesto».
     *
     * Editar y crear son cosas distintas y hay que decir cuál es: al editar, el código puede
     * cambiar y lo que se renombra es ESA plaza; al crear, un código que ya exista es un error.
     */
    public function guardar(string $codigo, ?string $tipo = null, ?string $zona = null, ?int $orden = null, ?Puesto $puesto = null): Puesto
    {
        $codigo = mb_strtoupper(trim($codigo));
        $tipo = trim((string) $tipo);
        $zona = trim((string) $zona);

        if ($codigo === '') {
            throw ValidationException::withMessages([
                'codigo' => 'Hace falta el código del puesto, como «A-1» o «S2-14».',
            ]);
        }

        if ($tipo !== '' && ! in_array($tipo, [DatosVehiculo::CARRO, DatosVehiculo::MOTO], true)) {
            throw ValidationException::withMessages([
                'tipo' => 'El tipo del puesto es carro, moto o cualquiera.',
            ]);
        }

        /*
         * El código identifica la plaza, así que uno repetido es un error de quien lo teclea, no
         * una orden de sustituir. Antes esto era un «updateOrCreate» y pasaban dos cosas malas y
         * silenciosas:
         *
         *   · dar de alta un código que ya existía no creaba nada: machacaba el que había, y el
         *     total no subía por muchas plazas que se cargaran;
         *   · al EDITAR una plaza y cambiarle el código, la vieja se quedaba donde estaba y lo que
         *     se sobrescribía era otra distinta —la que ya tuviera ese código—.
         *
         * Ahora renombrar renombra, y un código repetido se dice.
         */
        $otro = Puesto::where('codigo', $codigo)
            ->when($puesto, fn ($q) => $q->whereKeyNot($puesto->getKey()))
            ->first();

        if ($otro) {
            throw ValidationException::withMessages([
                'codigo' => 'Ya hay una plaza con el código «'.$codigo.'»'
                    .($otro->zona ? ' (en '.$otro->zona.')' : '').'. Los códigos no se repiten.',
            ]);
        }

        $atributos = [
            'codigo' => $codigo,
            'tipo' => $tipo === '' ? null : $tipo,
            'zona' => $zona === '' ? null : mb_substr($zona, 0, 60),
        ];

        if ($puesto) {
            // Al editar se conserva su orden: es dónde va en la lista, no algo que se esté tocando.
            $puesto->update($atributos);

            return $puesto->refresh();
        }

        return Puesto::create($atributos + ['orden' => $orden ?? ((int) Puesto::max('orden') + 1)]);
    }

    public function activar(Puesto $puesto, bool $activo): void
    {
        $puesto->update(['activo' => $activo]);
    }

    public function eliminar(Puesto $puesto): void
    {
        $puesto->delete();
    }
}
