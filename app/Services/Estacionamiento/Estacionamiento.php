<?php

namespace App\Services\Estacionamiento;

use App\Models\Movimiento;
use App\Models\Puesto;
use App\Services\Alertas\UmbralesDeAlerta;
use App\Services\DatosVehiculo;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Qué hay en el estacionamiento ahora mismo, a partir de lo que el marcaje ya guarda.
 *
 * No hace falta una tabla nueva ni tocar la puerta: cada asiento congela el vehículo con el que
 * se entró (tipo, marca, modelo, color, placa). Un vehículo está dentro si su dueño está dentro
 * —su último movimiento fue una entrada— y esa entrada traía vehículo. Cuando esa persona marca
 * la salida, su vehículo deja de contar.
 *
 * Es para el guardia del portón: ver cuántos vehículos hay, de qué tipo, y con qué placa.
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
        // El último movimiento de cada persona, y de ahí solo los que están dentro (entrada) y
        // traían vehículo. El «último de cada persona» vive en el modelo y vale en las dos bases;
        // aquí iba antes un «distinct on», que es solo de PostgreSQL.
        return Movimiento::ultimoDeCadaPersona()
            ->join('personas', 'personas.id', '=', 'movimientos.persona_id')
            ->leftJoin('puestos', 'puestos.id', '=', 'movimientos.puesto_id')
            ->where('movimientos.tipo', Movimiento::ENTRADA)
            ->whereNotNull('movimientos.tipo_vehiculo')
            ->orderByDesc('movimientos.ocurrio_en')
            ->get([
                'movimientos.persona_id', 'movimientos.tipo_vehiculo', 'movimientos.marca',
                'movimientos.modelo', 'movimientos.color', 'movimientos.placa',
                'movimientos.ocurrio_en', 'movimientos.puesto_id', 'personas.nombre', 'personas.cedula',
                'puestos.codigo as puesto',
            ])
            ->map(function ($fila) {
                // El mismo objeto de datos que usa la puerta, para que la placa y la descripción se
                // lean igual en las dos pantallas.
                $fila->vehiculo = DatosVehiculo::desdeModelo($fila);

                return $fila;
            });
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
        // Ocupan puesto tanto los vehículos de personas que están dentro como los fijos (empresa o
        // los que ya estaban) que siguen anotados en la bitácora.
        $porPersonas = $this->vehiculosDentro()->pluck('puesto_id')->filter();
        $porFijos = app(VehiculosFijos::class)->puestosOcupados();

        return $porPersonas->merge($porFijos)->unique()->values();
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
    public function asignarPuesto(int $personaId, ?int $puestoId): void
    {
        $entrada = Movimiento::ultimoDeCadaPersona()
            ->where('movimientos.persona_id', $personaId)
            ->where('movimientos.tipo', Movimiento::ENTRADA)
            ->whereNotNull('movimientos.tipo_vehiculo')
            ->first(['movimientos.id', 'movimientos.tipo_vehiculo', 'movimientos.puesto_id']);

        if (! $entrada) {
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

            if (! $puesto->admite($entrada->tipo_vehiculo)) {
                throw ValidationException::withMessages([
                    'puesto' => 'Ese puesto no admite este tipo de vehículo.',
                ]);
            }

            // Ocupado por OTRO: si ya es de este mismo vehículo, reasignarlo al mismo no falla.
            $esElMismo = (int) $entrada->puesto_id === $puestoId;

            if (! $esElMismo && $this->puestosOcupados()->contains($puestoId)) {
                throw ValidationException::withMessages([
                    'puesto' => 'Ese puesto ya está ocupado por otro vehículo.',
                ]);
            }
        }

        Movimiento::whereKey($entrada->id)->update(['puesto_id' => $puestoId]);
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
        return Movimiento::query()
            ->join('personas', 'personas.id', '=', 'movimientos.persona_id')
            ->whereNotNull('movimientos.tipo_vehiculo')
            ->whereBetween('movimientos.ocurrio_en', [$fecha->startOfDay(), $fecha->endOfDay()])
            ->orderByDesc('movimientos.ocurrio_en')
            ->orderByDesc('movimientos.id')
            ->get([
                'movimientos.tipo as sentido', 'movimientos.tipo_vehiculo', 'movimientos.marca',
                'movimientos.modelo', 'movimientos.color', 'movimientos.placa',
                'movimientos.ocurrio_en', 'personas.nombre',
            ])
            ->map(function ($fila) {
                $fila->vehiculo = DatosVehiculo::desdeModelo($fila);
                $fila->esEntrada = $fila->sentido === Movimiento::ENTRADA;

                return $fila;
            });
    }

    /** «Desde cuándo» está un vehículo, para la lista. */
    public function desde(string $ocurrioEn): CarbonImmutable
    {
        return CarbonImmutable::parse($ocurrioEn);
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
