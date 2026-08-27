<?php

namespace App\Services\Alertas;

use App\Models\Movimiento;
use App\Models\Persona;
use App\Services\Estacionamiento\Estacionamiento;
use App\Services\Estacionamiento\Flota;
use App\Services\Pases\Pases;
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
 *   · flota fuera   — un vehículo de la empresa que está fuera, desde que sale; urgente si tarda.
 *   · pase fuera    — un pase de visitante entregado y sin devolver, con lo mismo.
 *
 * Los umbrales salen todos de UmbralesDeAlerta, ajustables desde Ajustes; en 0 se apagan.
 */
final class Alertas
{
    public function __construct(
        private UmbralesDeAlerta $umbrales,
        private Estacionamiento $estacionamiento,
        private Flota $flota,
        private Pases $pases,
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

        // Los que alguien ya miró y silenció: siguen dentro de verdad —el guardia de noche, un
        // turno largo— y su aviso volverá mañana si aún están.
        $silenciados = app(CierreDeOlvidos::class)->silenciados();
        $largos = $largos->reject(fn ($fila) => $silenciados->contains($fila['persona_id']))->values();

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
                    personaCedula: $persona?->cedula,
                    desde: $fila['desde'],
                ));
            }
        }

        // FLOTA FUERA — todo vehículo de la empresa que está fuera, desde que sale.
        //
        // Se avisa en cuanto sale y no al pasar un plazo: un vehículo de la empresa circulando por
        // ahí es algo que se quiere SABER, aunque sea normal y aunque vuelva en una hora. El plazo
        // no decide si se avisa, sino cuándo el aviso pasa a urgente: hasta ahí es un trámite
        // largo; a partir de ahí, algo que mirar.
        $horasFuera = $this->umbrales->horasFlotaFuera();

        if ($horasFuera > 0) {
            foreach ($this->flota->fuera() as $vehiculo) {
                $minutos = (int) $vehiculo->salio_en->diffInMinutes($ahora);
                $llevaHoras = intdiv($minutos, 60);
                $quien = $vehiculo->seLoLlevo ?: 'sin conductor anotado';

                $alertas->push(new Alerta(
                    tipo: Alerta::FLOTA_FUERA,
                    severidad: $llevaHoras >= $horasFuera ? Alerta::URGENTE : Alerta::AVISO,
                    titulo: $vehiculo->placa.' está fuera'.($llevaHoras > 0 ? ' · '.$llevaHoras.' h' : ''),
                    detalle: 'Vehículo de la empresa ('.$vehiculo->descripcion.'). Se lo llevó '.$quien
                        .' el '.$vehiculo->salio_en->translatedFormat('D d M \a \l\a\s g:i a')
                        .($vehiculo->loDejoSalir ? ', anotado por '.$vehiculo->loDejoSalir : '').'.'
                        .($llevaHoras >= $horasFuera ? ' Lleva fuera más de las '.$horasFuera.' h previstas.' : ''),
                    desde: $vehiculo->salio_en,
                ));
            }
        }

        // PASE FUERA — cada pase de visitante que está en la calle. Igual que la flota: se avisa
        // desde que se entrega, porque saber cuáles están fuera es justo el punto de contarlos; el
        // plazo solo decide cuándo deja de ser una visita larga y pasa a ser un pase que no vuelve.
        $horasPase = $this->umbrales->horasPaseFuera();

        if ($horasPase > 0) {
            $idsDentro = $dentro->pluck('persona_id')->map(fn ($id) => (string) $id)->all();

            foreach ($this->pases->fuera() as $entrega) {
                $llevaHoras = intdiv((int) $entrega->entregado_en->diffInMinutes($ahora), 60);

                // Que el pase esté fuera mientras su visitante está dentro es lo normal. Que esté
                // fuera cuando esa persona YA SE FUE del edificio es otra cosa: el pase se fue con
                // ella. Eso es urgente desde el primer minuto, no cuando se cumpla un plazo.
                $seFueConEl = ! in_array((string) $entrega->persona_id, $idsDentro, true);
                $tarde = $llevaHoras >= $horasPase;

                $alertas->push(new Alerta(
                    tipo: Alerta::PASE_FUERA,
                    severidad: $seFueConEl || $tarde ? Alerta::URGENTE : Alerta::AVISO,
                    titulo: 'Pase '.($entrega->pase?->codigo ?? '?')
                        .($seFueConEl ? ' se fue sin devolverse' : ' fuera')
                        .($llevaHoras > 0 ? ' · '.$llevaHoras.' h' : ''),
                    detalle: 'Lo lleva '.($entrega->persona?->nombre ?? 'alguien').', desde las '
                        .$entrega->entregado_en->format('g:i a')
                        .($entrega->usuario ? ' (lo entregó '.($entrega->usuario->nombre ?? $entrega->usuario->usuario).')' : '').'.'
                        .($seFueConEl
                            ? ' Ya marcó su salida del edificio y el pase no volvió.'
                            : ($tarde ? ' Lleva más de las '.$horasPase.' h previstas.' : '')),
                    personaId: $entrega->persona_id ? (string) $entrega->persona_id : null,
                    personaNombre: $entrega->persona?->nombre,
                    personaCedula: $entrega->persona?->cedula,
                    desde: CarbonImmutable::parse($entrega->entregado_en),
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
