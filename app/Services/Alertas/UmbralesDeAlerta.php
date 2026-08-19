<?php

namespace App\Services\Alertas;

use App\Models\Parametro;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Los umbrales de las alertas, que el administrador ajusta sin tocar código.
 *
 * Mismo patrón que ReglasDeTiempo y misma tabla «parametros» (con claves propias, prefijadas
 * «alerta_»): un valor por omisión en el código que también es el respaldo si la tabla está
 * vacía, y el valor que manda sale de la base. Cambiarlos desde Ajustes vale en la siguiente
 * lectura de alertas, sin volver a desplegar.
 *
 * Se separan de ReglasDeTiempo a propósito: aquéllas gobiernan la puerta al marcar; éstas, cuándo
 * algo del registro merece un aviso. Son dos oficios distintos aunque compartan la tabla.
 */
class UmbralesDeAlerta
{
    /** clave => [etiqueta, explicación, por omisión, mínimo, máximo, unidad]. */
    public const UMBRALES = [
        'alerta_horas_permanencia' => [
            'Permanencia larga',
            'Horas que alguien puede llevar dentro sin marcar salida antes de que se avise. Casi siempre es un olvido de marcar la salida.',
            12, 1, 48, 'horas',
        ],
        'alerta_aforo' => [
            'Aforo',
            'Cuántas personas dentro a la vez disparan el aviso de aforo. En 0 el aviso queda desactivado.',
            0, 0, 999, 'personas',
        ],
        'alerta_aforo_estacionamiento' => [
            'Aforo del estacionamiento',
            'Cuántos vehículos dentro a la vez (en total) disparan el aviso de estacionamiento lleno. En 0 el aviso queda desactivado.',
            0, 0, 9999, 'vehículos',
        ],
        'alerta_aforo_carro' => [
            'Puestos de carros',
            'Cuántos carros caben. Carros y motos no ocupan el mismo sitio, así que se cuentan aparte. En 0 no se avisa por carros.',
            0, 0, 9999, 'carros',
        ],
        'alerta_aforo_moto' => [
            'Puestos de motos',
            'Cuántas motos caben, aparte de los carros. En 0 no se avisa por motos.',
            0, 0, 9999, 'motos',
        ],
    ];

    /** @var array<string, int>|null Los valores de la base, leídos una sola vez. */
    private ?array $valores = null;

    public function horasPermanencia(): int
    {
        return $this->valor('alerta_horas_permanencia');
    }

    public function aforo(): int
    {
        return $this->valor('alerta_aforo');
    }

    public function aforoEstacionamiento(): int
    {
        return $this->valor('alerta_aforo_estacionamiento');
    }

    public function aforoCarros(): int
    {
        return $this->valor('alerta_aforo_carro');
    }

    public function aforoMotos(): int
    {
        return $this->valor('alerta_aforo_moto');
    }

    /**
     * Para la pantalla: los umbrales con etiqueta, explicación, valor actual y límites.
     *
     * @return Collection<int, array{clave:string, etiqueta:string, explicacion:string, valor:int, minimo:int, maximo:int, unidad:string}>
     */
    public function todos(): Collection
    {
        return collect(self::UMBRALES)->map(fn (array $umbral, string $clave) => [
            'clave' => $clave,
            'etiqueta' => $umbral[0],
            'explicacion' => $umbral[1],
            'valor' => $this->valor($clave),
            'minimo' => $umbral[3],
            'maximo' => $umbral[4],
            'unidad' => $umbral[5],
        ])->values();
    }

    /**
     * Ajusta un umbral, validado contra sus límites.
     *
     * @throws ValidationException
     */
    public function guardar(string $clave, int $valor): void
    {
        if (! array_key_exists($clave, self::UMBRALES)) {
            throw ValidationException::withMessages(['valor' => 'Ese umbral no existe.']);
        }

        [, , , $minimo, $maximo] = self::UMBRALES[$clave];

        if ($valor < $minimo || $valor > $maximo) {
            throw ValidationException::withMessages([
                'valor' => "El valor tiene que estar entre $minimo y $maximo.",
            ]);
        }

        Parametro::updateOrCreate(['clave' => $clave], ['valor' => $valor]);

        $this->valores = null;
    }

    private function valor(string $clave): int
    {
        $this->valores ??= Parametro::query()->pluck('valor', 'clave')->all();

        return $this->valores[$clave] ?? self::UMBRALES[$clave][2];
    }
}
