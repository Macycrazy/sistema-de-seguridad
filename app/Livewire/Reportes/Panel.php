<?php

namespace App\Livewire\Reportes;

use App\Models\Persona;
use App\Services\Reportes\Reportes;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * El mirador del registro: las cuentas de un tramo de fechas.
 *
 * Solo lee y solo resume; no cambia nada. Comparte el permiso «ver-registro» con la pantalla del
 * registro a propósito —quien puede ver el detalle día a día puede ver su resumen—, así que no
 * suma un permiso nuevo ni una casilla más en la matriz de roles.
 *
 * Las fechas van en la URL para poder compartir o recargar un reporte tal cual se dejó.
 */
class Panel extends Component
{
    #[Url]
    public string $desde = '';

    #[Url]
    public string $hasta = '';

    /**
     * El permiso en «boot» y no en «mount»: cada acción de Livewire rehidrata el componente sin
     * volver a montarlo, y a quien le quiten el permiso con la pantalla abierta se le corta aquí.
     */
    public function boot(): void
    {
        Gate::authorize('ver-registro');
    }

    public function mount(): void
    {
        if ($this->hasta === '') {
            $this->hasta = CarbonImmutable::today()->toDateString();
        }

        if ($this->desde === '') {
            $this->desde = CarbonImmutable::today()->subDays(29)->toDateString();
        }
    }

    /** Atajos de tramo: la mayoría de los reportes son «los últimos N días» o «este mes». */
    public function ultimos(int $dias): void
    {
        $this->hasta = CarbonImmutable::today()->toDateString();
        $this->desde = CarbonImmutable::today()->subDays($dias - 1)->toDateString();
    }

    public function esteMes(): void
    {
        $this->desde = CarbonImmutable::today()->startOfMonth()->toDateString();
        $this->hasta = CarbonImmutable::today()->toDateString();
    }

    /**
     * El tramo que se está mirando, ya saneado: si las fechas llegan al revés se enderezan, si
     * traen basura se cae a hoy, y si el tramo es más largo del máximo se recorta al final. Todo
     * el resto de la pantalla pregunta por aquí, nunca por las propiedades crudas.
     *
     * @return array{desde:CarbonImmutable, hasta:CarbonImmutable}
     */
    #[Computed]
    public function tramo(): array
    {
        $desde = $this->fecha($this->desde, CarbonImmutable::today()->subDays(29));
        $hasta = $this->fecha($this->hasta, CarbonImmutable::today());

        if ($desde->greaterThan($hasta)) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        // El tramo se recorta desde el inicio, conservando el final: «demasiados días» casi siempre
        // es un «desde» tecleado de más, y lo que interesa es lo reciente.
        if ($desde->diffInDays($hasta) + 1 > Reportes::MAXIMO_DIAS) {
            $desde = $hasta->subDays(Reportes::MAXIMO_DIAS - 1);
        }

        return ['desde' => $desde->startOfDay(), 'hasta' => $hasta->startOfDay()];
    }

    #[Computed]
    public function diasDelTramo(): int
    {
        return (int) $this->tramo()['desde']->diffInDays($this->tramo()['hasta']) + 1;
    }

    #[Computed]
    public function resumen(): array
    {
        return app(Reportes::class)->resumen($this->tramo()['desde'], $this->tramo()['hasta']);
    }

    /** @return Collection<int, array{fecha:CarbonImmutable, entradas:int}> */
    #[Computed]
    public function porDia(): Collection
    {
        return app(Reportes::class)->porDia($this->tramo()['desde'], $this->tramo()['hasta']);
    }

    /** @return array<int, int> */
    #[Computed]
    public function porFranja(): array
    {
        return app(Reportes::class)->porFranja($this->tramo()['desde'], $this->tramo()['hasta']);
    }

    /** @return array{trabajador:int, invitado:int} */
    #[Computed]
    public function porTipo(): array
    {
        return app(Reportes::class)->porTipo($this->tramo()['desde'], $this->tramo()['hasta']);
    }

    /** @return Collection<int, array{persona:?Persona, visitas:int}> */
    #[Computed]
    public function masFrecuentes(): Collection
    {
        return app(Reportes::class)->masFrecuentes($this->tramo()['desde'], $this->tramo()['hasta']);
    }

    /** @return Collection<int, array{unidad:string, entradas:int}> */
    #[Computed]
    public function porDepartamento(): Collection
    {
        return app(Reportes::class)->porDepartamento($this->tramo()['desde'], $this->tramo()['hasta']);
    }

    /** @return array{carro:int, moto:int, total:int, conConductor:int} */
    #[Computed]
    public function vehiculos(): array
    {
        return app(Reportes::class)->vehiculosQueEntraron($this->tramo()['desde'], $this->tramo()['hasta']);
    }

    /** La franja pico dicha en horas, «8:00 am – 8:59 am». Nula si el tramo está vacío. */
    #[Computed]
    public function franjaPico(): ?string
    {
        $hora = $this->resumen()['franjaPico'];

        if ($hora === null) {
            return null;
        }

        $inicio = CarbonImmutable::today()->setTime($hora, 0);

        return $inicio->format('g:i a').' – '.$inicio->addMinutes(59)->format('g:i a');
    }

    public function render()
    {
        return view('livewire.reportes.panel');
    }

    /** Una fecha de la URL, o el respaldo si viene vacía o ilegible. */
    private function fecha(string $valor, CarbonImmutable $respaldo): CarbonImmutable
    {
        try {
            return trim($valor) === '' ? $respaldo : CarbonImmutable::parse($valor);
        } catch (\Throwable) {
            return $respaldo;
        }
    }
}
