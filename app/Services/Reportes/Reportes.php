<?php

namespace App\Services\Reportes;

use App\Models\Movimiento;
use App\Models\Persona;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Las cuentas del registro: cuánta gente entró, cuándo, y quién más.
 *
 * El registro (parte 2) responde «¿qué pasó tal día?»; esto responde «¿qué viene pasando?» sobre
 * un tramo de fechas. Todo se calcula en la base con sumas y agrupaciones —no se traen los
 * movimientos a memoria— porque el histórico crece sin techo y un reporte de un mes no puede
 * depender de cuántos asientos haya.
 *
 * La unidad que se cuenta es la ENTRADA: cada entrada es una visita al edificio. Las salidas no
 * se cuentan como evento aparte —son el cierre de la misma visita— y contarlas dividiría cada
 * visita en dos. Quien olvidó marcar salida igual aparece: entró.
 */
final class Reportes
{
    /** Tramos de más de esto se recortan: un reporte se lee de un vistazo, no en 400 barras. */
    public const MAXIMO_DIAS = 92;

    /**
     * Las cifras de cabecera del tramo.
     *
     * @return array{entradas:int, personas:int, dias:int, promedio:int, franjaPico:?int, picoEntradas:int}
     */
    public function resumen(CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $franja = $this->porFranja($desde, $hasta);
        $picoHora = null;
        $picoEntradas = 0;
        foreach ($franja as $hora => $entradas) {
            if ($entradas > $picoEntradas) {
                $picoEntradas = $entradas;
                $picoHora = $hora;
            }
        }

        $base = $this->entradasEntre($desde, $hasta);
        $entradas = (clone $base)->count();
        $personas = (clone $base)->distinct()->count('persona_id');
        // Los días con movimiento se cuentan en PHP: «ocurrio_en::date» es el casteo de
        // PostgreSQL y SQLite no lo entiende. Ver el comentario largo en porFranja().
        $dias = (clone $base)->pluck('ocurrio_en')
            ->map(fn ($momento) => CarbonImmutable::parse($momento)->toDateString())
            ->unique()
            ->count();

        return [
            'entradas' => $entradas,
            'personas' => $personas,
            'dias' => (int) $dias,
            'promedio' => $dias > 0 ? (int) round($entradas / $dias) : 0,
            'franjaPico' => $picoHora,
            'picoEntradas' => $picoEntradas,
        ];
    }

    /**
     * Entradas por cada día del calendario del tramo, huecos incluidos en cero.
     *
     * Se rellena el calendario entero —no solo los días con movimiento— para que la gráfica no
     * mienta: un fin de semana vacío tiene que verse vacío, no desaparecer y juntar el lunes con
     * el viernes.
     *
     * @return Collection<int, array{fecha:CarbonImmutable, entradas:int}>
     */
    public function porDia(CarbonImmutable $desde, CarbonImmutable $hasta): Collection
    {
        // Agrupado por fecha en PHP, por lo mismo que en porFranja(): el «::date» es de PostgreSQL.
        $conteo = $this->entradasEntre($desde, $hasta)
            ->pluck('ocurrio_en')
            ->countBy(fn ($momento) => CarbonImmutable::parse($momento)->toDateString());

        $dias = collect();
        for ($dia = $desde->startOfDay(); $dia->lessThanOrEqualTo($hasta); $dia = $dia->addDay()) {
            $dias->push([
                'fecha' => $dia,
                'entradas' => (int) ($conteo[$dia->toDateString()] ?? 0),
            ]);
        }

        return $dias;
    }

    /**
     * Entradas por hora del día (0 a 23), sumando todos los días del tramo.
     *
     * Responde «¿a qué hora se llena la puerta?»: el pico de la mañana, la hora de salida a
     * almorzar. Las 24 franjas siempre están, aunque valgan cero.
     *
     * @return array<int, int>
     */
    public function porFranja(CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        /*
         * La hora se saca en PHP y no en el SQL.
         *
         * Iba con «extract(hour from ocurrio_en)::int», que es de PostgreSQL: SQLite no entiende
         * ni «extract» ni el «::», así que la pantalla de reportes reventaba entera en las
         * pruebas. Cada base tiene su propia forma de sacar la hora —strftime, extract, hour()— y
         * escribir un «si la base es tal, entonces» es justo lo que se queda desfasado.
         *
         * Se traen las horas y se cuentan aquí. El tramo de un reporte son los movimientos de un
         * día, una semana o un mes: miles de filas en el peor caso, que es nada. Si algún día se
         * pidieran años enteros, ESTO es lo que habría que volver a bajar al SQL.
         */
        $conteo = $this->entradasEntre($desde, $hasta)
            ->pluck('ocurrio_en')
            ->countBy(fn ($momento) => (int) CarbonImmutable::parse($momento)->format('G'));

        $franjas = [];
        for ($hora = 0; $hora < 24; $hora++) {
            $franjas[$hora] = (int) ($conteo[$hora] ?? 0);
        }

        return $franjas;
    }

