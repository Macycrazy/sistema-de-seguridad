<?php

namespace App\Livewire\Estacionamiento;

use App\Services\DatosVehiculo;
use App\Services\Estacionamiento\Estacionamiento;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * El estacionamiento visto desde el portón: qué vehículos hay dentro ahora.
 *
 * Es para el guardia, así que no pide permiso propio —como la pantalla de marcar—: cualquiera con
 * sesión la ve. Solo lee lo que el marcaje ya guarda; no marca nada. Un «Actualizar» explícito
 * recalcula, sin sondeo automático.
 */
class Panel extends Component
{
    /** Buscar un vehículo por su placa: «¿está el carro ABC123?», «¿de quién es este que estorba?». */
    public string $busqueda = '';

    /** Si se muestra el registro del día (entradas y salidas), aparte de lo que hay dentro. */
    public bool $verHistorial = false;

    /** Todo lo que hay dentro ahora. Se calcula una vez por render y de aquí sale lo demás. */
    #[Computed]
    public function dentro(): Collection
    {
        return app(Estacionamiento::class)->vehiculosDentro();
    }

    /** Lo que se muestra: todo, o solo lo que coincide con la placa buscada. */
    #[Computed]
    public function vehiculos(): Collection
    {
        $aguja = mb_strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $this->busqueda) ?? '');

        if ($aguja === '') {
            return $this->dentro();
        }

        return $this->dentro()
            ->filter(fn ($v) => str_contains(mb_strtoupper((string) $v->placa), $aguja))
            ->values();
    }

    /**
     * El cupo por bucket: cuántos dentro, el aforo, cuántos libres y si está lleno. Los conteos
     * salen de lo que hay dentro (sin filtrar por la búsqueda).
     *
     * @return array{total:array, carro:array, moto:array}
     */
    #[Computed]
    public function resumen(): array
    {
        $aforos = app(Estacionamiento::class)->aforos();
        $carro = $this->dentro()->where('tipo_vehiculo', DatosVehiculo::CARRO)->count();
        $moto = $this->dentro()->where('tipo_vehiculo', DatosVehiculo::MOTO)->count();

        return [
            'total' => $this->cupo($carro + $moto, $aforos['total']),
            'carro' => $this->cupo($carro, $aforos['carro']),
            'moto' => $this->cupo($moto, $aforos['moto']),
        ];
    }

    /** El registro de vehículos del día: entradas y salidas. Solo si se pidió verlo. */
    #[Computed]
    public function historial(): Collection
    {
        return app(Estacionamiento::class)->delDia(CarbonImmutable::today());
    }

    /** Los que pernoctan: siguen dentro y entraron antes de hoy. */
    #[Computed]
    public function pernoctan(): Collection
    {
        return app(Estacionamiento::class)->pernoctan();
    }

    public function actualizar(): void
    {
        unset($this->dentro, $this->vehiculos, $this->resumen, $this->historial, $this->pernoctan);
    }

    public function render()
    {
        return view('livewire.estacionamiento.panel');
    }

    /** @return array{dentro:int, aforo:int, libres:?int, lleno:bool} */
    private function cupo(int $dentro, int $aforo): array
    {
        return [
            'dentro' => $dentro,
            'aforo' => $aforo,
            'libres' => $aforo > 0 ? max(0, $aforo - $dentro) : null,
            'lleno' => $aforo > 0 && $dentro >= $aforo,
        ];
    }
}
