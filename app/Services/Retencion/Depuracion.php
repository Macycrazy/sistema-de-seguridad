<?php

namespace App\Services\Retencion;

use App\Models\Bitacora;
use App\Models\Movimiento;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

/**
 * Archiva y depura los datos viejos según la política de retención.
 *
 * Es la única parte del sistema que borra registros del histórico, y por eso hace las cosas en
 * este orden y nunca al revés: primero exporta a un archivo lo que se va a borrar, y solo después
 * borra. Si el archivo no se puede escribir, no se borra nada. Personas NO se toca jamás: el
 * padrón se conserva; lo que caduca son las entradas/salidas y la bitácora, no la gente.
 *
 * Nada de esto ocurre solo: alguien tiene que ejecutar el comando `registro:depurar --confirmar`.
 * Y no ocurre nada si el periodo está en 0 (desactivado), que es como viene de fábrica.
 */
class Depuracion
{
    /** Dónde quedan los archivos previos al borrado. */
    private const CARPETA = 'depuracion';

    public function __construct(private RetencionDeDatos $retencion) {}

    /**
     * Qué se depuraría ahora mismo, sin tocar nada. Para el modo en seco del comando.
     *
     * @return array<int, array{tabla:string, meses:int, corte:?CarbonImmutable, cuantos:int}>
     */
    public function plan(): array
    {
        return [
            $this->planDe('movimientos', $this->retencion->mesesMovimientos()),
            $this->planDe('bitacora', $this->retencion->mesesBitacora()),
        ];
    }

    /**
     * Archiva y borra de verdad. Devuelve, por tabla, cuántos se borraron y a qué archivo.
     *
     * @return array<int, array{tabla:string, meses:int, corte:?CarbonImmutable, cuantos:int, archivo:?string}>
     */
    public function ejecutar(): array
    {
        $informe = [];

        foreach ($this->plan() as $plan) {
            $informe[] = $this->depurarTabla($plan);
        }

        return $informe;
    }

    /** True si al menos una tabla tiene un periodo configurado (distinto de 0). */
    public function estaActiva(): bool
    {
        return $this->retencion->mesesMovimientos() > 0 || $this->retencion->mesesBitacora() > 0;
    }

    /** @return array{tabla:string, meses:int, corte:?CarbonImmutable, cuantos:int} */
    private function planDe(string $tabla, int $meses): array
    {
        if ($meses <= 0) {
            return ['tabla' => $tabla, 'meses' => 0, 'corte' => null, 'cuantos' => 0];
        }

        $corte = CarbonImmutable::now()->subMonths($meses)->startOfDay();

        return [
            'tabla' => $tabla,
            'meses' => $meses,
            'corte' => $corte,
            'cuantos' => $this->consulta($tabla, $corte)->count(),
        ];
    }

    /** @return array{tabla:string, meses:int, corte:?CarbonImmutable, cuantos:int, archivo:?string} */
    private function depurarTabla(array $plan): array
    {
        $base = ['tabla' => $plan['tabla'], 'meses' => $plan['meses'], 'corte' => $plan['corte']];

        if ($plan['corte'] === null || $plan['cuantos'] === 0) {
            return $base + ['cuantos' => 0, 'archivo' => null];
        }

        $consulta = $this->consulta($plan['tabla'], $plan['corte']);

        // Primero el archivo; si falla, se sale por excepción y no se borra nada.
        $archivo = $this->archivar($plan['tabla'], $plan['corte'], $consulta);

        // Y solo entonces el borrado.
        $borrados = $this->consulta($plan['tabla'], $plan['corte'])->delete();

        return $base + ['cuantos' => $borrados, 'archivo' => $archivo];
    }

    /** La consulta de lo más viejo que el corte, para la tabla dada. */
    private function consulta(string $tabla, CarbonImmutable $corte): Builder
    {
        $modelo = $tabla === 'bitacora' ? Bitacora::query() : Movimiento::query();

        return $modelo->where('ocurrio_en', '<', $corte);
    }

    /**
     * Vuelca la consulta a un CSV en el disco local y devuelve su ruta. Se escribe con un flujo
     * temporal y un cursor para no cargar en memoria un histórico entero.
     */
    private function archivar(string $tabla, CarbonImmutable $corte, Builder $consulta): string
    {
        $ruta = self::CARPETA.'/'.$tabla.'-antes-de-'.$corte->format('Y-m-d').'.csv';

        $flujo = fopen('php://temp', 'r+');

        $encabezado = false;
        foreach ($consulta->orderBy('id')->cursor() as $fila) {
            $datos = $fila->getAttributes();

            if (! $encabezado) {
                fputcsv($flujo, array_keys($datos));
                $encabezado = true;
            }

            fputcsv($flujo, array_values($datos));
        }

        rewind($flujo);
        Storage::disk('local')->put($ruta, stream_get_contents($flujo) ?: '');
        fclose($flujo);

        return $ruta;
    }
}
