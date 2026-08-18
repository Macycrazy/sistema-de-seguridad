<?php

namespace App\Services\Alertas;

use App\Models\Movimiento;
use App\Models\Persona;
use App\Services\Estacionamiento\Estacionamiento;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * El motor de alertas: mira el estado de ahora mismo y dice qué merece atención.
 *
 * No guarda nada ni notifica por ningún canal —eso es una decisión de operación, no de código—:
 * solo detecta y devuelve. Se calcula sobre quién está dentro en este momento, que es una sola
 * consulta, así que sirve tanto para pintar la pantalla como para el número del menú.
 *
 * Dos alertas por ahora, las dos derivables del registro sin inventar datos:
 *
 *   · permanencia — quien lleva demasiadas horas dentro sin marcar salida (casi siempre, un
 *                   olvido de marcar la salida; a veces, alguien que de verdad sigue ahí).
 *   · aforo       — más gente dentro a la vez de la que el aforo configurado admite.
 *
 * Los umbrales de las dos salen de UmbralesDeAlerta, ajustables desde Ajustes.
 */
final class Alertas
{
    public function __construct(
        private UmbralesDeAlerta $umbrales,
        private Estacionamiento $estacionamiento,
    ) {}

    /**
     * Todas las alertas activas ahora, las urgentes primero y, dentro de cada gravedad, la
     * permanencia más larga arriba.
     *
     * @return Collection<int, Alerta>
     */
    public function activas(): Collection
    {
        $dentro = $this->dentroAhora();
        $ahora = CarbonImmutable::now();
        $alertas = collect();

        // AFORO — una sola alerta para todo el edificio.
        $aforo = $this->umbrales->aforo();
        if ($aforo > 0 && $dentro->count() > $aforo) {
            $alertas->push(new Alerta(
                tipo: Alerta::AFORO,
                severidad: Alerta::URGENTE,
                titulo: 'Aforo superado',
                detalle: $dentro->count().' personas dentro; el aforo son '.$aforo.'.',
            ));
        }

        // ESTACIONAMIENTO — se llena aparte del aforo de personas: mucha gente entra caminando.
        $aforoEst = $this->umbrales->aforoEstacionamiento();
        if ($aforoEst > 0) {
            $vehiculos = $this->estacionamiento->cuantosDentro();
            if ($vehiculos > $aforoEst) {
                $alertas->push(new Alerta(
                    tipo: Alerta::ESTACIONAMIENTO,
                    severidad: Alerta::URGENTE,
                    titulo: 'Estacionamiento lleno',
                    detalle: $vehiculos.' vehículos dentro; el aforo son '.$aforoEst.'.',
                ));
            }
        }

        // PERMANENCIA — una alerta por persona que lleva de más.
        $horas = $this->umbrales->horasPermanencia();
        $limite = $ahora->subHours($horas);

        $largos = $dentro
            ->map(fn ($fila) => ['persona_id' => (string) $fila->persona_id, 'desde' => CarbonImmutable::parse($fila->ocurrio_en)])
            ->filter(fn ($fila) => $fila['desde']->lessThan($limite))
            ->sortBy('desde')
            ->values();

        if ($largos->isNotEmpty()) {
            $personas = Persona::whereIn('id', $largos->pluck('persona_id'))->get()->keyBy('id');

            foreach ($largos as $fila) {
                $persona = $personas->get($fila['persona_id']);
                $llevaHoras = (int) $fila['desde']->diffInHours($ahora);

                $alertas->push(new Alerta(
                    tipo: Alerta::PERMANENCIA,
                    // Al doble del umbral ya no es un olvido probable: sube a urgente.
                    severidad: $llevaHoras >= $horas * 2 ? Alerta::URGENTE : Alerta::AVISO,
                    titulo: ($persona?->nombre ?? 'Persona retirada').' lleva '.$llevaHoras.' h dentro',
                    detalle: 'Entró '.$fila['desde']->translatedFormat('D d M \a \l\a\s H:i').' y no ha marcado salida.',
                    personaId: $fila['persona_id'],
                    personaNombre: $persona?->nombre,
                    desde: $fila['desde'],
                ));
            }
        }

        return $alertas
            ->sortByDesc(fn (Alerta $a) => $a->esUrgente() ? 1 : 0)
            ->values();
    }

    /** Cuántas alertas activas hay ahora. Para el número del menú. */
    public function cuantas(): int
    {
        return $this->activas()->count();
    }

    /**
     * Quién está dentro en este momento, con la hora de su entrada.
     *
     * «Dentro» es aquel cuyo último movimiento fue una entrada. Se resuelve con un DISTINCT ON de
     * Postgres: el primer registro por persona, ordenando por hora descendente, es su último
     * movimiento; si es una entrada, está dentro.
     *
     * @return Collection<int, object{persona_id:int, ocurrio_en:string}>
     */
    private function dentroAhora(): Collection
    {
        $ultimos = Movimiento::query()
            ->selectRaw('distinct on (persona_id) persona_id, tipo, ocurrio_en')
            ->orderBy('persona_id')
            ->orderByDesc('ocurrio_en')
            ->orderByDesc('id');

        return DB::query()
            ->fromSub($ultimos, 'u')
            ->where('tipo', Movimiento::ENTRADA)
            ->get();
    }
}
