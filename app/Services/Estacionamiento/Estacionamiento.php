<?php

namespace App\Services\Estacionamiento;

use App\Models\Movimiento;
use App\Models\Persona;
use App\Models\Puesto;
use App\Models\User;
use App\Models\VehiculoFijo;
use App\Services\Alertas\UmbralesDeAlerta;
use App\Services\DatosVehiculo;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Qué hay en el estacionamiento ahora mismo.
 *
 * Los vehículos son ESTADÍAS (App\Models\VehiculoFijo): cada uno se anota y se saca en el propio
 * estacionamiento, con su conductor y su puesto. La puerta ya no maneja vehículos —marca personas—;
 * un carro puede entrar con uno y salir con otro conductor, o quedarse aunque su conductor se vaya.
 * Un vehículo está dentro mientras su estadía siga abierta (sin salida).
 *
 * Es para el guardia del portón: ver cuántos vehículos hay, de qué tipo, con qué placa y en qué puesto.
 */
final class Estacionamiento
{
    public function __construct(private UmbralesDeAlerta $umbrales) {}

    /**
     * Los vehículos dentro ahora, el que entró más tarde primero.
     *
     * @return Collection<int, object{persona_id:int, nombre:string, cedula:?string, tipo_vehiculo:string, placa:?string, ocurrio_en:string, vehiculo:DatosVehiculo}>
     */
    public function vehiculosDentro(): Collection
    {
        // Los vehículos son estadías: cada uno se anota y se saca EN el estacionamiento, con su
        // conductor y su puesto (la puerta ya no maneja vehículos). Dentro = estadía abierta.
        return VehiculoFijo::query()
            ->abiertos()
            ->with('puesto')
            ->orderByDesc('entro_en')
            ->get()
            ->map(fn (VehiculoFijo $e) => (object) [
                'id' => $e->id,
                'placa' => $e->placa,
                'tipo_vehiculo' => $e->tipo_vehiculo,
                'marca' => $e->marca,
                'color' => $e->color,
                'puesto' => $e->puesto?->codigo,
                'puesto_id' => $e->puesto_id,
                'conductor' => $e->conductor_nombre,
                'ocurrio_en' => (string) $e->entro_en,
                'vehiculo' => DatosVehiculo::desde($e->tipo_vehiculo, $e->marca, null, $e->color, $e->placa),
            ]);
    }

    /** Cuántos vehículos hay dentro ahora. */
    public function cuantosDentro(): int
    {
        return $this->vehiculosDentro()->count();
    }

    /**
     * El desglose de lo que hay dentro por tipo.
     *
     * @return array{carro:int, moto:int}
     */
    public function porTipoDentro(): array
    {
        $dentro = $this->vehiculosDentro();

        return [
            'carro' => $dentro->where('tipo_vehiculo', DatosVehiculo::CARRO)->count(),
            'moto' => $dentro->where('tipo_vehiculo', DatosVehiculo::MOTO)->count(),
        ];
    }

    /**
     * Los ids de los puestos ocupados ahora mismo: los asignados a un vehículo que está dentro.
     *
     * @return Collection<int, int>
     */
    public function puestosOcupados(): Collection
    {
        // Ocupan puesto las estadías abiertas (todo vehículo dentro es una estadía).
        return VehiculoFijo::query()->abiertos()->whereNotNull('puesto_id')->pluck('puesto_id')->unique()->values();
    }

    /**
     * Las plazas libres —habilitadas y sin ocupar— que admiten ese tipo de vehículo. Sin tipo, las
     * que admiten cualquiera.
     *
     * @return Collection<int, Puesto>
     */
    public function puestosLibres(?string $tipo = null): Collection
    {
        $ocupados = $this->puestosOcupados()->all();

        return Puesto::query()
            ->where('activo', true)
            ->when($ocupados !== [], fn ($q) => $q->whereNotIn('id', $ocupados))
            ->orderBy('orden')->orderBy('codigo')
            ->get()
            ->filter(fn (Puesto $puesto) => $puesto->admite($tipo))
            ->values();
    }

    /**
     * Los que pernoctan: vehículos que siguen dentro ahora y cuya entrada fue antes de hoy —se
     * quedaron de noche—. Cada uno con su placa, dueño, tipo, desde cuándo y el puesto que ocupa.
     *
     * @return Collection<int, object>
     */
    public function pernoctan(): Collection
    {
        $inicioDeHoy = CarbonImmutable::today();

        return $this->vehiculosDentro()
            ->filter(fn ($fila) => $this->desde($fila->ocurrio_en)->lt($inicioDeHoy))
            ->values();
    }

