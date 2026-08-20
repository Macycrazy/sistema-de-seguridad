<?php

namespace App\Services\Estacionamiento;

use App\Models\Puesto;
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
            ->with('puesto')
            ->where(fn ($q) => $q->whereBetween('entro_en', [$desde, $hasta])->orWhereBetween('salio_en', [$desde, $hasta]))
            ->get();

        // Cada estadía puede aportar una entrada (si entró hoy) y una salida (si salió hoy).
        $filas = collect();

        foreach ($estadias as $e) {
            $base = fn (bool $esEntrada, $cuando, ?string $conductor) => (object) [
                'esEntrada' => $esEntrada,
                'placa' => $e->placa,
                'tipo_vehiculo' => $e->tipo_vehiculo,
                'marca' => $e->marca,
                'color' => $e->color,
                'puesto' => $e->puesto?->codigo,
                'conductor' => $conductor,
                'ocurrio_en' => CarbonImmutable::parse($cuando),
                'vehiculo' => DatosVehiculo::desde($e->tipo_vehiculo, $e->marca, null, $e->color, $e->placa),
            ];

            if ($e->entro_en >= $desde && $e->entro_en <= $hasta) {
                $filas->push($base(true, $e->entro_en, $e->conductor_nombre));
            }

            if ($e->salio_en !== null && $e->salio_en >= $desde && $e->salio_en <= $hasta) {
                $filas->push($base(false, $e->salio_en, $e->salida_conductor_nombre));
            }
        }

        return $filas->sortByDesc('ocurrio_en')->values();
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
