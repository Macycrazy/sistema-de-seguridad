<?php

namespace App\Services\Registro;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Datos inventados para desarrollar la parte 2 mientras el esquema de tablas no está
 * acordado entre las tres partes.
 *
 * Las PERSONAS son inventadas; la FORMA está copiada del listado de personal real
 * (corte 27/07/2026), y eso incluye a propósito los casos que rompen el código ingenuo:
 *
 *   · gente con pasaporte en vez de cédula (RD…, FZ…, N0…)
 *   · gente sin documento registrado
 *   · tres entes distintos compartiendo el mismo puesto de vigilancia
 *   · personal de VENAPP sin cargo asignado
 *
 * Si la pantalla aguanta esto, aguantará la lista de verdad. Datos reales no entran al
 * repositorio: la base real no se copia a la máquina de nadie.
 *
 * Dos cosas que no son capricho:
 *
 * 1. Los datos tienen que salir idénticos en cada request. Livewire rehace el componente
 *    en cada interacción; si cambiaran, la tabla parpadearía y ningún filtro sería
 *    comprobable.
 *
 *    Por eso aquí NO se usa Faker ni mt_rand()/shuffle(): todos comparten el motor
 *    aleatorio GLOBAL del proceso, y basta con que cualquier otro código consuma una
 *    tirada para desplazar la secuencia entera. Carbon lo hace al inicializarse. La
 *    solución es un Randomizer propio con su Mt19937 sembrado, que nadie más consume.
 *
 * 2. Las fechas se calculan relativas a hoy, no van cableadas.
 */
final class RegistroInventado implements FuenteDelRegistro
{
    private const SEMILLA = 2026;

    /** Proporción tomada del listado real: 174 CIIP, 50 Marca País, 75 VENAPP. */
    private const PLANTILLA = [
        'ciip' => 174,
        'marca-pais' => 50,
        'venapp' => 75,
    ];

    private const INVITADOS = 30;

    private const DIAS_DE_HISTORICO = 14;

    private const ENTRARON_HOY = 230;

    private const SALIERON_HOY = 43;

    private const NOMBRES = [
        'José', 'María', 'Carlos', 'Ana', 'Luis', 'Carmen', 'Pedro', 'Rosa',
        'Miguel', 'Yolanda', 'Rafael', 'Gladys', 'Jesús', 'Marisol', 'Antonio',
        'Zoraida', 'Francisco', 'Nelly', 'Juan', 'Morella', 'Alejandro', 'Yajaira',
        'Ramón', 'Belkis', 'Gustavo', 'Ligia', 'Orlando', 'Nancy', 'Douglas',
        'Milagros', 'Freddy', 'Elizabeth', 'Wilmer', 'Yusmary', 'Argenis', 'Deisy',
        'Eduardo', 'Norkys', 'Héctor', 'Zulay',
    ];

    private const SEGUNDOS_NOMBRES = [
        'Alberto', 'Coromoto', 'José', 'del Carmen', 'Antonio', 'Isabel', 'Rafael',
        'del Valle', 'Enrique', 'Josefina', 'Andreina', 'Gregorio', 'Alejandra', '',
    ];

    private const APELLIDOS = [
        'Rodríguez', 'González', 'Pérez', 'Hernández', 'García', 'Martínez',
        'López', 'Sánchez', 'Ramírez', 'Torres', 'Flores', 'Rivas', 'Gómez',
        'Díaz', 'Moreno', 'Álvarez', 'Romero', 'Suárez', 'Blanco', 'Castillo',
        'Guerra', 'Mendoza', 'Reyes', 'Chirinos', 'Bastidas', 'Colmenares',
        'Quintero', 'Vargas', 'Peña', 'Salazar', 'Bracho', 'Fuenmayor',
        'Urdaneta', 'Nava', 'Villalobos', 'Paredes', 'Contreras', 'Márquez',
        'Rojas', 'Silva',
    ];

