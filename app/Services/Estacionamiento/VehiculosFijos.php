<?php

namespace App\Services\Estacionamiento;

use App\Models\Persona;
use App\Models\VehiculoFijo;
use App\Services\Auditoria\Auditoria;
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

        // Un vehículo no puede estar dentro dos veces. Pasaba con el doble toque y, sobre todo,
        // ahora que se puede anotar desde dos sitios —la puerta y el estacionamiento—: el mismo
        // carro quedaba con dos estadías abiertas, ocupando dos plazas y contando doble en el
        // aforo, y al sacarlo se cerraba una sola.
        $yaEstaDentro = VehiculoFijo::query()->abiertos()->where('placa', $placa)->exists();

        if ($yaEstaDentro) {
            throw ValidationException::withMessages([
                'placaFija' => "El vehículo {$placa} ya está dentro: hay que marcarle la salida antes de volver a anotarlo.",
            ]);
        }

        // El puesto es OPCIONAL: en la puerta del estacionamiento se anota el vehículo al entrar,
        // y quién está adentro le pone la plaza después, cuando ve dónde quedó. Si se pone, tiene
        // que estar libre y admitir el tipo; sin puesto, entra igual y queda «sin plaza» hasta que
        // se la asignen.
        if ($puestoId !== null) {
            $libre = app(Estacionamiento::class)->puestosLibres($tipo)->contains('id', $puestoId);

            if (! $libre) {
                throw ValidationException::withMessages([
                    'puestoFijo' => 'Ese puesto no está libre para este tipo de vehículo.',
                ]);
            }
        }

        [$conductorId, $conductorNombreFinal] = $this->conductor($conductorCedula, $conductorNombre, 'conductorFija');

        $estadia = VehiculoFijo::create([
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
            'usuario_id' => $usuarioId ?? auth()->id(),
        ]);

        app(Auditoria::class)->anotoVehiculo($estadia);

        return $estadia;
    }

    /**
     * Le marca la salida y anota quién se lo lleva —que puede ser otro—. Libera el puesto. No se
     * borra: queda en la bitácora.
     *
     * Se guardan DOS personas distintas y hacen falta las dos: a quién se le entregó el vehículo
     * (el conductor, un dato que teclea el guardia) y desde qué cuenta se dio por entregado (el
     * usuario). La entrada ya guardaba su usuario; la salida no guardaba ninguno, así que de un
     * vehículo que se fue sin deber irse no quedaba constancia de quién lo dejó salir.
     *
     * Devuelve cuántas estadías se cerraron. Normalmente una; más de una significa que ese
     * vehículo estaba dentro por duplicado (ver abajo).
     *
     * @throws ValidationException
     */
    public function sacar(
        VehiculoFijo $fijo,
        ?string $conductorCedula = null,
        ?string $conductorNombre = null,
        ?int $usuarioId = null,
    ): int {
        if ($fijo->salio_en !== null) {
            return 0;
        }

        [$conductorId, $conductorNombreFinal] = $this->conductor($conductorCedula, $conductorNombre, 'conductorSalida');

        $salida = [
            'salio_en' => now(),
            'salida_conductor_id' => $conductorId,
            'salida_conductor_nombre' => $conductorNombreFinal,
            'salida_usuario_id' => $usuarioId ?? auth()->id(),
        ];

        $fijo->update($salida);
        app(Auditoria::class)->sacoVehiculo($fijo);

        /*
         * Se cierran TODAS las estadías abiertas de esa placa, no solo la que se tocó.
         *
         * Un vehículo no puede estar dentro dos veces —ahora se impide al anotarlo—, pero los
         * duplicados de antes siguen ahí, y con ellos pasaba esto: se le marcaba la salida al
         * carro, se actualizaba la pantalla, y el carro seguía apareciendo dentro. El vehículo se
         * fue de verdad: dejar la otra abierta lo tendría dentro para siempre, ocupando plaza y
         * contando en el aforo.
         */
        $duplicadas = VehiculoFijo::query()
            ->abiertos()
            ->where('placa', $fijo->placa)
            ->where('id', '!=', $fijo->id)
            ->get();

        foreach ($duplicadas as $otra) {
            $otra->update($salida);
            app(Auditoria::class)->cerroEstadiaDuplicada($otra);
        }

        return 1 + $duplicadas->count();
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