    /**
     * Asigna, cambia o quita (puestoId nulo) el puesto de un vehículo que está dentro.
     *
     * Lo hace quien está EN el estacionamiento —que ve dónde quedó el vehículo—, no la puerta: en
     * la puerta todavía no se sabe en qué plaza va a estacionar. Actualiza el asiento de entrada.
     *
     * @throws ValidationException
     */
    public function asignarPuesto(int $estadiaId, ?int $puestoId): void
    {
        $estadia = VehiculoFijo::query()->abiertos()->find($estadiaId);

        if (! $estadia) {
            throw ValidationException::withMessages([
                'puesto' => 'Ese vehículo ya no está dentro.',
            ]);
        }

        if ($puestoId !== null) {
            $puesto = Puesto::find($puestoId);

            if (! $puesto || ! $puesto->activo) {
                throw ValidationException::withMessages([
                    'puesto' => 'Ese puesto no existe o está deshabilitado.',
                ]);
            }

            if (! $puesto->admite($estadia->tipo_vehiculo)) {
                throw ValidationException::withMessages([
                    'puesto' => 'Ese puesto no admite este tipo de vehículo.',
                ]);
            }

            // Ocupado por OTRO: si ya es de este mismo vehículo, reasignarlo al mismo no falla.
            $esElMismo = (int) $estadia->puesto_id === $puestoId;

            if (! $esElMismo && $this->puestosOcupados()->contains($puestoId)) {
                throw ValidationException::withMessages([
                    'puesto' => 'Ese puesto ya está ocupado por otro vehículo.',
                ]);
            }
        }

        $estadia->update(['puesto_id' => $puestoId]);
    }

    /**
     * Qué vehículos pernoctaron la NOCHE de una fecha pasada (histórico): los que estaban dentro en
     * la medianoche que cierra ese día. Incluye los de personas y los fijos. Cada uno con placa,
     * puesto, tipo, quién (dueño, o la nota del fijo) y desde cuándo.
     *
     * @return Collection<int, object>
     */
    public function pernoctaronLaNoche(CarbonImmutable $fecha): Collection
    {
        $corte = $fecha->startOfDay()->addDay();   // 00:00 del día siguiente = cierre de esa noche

        // Las estadías abiertas a través de esa noche: entraron antes del cierre y no habían salido.
        return VehiculoFijo::query()
            ->with('puesto')
            ->where('entro_en', '<=', $corte)
            ->where(fn ($q) => $q->whereNull('salio_en')->orWhere('salio_en', '>', $corte))
            ->orderByDesc('entro_en')
            ->get()
            ->map(fn (VehiculoFijo $e) => (object) [
                'placa' => $e->placa,
                'tipo_vehiculo' => $e->tipo_vehiculo,
                'marca' => $e->marca,
                'color' => $e->color,
                'puesto' => $e->puesto?->codigo,
                'quien' => $e->conductor_nombre ?: ($e->nota ?: '—'),
                'entro_en' => CarbonImmutable::parse($e->entro_en),
            ])
            ->values();
    }

    /** El aforo total configurado (0 = sin tope). */
    public function aforo(): int
    {
        return $this->umbrales->aforoEstacionamiento();
    }

    /**
     * Los tres aforos: el total, y los de carros y motos por separado —que no ocupan el mismo
     * sitio—. 0 en cualquiera significa «sin tope» para ese cupo.
     *
     * @return array{total:int, carro:int, moto:int}
     */
    public function aforos(): array
    {
        return [
            'total' => $this->umbrales->aforoEstacionamiento(),
            'carro' => $this->umbrales->aforoCarros(),
            'moto' => $this->umbrales->aforoMotos(),
        ];
    }

    /**
     * El movimiento de vehículos del día: entradas Y salidas que llevaban vehículo, más reciente
     * primero. Es el registro del estacionamiento —quién entró y quién sacó su vehículo hoy—, no
     * solo lo que hay dentro ahora.
     *
     * @return Collection<int, object>
     */
    public function delDia(CarbonImmutable $fecha): Collection
    {
        $desde = $fecha->startOfDay();
        $hasta = $fecha->endOfDay();

        $estadias = VehiculoFijo::query()
            ->with(['puesto', 'usuario', 'salidaUsuario'])
            ->where(fn ($q) => $q->whereBetween('entro_en', [$desde, $hasta])->orWhereBetween('salio_en', [$desde, $hasta]))
            ->get();

        // Cada estadía puede aportar una entrada (si entró hoy) y una salida (si salió hoy).
        $filas = collect();

        foreach ($estadias as $e) {
            // «Conductor» es a quién se le entregó el vehículo; «registradoPor», desde qué cuenta
            // se anotó el movimiento. Los dos, y separados: en una salida que no debió pasar, el
            // primero dice quién se lo llevó y el segundo quién lo dejó salir.
            $base = fn (bool $esEntrada, $cuando, ?string $conductor, ?User $usuario) => (object) [
                'esEntrada' => $esEntrada,
                'placa' => $e->placa,
                'tipo_vehiculo' => $e->tipo_vehiculo,
                'marca' => $e->marca,
                'color' => $e->color,
                'puesto' => $e->puesto?->codigo,
                'conductor' => $conductor,
                'registradoPor' => $usuario?->nombre ?? $usuario?->usuario,
                'ocurrio_en' => CarbonImmutable::parse($cuando),
                'vehiculo' => DatosVehiculo::desde($e->tipo_vehiculo, $e->marca, null, $e->color, $e->placa),
            ];

            if ($e->entro_en >= $desde && $e->entro_en <= $hasta) {
                $filas->push($base(true, $e->entro_en, $e->conductor_nombre, $e->usuario));
            }

            if ($e->salio_en !== null && $e->salio_en >= $desde && $e->salio_en <= $hasta) {
                $filas->push($base(false, $e->salio_en, $e->salida_conductor_nombre, $e->salidaUsuario));
            }
        }

        return $filas->sortByDesc('ocurrio_en')->values();
    }