    /** Nombres de dependencia tomados de la estructura real del organismo. */
    private const DEPENDENCIAS = [
        'ciip' => [
            ['Gerencia General de Gestión Humana', ['4-1', '4-9']],
            ['Gerencia General de Gestión Administrativa', ['4-2', '4-3', '4-8']],
            ['Gerencia General del Observatorio Venezolano Antibloqueo', ['3-2']],
            ['Gerencia General de Promoción de Inversiones', ['1-2', '1-7']],
            ['Gerencia General de Planificación y Presupuesto', ['4-4', '4-5']],
            ['Gerencia General de Gestión Comunicacional', ['2-4', '2-6']],
            ['Gerencia General de Tecnología de la Información y Comunicación', ['2-1']],
            ['Gerencia General de Seguridad Integral', ['4-7', 'LOBBY']],
            ['Gerencia General de Proyectos de Inversión y Activos', ['2-3']],
            ['Gerencia General del Despacho', ['8-2']],
            ['Auditoría Interna', ['2-5']],
            ['Consultoría Jurídica', ['2-2', '2-7']],
            ['Presidencia', ['8-2', '9']],
            ['Directorio', ['9']],
        ],
        'marca-pais' => [
            ['Presidencia', ['3-1']],
            ['Gerencia General', ['3-1']],
            ['Gerencia de Gestión Administrativa', ['4-6']],
            ['Gerencia Gestión Comunicacional', ['3-5']],
            ['Gerencia de Gestión Humana', ['3-4']],
            ['Consultoría Jurídica', ['3-4']],
            ['Gerencia de Planificación y Presupuesto', ['4-6']],
            ['Gerencia de Atención Ciudadana', ['3-1']],
        ],
        'venapp' => [
            ['VENAPP', ['7']],
        ],
    ];

    private const CARGOS = [
        'ciip' => [
            'Bachiller', 'Profesional', 'Técnico', 'Coordinador', 'Gerente',
            'Gerente General', 'Asesor Profesional', 'Asesor Técnico',
            'Obrero General', 'Obrero Certificado', 'Asistente Ejecutivo',
            'Auditor Interno', 'Director Principal', 'Director Suplente', 'Pasante',
        ],
        'marca-pais' => [
            'Apoyo Profesional', 'Apoyo Institucional', 'Apoyo Técnico',
            'Apoyo Administrativo', 'Gerente', 'Gerente General',
            'Honorarios Profesionales', 'Chofer', 'Aseadora',
        ],
        // En el listado real, el personal de VENAPP no trae cargo asignado.
        'venapp' => [],
    ];

    /** Los vigilantes que anotan. Cuando llegue la parte 3 esto sale de la tabla users. */
    private const VIGILANTES = ['k.moreno', 'a.puglia', 'r.bastidas', 'm.chirinos'];

    private const PREFIJOS_PASAPORTE = ['RD', 'FZ', 'N0', 'AB'];

    /** Motor aleatorio propio: sembrado, privado y ajeno al estado global del proceso. */
    private Randomizer $dado;

    /** @var Collection<string, Persona> */
    private Collection $personas;

    /** @var Collection<int, Movimiento> */
    private Collection $movimientos;

    public function __construct()
    {
        $this->dado = new Randomizer(new Mt19937(self::SEMILLA));

        $this->personas = $this->inventarPersonas();
        $this->movimientos = $this->inventarMovimientos();
    }

    public function movimientosDelDia(
        CarbonImmutable $fecha,
        ?TipoDePersona $tipo = null,
        ?Ente $ente = null,
    ): Collection {
        return $this->movimientos
            ->filter(fn (Movimiento $m) => $m->ocurrioEn->isSameDay($fecha))
            ->when($tipo, fn (Collection $c) => $c->filter(fn (Movimiento $m) => $m->persona->tipo === $tipo))
            ->when($ente, fn (Collection $c) => $c->filter(fn (Movimiento $m) => $m->persona->ente === $ente))
            ->sortByDesc(fn (Movimiento $m) => $m->ocurrioEn->getTimestamp())
            ->values();
    }

    public function dentroEn(CarbonImmutable $fecha): int
    {
        // Una persona está dentro si ese día entró y no registró salida.
        //
        // El recuento se limita al día a propósito. Mirando todo el histórico, quien
        // entró un martes y se fue sin que le anotaran la salida seguiría contando como
        // «dentro» el jueves, el viernes y para siempre: los olvidos se acumularían y el
        // contador se separaría de la realidad sin manera de volver. Acotado al día, el
        // error dura como mucho hasta la medianoche.
        return $this->movimientos
            ->filter(fn (Movimiento $m) => $m->ocurrioEn->isSameDay($fecha))
            ->groupBy(fn (Movimiento $m) => $m->persona->id)
            ->filter(function (Collection $delaPersona) {
                $ultimo = $delaPersona->sortByDesc(fn (Movimiento $m) => $m->ocurrioEn->getTimestamp())->first();

                return $ultimo->esEntrada();
            })
            ->count();
    }

