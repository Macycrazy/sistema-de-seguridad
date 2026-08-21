<?php

namespace App\Services\Estacionamiento;

use App\Models\Persona;
use App\Models\Vehiculo;
use App\Models\VehiculoFijo;
use App\Services\DatosVehiculo;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Anotar el vehículo EN EL MISMO GESTO de marcar a la persona.
 *
 * Antes eran dos formularios: se marcaba a la persona en la puerta y, aparte, se anotaba el
 * vehículo en el estacionamiento tecleando otra vez su cédula. Dos capturas de lo mismo, y la
 * segunda con el conductor opcional: si el guardia no la hacía —con la cola esperando, no la
 * hacía—, el vehículo quedaba sin dueño y no había manera de saber quién entró o salió en él.
 *
 * Aquí el conductor no se teclea: es la persona que se está marcando. Sale gratis y sale siempre.
 *
 * Lo que NO hace: sustituir al formulario del estacionamiento. Por ahí siguen entrando la flota y
 * los carros de quien no se marca en la puerta.
 */
final class VehiculoEnLaPuerta
{
    /**
     * Los vehículos que ya tiene guardados: los de un toque.
     *
     * La primera vez hay que teclear la placa; a partir de ahí el vehículo queda en su ficha y
     * marcarlo cuesta un toque. Por eso se guarda al entrar (ver «entra»).
     *
     * @return Collection<int, Vehiculo>
     */
    public function suyos(Persona $persona): Collection
    {
        return $persona->vehiculos()->get();
    }

    /**
     * Los vehículos que están DENTRO y que metió esta persona: lo que se le puede ofrecer al salir.
     *
     * Se ofrece solo lo que está dentro, que es la regla: no se puede sacar un vehículo que no
     * está. Y solo lo suyo, porque es lo que se marca de un toque; para llevarse el carro de otro
     * está la pantalla del estacionamiento, donde se anota a quién se le entrega.
     *
     * @return Collection<int, VehiculoFijo>
     */
    public function dentroASuNombre(Persona $persona): Collection
    {
        return VehiculoFijo::query()
            ->abiertos()
            ->where('conductor_id', $persona->id)
            ->orderByDesc('entro_en')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Entra con un vehículo: abre su estadía, ya a nombre de esta persona.
     *
     * Sin puesto: en la puerta no se sabe dónde va a quedar. Se lo asigna después quien está
     * adentro, desde el estacionamiento, como cualquier otro vehículo.
     *
     * @throws ValidationException
     */
    public function entra(
        Persona $persona,
        string $placa,
        ?string $tipo = null,
        ?string $marca = null,
        ?string $color = null,
    ): VehiculoFijo {
        $placaLimpia = DatosVehiculo::normalizarPlaca($placa);

        if ($placaLimpia === null) {
            throw ValidationException::withMessages([
                'placaEntrada' => 'Hace falta la placa del vehículo.',
            ]);
        }

        $estadia = app(VehiculosFijos::class)->registrar(
            placa: $placaLimpia,
            tipoVehiculo: $tipo ?? DatosVehiculo::CARRO,
            puestoId: null,
            marca: $marca,
            color: $color,
            conductorCedula: $persona->cedula,
        );

        $this->recordar($persona, $placaLimpia, $tipo, $marca, $color);

        return $estadia;
    }

    /**
     * Sale con estos vehículos: les marca la salida a su nombre.
     *
     * La regla que gobierna esto: solo se puede salir con un vehículo que ESTÁ. Una estadía ya
     * cerrada —o que nunca existió— no se vuelve a cerrar, y decirlo es mejor que tragárselo: si
     * la pantalla ofreció un vehículo que entretanto sacó otro, el guardia tiene que enterarse.
     *
     * @param  list<int>  $estadiaIds
     * @return Collection<int, VehiculoFijo>
     *
     * @throws ValidationException
     */
    public function sale(Persona $persona, array $estadiaIds): Collection
    {
        $estadias = VehiculoFijo::query()->whereIn('id', $estadiaIds)->get();

        $cerradas = $estadias->filter(fn (VehiculoFijo $e) => $e->salio_en !== null);

        if ($cerradas->isNotEmpty()) {
            throw ValidationException::withMessages([
                'vehiculoSalida' => 'Ese vehículo ya no está dentro: alguien le marcó la salida antes ('
                    .$cerradas->pluck('placa')->implode(', ').').',
            ]);
        }

        foreach ($estadias as $estadia) {
            app(VehiculosFijos::class)->sacar(
                $estadia,
                conductorCedula: $persona->cedula,
            );
        }

        return $estadias;
    }

    /**
     * Deja el vehículo en la ficha de la persona, si no lo tenía ya.
     *
     * Es lo que hace que la segunda vez sea un toque. Nunca revienta el marcaje: que no se pueda
     * recordar un vehículo no es motivo para no dejar entrar a nadie.
     */
    private function recordar(Persona $persona, string $placa, ?string $tipo, ?string $marca, ?string $color): void
    {
        try {
            if ($persona->vehiculoConPlaca($placa) !== null) {
                return;
            }

            Vehiculo::create([
                'persona_id' => $persona->id,
                'tipo' => DatosVehiculo::normalizarTipo($tipo) ?? DatosVehiculo::CARRO,
                'marca' => ($marca = trim((string) $marca)) === '' ? null : mb_substr($marca, 0, DatosVehiculo::LARGO_MARCA),
                'color' => ($color = trim((string) $color)) === '' ? null : mb_substr($color, 0, DatosVehiculo::LARGO_COLOR),
                'placa' => $placa,
            ]);
        } catch (\Throwable) {
            // Recordarlo es una comodidad, no parte del marcaje.
        }
    }
}
