<?php

namespace App\Services\Estacionamiento;

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
     * Anota un vehículo fijo en un puesto libre.
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

        return VehiculoFijo::create([
            'puesto_id' => $puestoId,
            'placa' => $placa,
            'tipo_vehiculo' => $tipo,
            'marca' => ($marca = trim((string) $marca)) === '' ? null : mb_substr($marca, 0, 40),
            'color' => ($color = trim((string) $color)) === '' ? null : mb_substr($color, 0, 30),
            'nota' => ($nota = trim((string) $nota)) === '' ? null : mb_substr($nota, 0, 120),
            'entro_en' => now(),
            'usuario_id' => $usuarioId,
        ]);
    }

    /** Le marca la salida: libera el puesto. No se borra, queda en la bitácora. */
    public function sacar(VehiculoFijo $fijo): void
    {
        if ($fijo->salio_en === null) {
            $fijo->update(['salio_en' => now()]);
        }
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
