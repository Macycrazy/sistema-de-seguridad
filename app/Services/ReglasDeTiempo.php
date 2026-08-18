<?php

namespace App\Services;

use App\Models\Parametro;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Las reglas de tiempo del marcaje, que el administrador ajusta sin tocar código.
 *
 * Estaban como constantes en Marcaje. Siguen ahí como valores POR OMISIÓN —y como respaldo si la
 * tabla estuviera vacía—, pero el valor que manda sale de la tabla «parametros». Marcaje las lee a
 * través de este servicio, así que cambiarlas desde la pantalla vale en el siguiente marcaje.
 *
 * Los valores se leen una vez por instancia: Marcaje pregunta varias veces en un mismo marcaje y
 * no tiene sentido volver a la base cada vez.
 */
class ReglasDeTiempo
{
    /**
     * Las reglas conocidas: clave => [etiqueta, explicación, por omisión, mínimo, máximo, unidad].
     * El default sale de las constantes de Marcaje, que son la fuente única.
     */
    public const REGLAS = [
        'segundos_antiduplicado' => [
            'Plazo antiduplicado',
            'Segundos en que volver a pulsar el mismo botón no crea otro asiento (doble pulsación, doble lectura del carnet).',
            Marcaje::SEGUNDOS_ANTIDUPLICADO, 1, 120, 'segundos',
        ],
        'minutos_entre_entradas' => [
            'Entre dos entradas',
            'Minutos que tienen que pasar para volver a marcarle la entrada a quien ya entró.',
            Marcaje::MINUTOS_ENTRE_ENTRADAS, 0, 600, 'minutos',
        ],
        'minutos_entre_entrada_y_salida' => [
            'Entre la entrada y su salida',
            'Minutos mínimos entre que alguien entra y se le marca la salida.',
            Marcaje::MINUTOS_ENTRE_ENTRADA_Y_SALIDA, 0, 600, 'minutos',
        ],
        'minutos_entre_salida_y_entrada' => [
            'Entre la salida y volver a entrar',
            'Minutos que tienen que pasar tras una salida para volver a marcarle la entrada.',
            Marcaje::MINUTOS_ENTRE_SALIDA_Y_ENTRADA, 0, 600, 'minutos',
        ],
    ];

    /** @var array<string, int>|null Los valores de la base, leídos una sola vez. */
    private ?array $valores = null;

    public function segundosAntiduplicado(): int
    {
        return $this->valor('segundos_antiduplicado');
    }

    public function minutosEntreEntradas(): int
    {
        return $this->valor('minutos_entre_entradas');
    }

    public function minutosEntreEntradaYSalida(): int
    {
        return $this->valor('minutos_entre_entrada_y_salida');
    }

    public function minutosEntreSalidaYEntrada(): int
    {
        return $this->valor('minutos_entre_salida_y_entrada');
    }

    /**
     * Para la pantalla: las reglas con su etiqueta, explicación, valor actual y límites.
     *
     * @return Collection<int, array{clave:string, etiqueta:string, explicacion:string, valor:int, minimo:int, maximo:int, unidad:string}>
     */
    public function todas(): Collection
    {
        return collect(self::REGLAS)->map(fn (array $regla, string $clave) => [
            'clave' => $clave,
            'etiqueta' => $regla[0],
            'explicacion' => $regla[1],
            'valor' => $this->valor($clave),
            'minimo' => $regla[3],
            'maximo' => $regla[4],
            'unidad' => $regla[5],
        ])->values();
    }

    /**
     * Ajusta una regla. Se valida contra sus límites: dejar un plazo en cero o en un número
     * absurdo desde la pantalla no puede romper la puerta.
     *
     * @throws ValidationException
     */
    public function guardar(string $clave, int $valor): void
    {
        if (! array_key_exists($clave, self::REGLAS)) {
            throw ValidationException::withMessages(['valor' => 'Ese parámetro no existe.']);
        }

        [, , , $minimo, $maximo] = self::REGLAS[$clave];

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

        // Si falta en la base (tabla recién creada, o una clave nueva), manda el default del código.
        return $this->valores[$clave] ?? self::REGLAS[$clave][2];
    }
}
