<?php

namespace App\Services\Retencion;

use App\Models\Parametro;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Cuánto tiempo se guardan los datos antes de poder depurarlos.
 *
 * Mismo patrón que ReglasDeTiempo y UmbralesDeAlerta, misma tabla «parametros» (claves
 * «retencion_»). La diferencia de fondo: aquí el valor por omisión es 0 = DESACTIVADO. Depurar
 * borra, y borrar choca con la inmutabilidad del registro; por eso no se borra nada hasta que
 * alguien fija un periodo a conciencia. Con 0, la depuración no toca nada aunque se ejecute.
 *
 * Se guardan en meses porque es como se piensa una política de retención («guardamos dos años»),
 * no en días.
 */
class RetencionDeDatos
{
    /** clave => [etiqueta, explicación, por omisión, mínimo, máximo, unidad]. */
    public const PERIODOS = [
        'retencion_movimientos_meses' => [
            'Movimientos',
            'Meses que se guardan las entradas y salidas antes de poder archivarlas y depurarlas. En 0 no se depura nunca.',
            0, 0, 120, 'meses',
        ],
        'retencion_bitacora_meses' => [
            'Bitácora de auditoría',
            'Meses que se guarda la bitácora antes de poder archivarla y depurarla. Suele guardarse más que los movimientos. En 0 no se depura nunca.',
            0, 0, 120, 'meses',
        ],
    ];

    /** @var array<string, int>|null */
    private ?array $valores = null;

    public function mesesMovimientos(): int
    {
        return $this->valor('retencion_movimientos_meses');
    }

    public function mesesBitacora(): int
    {
        return $this->valor('retencion_bitacora_meses');
    }

    /**
     * Para la pantalla: los periodos con etiqueta, explicación, valor y límites.
     *
     * @return Collection<int, array{clave:string, etiqueta:string, explicacion:string, valor:int, minimo:int, maximo:int, unidad:string}>
     */
    public function todos(): Collection
    {
        return collect(self::PERIODOS)->map(fn (array $p, string $clave) => [
            'clave' => $clave,
            'etiqueta' => $p[0],
            'explicacion' => $p[1],
            'valor' => $this->valor($clave),
            'minimo' => $p[3],
            'maximo' => $p[4],
            'unidad' => $p[5],
        ])->values();
    }

    /** @throws ValidationException */
    public function guardar(string $clave, int $valor): void
    {
        if (! array_key_exists($clave, self::PERIODOS)) {
            throw ValidationException::withMessages(['valor' => 'Ese periodo no existe.']);
        }

        [, , , $minimo, $maximo] = self::PERIODOS[$clave];

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

        return $this->valores[$clave] ?? self::PERIODOS[$clave][2];
    }
}