    /**
     * Entradas de trabajadores frente a las de invitados.
     *
     * @return array{trabajador:int, invitado:int}
     */
    public function porTipo(CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $conteo = $this->entradasEntre($desde, $hasta)
            ->join('personas', 'personas.id', '=', 'movimientos.persona_id')
            ->selectRaw('personas.tipo as t, count(*) as n')
            ->groupBy('personas.tipo')
            ->pluck('n', 't');

        return [
            'trabajador' => (int) ($conteo[Persona::TRABAJADOR] ?? 0),
            'invitado' => (int) ($conteo[Persona::INVITADO] ?? 0),
        ];
    }

    /**
     * Quiénes entraron más veces en el tramo, de mayor a menor.
     *
     * @return Collection<int, array{persona:?Persona, visitas:int}>
     */
    public function masFrecuentes(CarbonImmutable $desde, CarbonImmutable $hasta, int $limite = 10): Collection
    {
        $filas = $this->entradasEntre($desde, $hasta)
            ->selectRaw('persona_id, count(*) as visitas')
            ->groupBy('persona_id')
            ->orderByDesc('visitas')
            ->orderBy('persona_id')
            ->limit($limite)
            ->get();

        $personas = Persona::whereIn('id', $filas->pluck('persona_id'))->get()->keyBy('id');

        return $filas->map(fn ($fila) => [
            'persona' => $personas->get($fila->persona_id),
            'visitas' => (int) $fila->visitas,
        ]);
    }

    /**
     * Entradas por unidad del organigrama, de mayor a menor.
     *
     * Se agrupa por la unidad enlazada; quien no la tenga cae a su texto de «dependencia», y quien
     * no tenga ni eso, a «Sin unidad». Así el desglose no pierde a nadie mientras se adopta el
     * organigrama.
     *
     * @return Collection<int, array{unidad:string, entradas:int}>
     */
    public function porDepartamento(CarbonImmutable $desde, CarbonImmutable $hasta, int $limite = 8): Collection
    {
        return $this->entradasEntre($desde, $hasta)
            ->join('personas', 'personas.id', '=', 'movimientos.persona_id')
            ->leftJoin('departamentos', 'departamentos.id', '=', 'personas.departamento_id')
            ->selectRaw("coalesce(departamentos.nombre, personas.dependencia, 'Sin unidad') as unidad, count(*) as n")
            ->groupByRaw("coalesce(departamentos.nombre, personas.dependencia, 'Sin unidad')")
            ->orderByDesc('n')
            ->orderBy('unidad')
            ->limit($limite)
            ->get()
            ->map(fn ($fila) => ['unidad' => $fila->unidad, 'entradas' => (int) $fila->n]);
    }

    /**
     * Cómo se entró en el tramo: en carro, en moto o a pie.
     *
     * Se mira el vehículo congelado en cada entrada. Sin tipo_vehiculo, se entró caminando.
     *
     * @return array{carro:int, moto:int, aPie:int}
     */
    public function porVehiculo(CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $conteo = $this->entradasEntre($desde, $hasta)
            ->selectRaw("coalesce(tipo_vehiculo, 'a-pie') as t, count(*) as n")
            ->groupByRaw('coalesce(tipo_vehiculo, \'a-pie\')')
            ->pluck('n', 't');

        return [
            'carro' => (int) ($conteo['carro'] ?? 0),
            'moto' => (int) ($conteo['moto'] ?? 0),
            'aPie' => (int) ($conteo['a-pie'] ?? 0),
        ];
    }

    /** El tramo, siempre de entradas y siempre acotado por [inicio del día, fin del día]. */
    private function entradasEntre(CarbonImmutable $desde, CarbonImmutable $hasta)
    {
        return Movimiento::query()
            ->where('movimientos.tipo', Movimiento::ENTRADA)
            ->whereBetween('ocurrio_en', [$desde->startOfDay(), $hasta->endOfDay()]);
    }
}
