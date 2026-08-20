<?php

namespace App\Services\Estacionamiento;

use App\Models\Persona;
use App\Models\VehiculoFijo;
use App\Services\DatosVehiculo;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * La bitácora de vehículos fijos: los de la empresa, o los que ya estaban y se quedan estacionados
 * sin pasar por el marcaje de una persona. Solo placa y puesto.
 *
 * La pantalla no decide nada: pregunta aquí, donde se valida. Un fijo ocupa su puesto hasta que se
 * le marca la salida; mientras esté abierto, ese puesto cuenta como tomado (ver Estacionamiento).
 */
class VehiculosFijos
{
    /**
     * Los que siguen dentro, con el código de su puesto. El que entró más tarde primero.
     *
     * @return Collection<int, VehiculoFijo>
     */
    public function abiertos(): Collection
    {
        return VehiculoFijo::query()
            ->abiertos()
            ->with('puesto')
            ->orderByDesc('entro_en')
            ->get();
    }

    /**
     * Anota un vehículo en un puesto libre, con quién lo conduce al entrar.
     *
     * El conductor es opcional y puede ser una persona del sistema (por cédula) o un nombre suelto.
     * El «flotaId» enlaza al vehículo de la empresa del catálogo, si viene de ahí.
     *
     * @throws ValidationException
     */
    public function registrar(
        string $placa,
        string $tipoVehiculo,
        ?int $puestoId,
        ?string $marca = null,
        ?string $color = null,
        ?string $nota = null,
        ?int $usuarioId = null,
        ?string $conductorCedula = null,
        ?string $conductorNombre = null,
        ?int $flotaId = null,
    ): VehiculoFijo {
        $placa = DatosVehiculo::normalizarPlaca($placa);
        $tipo = DatosVehiculo::normalizarTipo($tipoVehiculo);

        if ($placa === null) {
            throw ValidationException::withMessages([
                'placaFija' => 'Hace falta la placa del vehículo.',
            ]);
        }

        if ($puestoId === null) {
            throw ValidationException::withMessages([
                'puestoFijo' => 'Hay que decir en qué puesto queda.',
            ]);
        }

        // Que la plaza esté libre y admita el tipo lo decide el estacionamiento, que ya conoce lo
        // que hay dentro (personas y otros fijos).
        $libre = app(Estacionamiento::class)->puestosLibres($tipo)->contains('id', $puestoId);

        if (! $libre) {
            throw ValidationException::withMessages([
                'puestoFijo' => 'Ese puesto no está libre para este tipo de vehículo.',
            ]);
        }

        [$conductorId, $conductorNombreFinal] = $this->conductor($conductorCedula, $conductorNombre, 'conductorFija');

        return VehiculoFijo::create([
            'flota_id' => $flotaId,
            'puesto_id' => $puestoId,
            'placa' => $placa,
            'tipo_vehiculo' => $tipo,
            'marca' => ($marca = trim((string) $marca)) === '' ? null : mb_substr($marca, 0, 40),
            'color' => ($color = trim((string) $color)) === '' ? null : mb_substr($color, 0, 30),
            'nota' => ($nota = trim((string) $nota)) === '' ? null : mb_substr($nota, 0, 120),
            'conductor_id' => $conductorId,
            'conductor_nombre' => $conductorNombreFinal,
            'entro_en' => now(),
            'usuario_id' => $usuarioId,
        ]);
    }

    /**
     * Le marca la salida y anota quién se lo lleva —que puede ser otro—. Libera el puesto. No se
     * borra: queda en la bitácora.
     *
     * @throws ValidationException
     */
    public function sacar(VehiculoFijo $fijo, ?string $conductorCedula = null, ?string $conductorNombre = null): void
    {
        if ($fijo->salio_en !== null) {
            return;
        }

        [$conductorId, $conductorNombreFinal] = $this->conductor($conductorCedula, $conductorNombre, 'conductorSalida');

        $fijo->update([
            'salio_en' => now(),
            'salida_conductor_id' => $conductorId,
            'salida_conductor_nombre' => $conductorNombreFinal,
        ]);
    }

    /**
     * Resuelve un conductor: una persona del sistema (por cédula) o un nombre suelto. Devuelve
     * [id, nombre]. Si se da una cédula que no existe, lo dice en vez de tragárselo.
     *
     * @return array{0: ?int, 1: ?string}
     *
     * @throws ValidationException
     */
    private function conductor(?string $cedula, ?string $nombre, string $campo): array
    {
        $cedula = Persona::normalizarCedula($cedula);
        $nombre = trim((string) $nombre);

        if ($cedula !== '') {
            $persona = Persona::where('cedula', $cedula)->first();

            if (! $persona) {
                throw ValidationException::withMessages([
                    $campo => 'No hay nadie con esa cédula. Deja la cédula vacía y escribe el nombre.',
                ]);
            }

            return [$persona->id, $persona->nombre];
        }

        return [null, $nombre === '' ? null : mb_substr($nombre, 0, 120)];
    }

    /**
     * Los ids de puesto que ocupan los fijos que siguen dentro.
     *
     * @return Collection<int, int>
     */
    public function puestosOcupados(): Collection
    {
        return VehiculoFijo::query()->abiertos()->pluck('puesto_id')->unique()->values();
    }
}