    public function buscarPersonas(string $texto, int $limite = 8): Collection
    {
        $texto = trim($texto);

        if (mb_strlen($texto) < 2) {
            return collect();
        }

        $aguja = $this->normalizar($texto);

        return $this->personas
            ->filter(function (Persona $p) use ($aguja) {
                $documento = $this->normalizar((string) $p->cedula);

                return ($documento !== '' && str_contains($documento, $aguja))
                    || str_contains($this->normalizar($p->nombre()), $aguja)
                    || str_contains($this->normalizar($p->nombreCompleto()), $aguja);
            })
            ->sortBy(fn (Persona $p) => $p->nombreCompleto())
            ->take($limite)
            ->values();
    }

    public function historicoDe(string $personaId): Collection
    {
        return $this->movimientos
            ->filter(fn (Movimiento $m) => $m->persona->id === $personaId)
            ->sortByDesc(fn (Movimiento $m) => $m->ocurrioEn->getTimestamp())
            ->values();
    }

    public function persona(string $personaId): ?Persona
    {
        return $this->personas->get($personaId);
    }

    /** @return Collection<string, Persona> */
    private function inventarPersonas(): Collection
    {
        $personas = collect();
        $usados = [];
        $n = 0;

        foreach (self::PLANTILLA as $claveEnte => $cuantos) {
            $ente = Ente::from($claveEnte);

            for ($i = 0; $i < $cuantos; $i++) {
                [$dependencia, $pisos] = $this->elegir(self::DEPENDENCIAS[$claveEnte]);
                $cargos = self::CARGOS[$claveEnte];

                $id = 'p-'.(++$n);
                $apellidos = $this->elegir(self::APELLIDOS).' '.$this->elegir(self::APELLIDOS);

                // Una de cada cien fichas viene mal cargada, con los apellidos repetidos
                // en el campo de nombres. Está así en el listado real (fila 30) y se
                // reproduce a propósito: si los datos de prueba fueran todos correctos,
                // nadie sabría cómo se ve eso en pantalla hasta verlo en producción.
                $nombres = $this->dado->getInt(1, 100) === 1
                    ? $apellidos
                    : trim($this->elegir(self::NOMBRES).' '.$this->elegir(self::SEGUNDOS_NOMBRES));

                $personas[$id] = new Persona(
                    id: $id,
                    cedula: $this->inventarDocumento($usados),
                    apellidos: $apellidos,
                    nombres: $nombres,
                    tipo: TipoDePersona::Trabajador,
                    ente: $ente,
                    dependencia: $dependencia,
                    piso: $this->elegir($pisos),
                    cargo: $cargos ? $this->elegir($cargos) : null,
                );
            }
        }

        // Del invitado, lo mínimo: nombre y a quién viene a ver. Ni ente, ni cargo, ni piso.
        $anfitriones = $personas->take(40)->map(fn (Persona $p) => $p->nombre())->values()->all();

        for ($i = 0; $i < self::INVITADOS; $i++) {
            $id = 'p-'.(++$n);

            $personas[$id] = new Persona(
                id: $id,
                cedula: $this->inventarDocumento($usados),
                apellidos: $this->elegir(self::APELLIDOS),
                nombres: $this->elegir(self::NOMBRES),
                tipo: TipoDePersona::Invitado,
                visitaA: $this->elegir($anfitriones),
            );
        }

        return $personas;
    }

    /**
     * Documentos con la misma variedad que el listado real.
     *
     * De cada 100: unos 3 sin documento registrado y unos 3 con pasaporte. El resto,
     * cédula venezolana ya formateada con su prefijo — el prefijo se guarda en el dato
     * porque el listado no permite deducirlo.
     *
     * @param  array<string, bool>  $usados
     */
    private function inventarDocumento(array &$usados): ?string
    {
        do {
            $suerte = $this->dado->getInt(1, 100);

            if ($suerte <= 3) {
                return null;
            }

            if ($suerte <= 6) {
                $documento = $this->elegir(self::PREFIJOS_PASAPORTE).$this->dado->getInt(1_000_000, 9_999_999);
            } else {
                $numero = $this->dado->getInt(3_500_000, 31_500_000);
                $documento = 'V-'.number_format($numero, 0, ',', '.');
            }
        } while (isset($usados[$documento]));

        $usados[$documento] = true;

        return $documento;
    }