    /** «Desde cuándo» está un vehículo, para la lista. */
    public function desde(string $ocurrioEn): CarbonImmutable
    {
        return CarbonImmutable::parse($ocurrioEn);
    }

    /**
     * Todo lo que ha hecho una placa: cada vez que entró, cuándo salió, con quién y en qué puesto.
     *
     * Es la pregunta que el registro del día no puede responder —«¿qué ha pasado con este carro?»,
     * «¿cuántas veces ha estado aquí?», «¿quién se lo llevó la última vez?»— y la que se hace
     * cuando algo va mal con un vehículo concreto.
     *
     * Se busca por trozo de placa y no por la placa entera, igual que la búsqueda de arriba: el
     * guardia teclea lo que recuerda o lo que alcanzó a ver. La aguja se limpia como se limpian
     * las placas al guardarlas, así que da igual escribirla con guiones, espacios o en minúscula.
     *
     * @return Collection<int, object>
     */
    public function historialDePlaca(string $aguja, int $limite = 50): Collection
    {
        $aguja = mb_strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $aguja) ?? '');

        if ($aguja === '') {
            return collect();
        }

        return VehiculoFijo::query()
            ->with(['puesto', 'usuario', 'salidaUsuario'])
            ->where('placa', 'like', '%'.$aguja.'%')
            // Por id además de por hora: dos estadías del mismo vehículo pueden empezar en el
            // mismo segundo, y ahí la hora empata pero el id no.
            ->orderByDesc('entro_en')
            ->orderByDesc('id')
            ->limit($limite)
            ->get()
            ->map(fn (VehiculoFijo $e) => (object) [
                'placa' => $e->placa,
                'tipo_vehiculo' => $e->tipo_vehiculo,
                'marca' => $e->marca,
                'color' => $e->color,
                'puesto' => $e->puesto?->codigo,
                'entro_en' => CarbonImmutable::parse($e->entro_en),
                'entroCon' => $e->conductor_nombre,
                'entroPor' => $e->usuario?->nombre ?? $e->usuario?->usuario,
                // Sigue dentro mientras no tenga salida: la estadía está abierta.
                'salio_en' => $e->salio_en === null ? null : CarbonImmutable::parse($e->salio_en),
                'salioCon' => $e->salida_conductor_nombre,
                'salioPor' => $e->salidaUsuario?->nombre ?? $e->salidaUsuario?->usuario,
                'dentro' => $e->salio_en === null,
            ]);
    }

    /**
     * Quién pudo llevarse este vehículo: para elegir en vez de teclear una cédula a ciegas.
     *
     * Primero el que lo metió, que es quien se lo lleva casi siempre. Después la gente que ya
     * marcó su salida hoy: si alguien salió del edificio y este carro sigue aquí, es justo el
     * candidato —y es el caso que deja estadías abiertas, porque quien se va en un vehículo no
     * siempre pasa a decirlo—. Los más recientes primero.
     *
     * @return Collection<int, Persona>
     */
    public function quienesPudieronLlevarselo(VehiculoFijo $estadia, int $limite = 20): Collection
    {
        $candidatos = collect();

        if ($estadia->conductor) {
            $candidatos->push($estadia->conductor);
        }

        // Los que hoy marcaron salida, del más reciente al más antiguo. Se mira el ÚLTIMO
        // movimiento de cada quien: quien salió y volvió a entrar está dentro, no se fue.
        $salieron = Movimiento::ultimoDeCadaPersona()
            ->where('movimientos.tipo', Movimiento::SALIDA)
            ->where('movimientos.ocurrio_en', '>=', CarbonImmutable::today())
            ->orderByDesc('movimientos.ocurrio_en')
            ->limit($limite)
            ->pluck('movimientos.persona_id');

        $personas = Persona::query()->whereIn('id', $salieron)->get()->keyBy('id');

        foreach ($salieron as $id) {
            if (isset($personas[$id])) {
                $candidatos->push($personas[$id]);
            }
        }

        return $candidatos->unique('id')->values();
    }

    /** Cuánto lleva dentro, dicho corto: «3 h 20 min», «45 min». */
    public function tiempoDentro(string $ocurrioEn): string
    {
        $minutos = (int) $this->desde($ocurrioEn)->diffInMinutes(CarbonImmutable::now());
        $horas = intdiv($minutos, 60);
        $resto = $minutos % 60;

        return match (true) {
            $horas > 0 && $resto > 0 => $horas.' h '.$resto.' min',
            $horas > 0 => $horas.' h',
            default => max(0, $resto).' min',
        };
    }
}
