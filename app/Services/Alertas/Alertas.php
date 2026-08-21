<?php

namespace App\Services\Alertas;

use App\Models\Movimiento;
use App\Models\Persona;
use App\Services\Estacionamiento\Estacionamiento;
use App\Services\Estacionamiento\Flota;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * El motor de alertas: mira el estado de ahora mismo y dice qué merece atención.
 *
 * No guarda nada ni notifica por ningún canal —eso es una decisión de operación, no de código—:
 * solo detecta y devuelve. Se calcula sobre quién está dentro en este momento, que es una sola
 * consulta, así que sirve tanto para pintar la pantalla como para el número del menú.
 *
 * Las alertas, todas derivables de lo que ya está guardado y sin inventar datos:
 *
 *   · permanencia   — quien lleva demasiadas horas dentro sin marcar salida (casi siempre, un
 *                     olvido de marcar la salida; a veces, alguien que de verdad sigue ahí).
 *   · aforo         — más gente dentro a la vez de la que el aforo configurado admite.
 *   · estacionamiento — lo mismo con los vehículos, por total y por tipo.
 *   · flota fuera   — un vehículo de la empresa que salió y no ha vuelto.
 *
 * Los umbrales salen todos de UmbralesDeAlerta, ajustables desde Ajustes; en 0 se apagan.
 */
final class Alertas
{
    public function __construct(
        private UmbralesDeAlerta $umbrales,
        private Estacionamiento $estacionamiento,
        private Flota $flota,
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

        // ESTACIONAMIENTO — se llena aparte del aforo de personas (mucha gente entra caminando), y
        // carros y motos se cuentan por separado, que no ocupan el mismo sitio. Se avisa por el
        // total y por cada tipo, según qué aforos estén puestos.
        $aforos = $this->estacionamiento->aforos();
        $porTipo = $this->estacionamiento->porTipoDentro();

        $cupos = [
            ['aforo' => $aforos['total'], 'dentro' => $porTipo['carro'] + $porTipo['moto'], 'que' => 'vehículos', 'titulo' => 'Estacionamiento lleno'],
            ['aforo' => $aforos['carro'], 'dentro' => $porTipo['carro'], 'que' => 'carros', 'titulo' => 'Sin puestos de carros'],
            ['aforo' => $aforos['moto'], 'dentro' => $porTipo['moto'], 'que' => 'motos', 'titulo' => 'Sin puestos de motos'],
        ];

        foreach ($cupos as $cupo) {
            if ($cupo['aforo'] > 0 && $cupo['dentro'] > $cupo['aforo']) {
                $alertas->push(new Alerta(
                    tipo: Alerta::ESTACIONAMIENTO,
                    severidad: Alerta::URGENTE,
                    titulo: $cupo['titulo'],
                    detalle: $cupo['dentro'].' '.$cupo['que'].' dentro; el aforo son '.$cupo['aforo'].'.',
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
                    detalle: 'Entró '.$fila['desde']->translatedFormat('D d M \a \l\a\s g:i a').' y no ha marcado salida.',
                    personaId: $fila['persona_id'],
                    personaNombre: $persona?->nombre,
                    desde: $fila['desde'],
                ));
            }
        }

        // FLOTA FUERA — un vehículo de la empresa que salió y no ha vuelto. Sale para un trámite y
        // vuelve; si no vuelve, alguien tiene que enterarse sin ir a mirarlo uno por uno.
        $horasFuera = $this->umbrales->horasFlotaFuera();

        if ($horasFuera > 0) {
            $limiteFuera = $ahora->subHours($horasFuera);

            foreach ($this->flota->fuera() as $vehiculo) {
                if ($vehiculo->salio_en->greaterThanOrEqualTo($limiteFuera)) {
                    continue;
                }

                $llevaHoras = (int) $vehiculo->salio_en->diffInHours($ahora);
                $quien = $vehiculo->seLoLlevo ?: 'sin conductor anotado';

                $alertas->push(new Alerta(
                    tipo: Alerta::FLOTA_FUERA,
                    // Pasado el doble del plazo ya no es que se haya alargado el trámite.
                    severidad: $llevaHoras >= $horasFuera * 2 ? Alerta::URGENTE : Alerta::AVISO,
                    titulo: $vehiculo->placa.' lleva '.$llevaHoras.' h fuera',
                    detalle: 'Vehículo de la empresa ('.$vehiculo->descripcion.'). Se lo llevó '.$quien
                        .' el '.$vehiculo->salio_en->translatedFormat('D d M \a \l\a\s g:i a')
                        .($vehiculo->loDejoSalir ? ', anotado por '.$vehiculo->loDejoSalir : '').'.',
                    desde: $vehiculo->salio_en,
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
     * «Dentro» es aquel cuyo último movimiento fue una entrada. El último de cada persona lo
     * resuelve Movimiento::ultimoDeCadaPersona(), en un SQL que entienden tanto PostgreSQL como
     * SQLite — antes iba con un «distinct on», que es solo de Postgres y tumbaba las pruebas.
     *
     * @return Collection<int, object{persona_id:int, ocurrio_en:string}>
     */
    private function dentroAhora(): Collection
    {
        return Movimiento::ultimoDeCadaPersona()
            ->where('movimientos.tipo', Movimiento::ENTRADA)
            ->get(['movimientos.persona_id', 'movimientos.tipo', 'movimientos.ocurrio_en']);
    }
}