    /** @return Collection<int, Movimiento> */
    private function inventarMovimientos(): Collection
    {
        $movimientos = collect();
        $siguienteId = 1;

        $trabajadores = $this->personas->filter(fn (Persona $p) => $p->tipo === TipoDePersona::Trabajador)->values();
        $invitados = $this->personas->filter(fn (Persona $p) => $p->tipo === TipoDePersona::Invitado)->values();

        $hoy = CarbonImmutable::today();

        $anotar = function (Persona $persona, Sentido $sentido, CarbonImmutable $cuando) use ($movimientos, &$siguienteId) {
            $movimientos->push(new Movimiento(
                id: 'mov-'.$siguienteId++,
                persona: $persona,
                sentido: $sentido,
                ocurrioEn: $cuando,
                registradoPor: $this->elegir(self::VIGILANTES),
            ));
        };

        for ($atras = self::DIAS_DE_HISTORICO - 1; $atras >= 0; $atras--) {
            $dia = $hoy->subDays($atras);
            $esHoy = $atras === 0;

            // El puesto registra todos los días. El fin de semana ve mucho menos movimiento,
            // pero no cero: hay guardias, mantenimiento y trabajo puntual. Hoy siempre va lleno
            // para que el demo tenga qué mostrar, sea el día que sea.
            if ($esHoy) {
                $asistencia = self::ENTRARON_HOY;
            } elseif ($dia->isSaturday() || $dia->isSunday()) {
                $asistencia = (int) round($trabajadores->count() * 0.18);
            } else {
                $asistencia = $this->dado->getInt(198, 232);
            }

            $delDia = $this->barajar($trabajadores)->take($asistencia);

            // Hoy la jornada está a medias: solo una parte ya registró su salida.
            // Los días pasados cierran completos, salvo algún olvido suelto.
            $conSalida = $esHoy
                ? self::SALIERON_HOY
                : $asistencia - $this->dado->getInt(0, 3);

            foreach ($delDia->values() as $puesto => $persona) {
                $entrada = $dia
                    ->setTime(6, 30)
                    ->addMinutes($this->dado->getInt(0, 390));

                $anotar($persona, Sentido::Entrada, $entrada);

                if ($puesto < $conSalida) {
                    // La salida se ancla al mismo día para que la jornada no se derrame
                    // al siguiente: un turno que cruza la medianoche confundiría tanto
                    // a la lista del día como al contador.
                    $salida = $entrada->addMinutes($this->dado->getInt(240, 560));

                    $anotar($persona, Sentido::Salida, min($salida, $dia->setTime(23, 45)));
                }
            }

            // Los invitados llegan sueltos a lo largo del día.
            $cuantos = $this->dado->getInt(3, 9);

            foreach ($this->barajar($invitados)->take($cuantos)->values() as $puesto => $invitado) {
                $entrada = $dia
                    ->setTime(7, 0)
                    ->addMinutes($this->dado->getInt(0, 540));

                $anotar($invitado, Sentido::Entrada, $entrada);

                if (! $esHoy || $puesto % 2 === 0) {
                    $salida = $entrada->addMinutes($this->dado->getInt(35, 200));

                    $anotar($invitado, Sentido::Salida, min($salida, $dia->setTime(23, 45)));
                }
            }
        }

        return $movimientos->values();
    }

    /**
     * Baraja con el motor propio.
     *
     * Ojo: Collection::shuffle() de Laravel llama a Arr::shuffle(), que usa un Randomizer
     * con motor por defecto (CSPRNG) y por tanto ignora cualquier semilla. Aquí no sirve.
     *
     * @param  Collection<int, Persona>  $personas
     * @return Collection<int, Persona>
     */
    private function barajar(Collection $personas): Collection
    {
        return collect($this->dado->shuffleArray($personas->all()));
    }

    /**
     * @template T
     *
     * @param  array<int, T>  $opciones
     * @return T
     */
    private function elegir(array $opciones): mixed
    {
        $claves = array_keys($opciones);

        return $opciones[$claves[$this->dado->getInt(0, count($claves) - 1)]];
    }

    /** Para que «perez» encuentre a «Pérez» y «12345678» encuentre a «V-12.345.678». */
    private function normalizar(string $texto): string
    {
        return preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii($texto)));
    }
}
