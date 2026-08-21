<?php

namespace App\Livewire\Visitas;

use App\Models\VisitaEsperada;
use App\Services\Visitas\VisitasEsperadas;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * La agenda de visitas esperadas: quién se espera, y marcar su llegada.
 *
 * Bajo el permiso «gestionar-visitas» (recepción/supervisión). No marca entradas —eso es la puerta,
 * parte 1—: agenda y da seguimiento. Todo se lo pregunta a VisitasEsperadas, donde se valida.
 */
class Agenda extends Component
{
    /** El día que se está mirando; en la URL para poder compartir o recargar. */
    #[Url]
    public string $fecha = '';

    public bool $creando = false;

    public string $nombre = '';

    public string $cedula = '';

    public string $aQuienVisita = '';

    public string $motivo = '';

    public string $fechaEsperada = '';

    public string $notas = '';

    public string $aviso = '';

    protected VisitasEsperadas $servicio;

    public function boot(): void
    {
        // Para ENTRAR basta con ver; agendar o cancelar exige «gestionar» aparte.
        Gate::authorize('ver-visitas');

        $this->servicio = app(VisitasEsperadas::class);
    }

    public function mount(): void
    {
        if ($this->fecha === '') {
            $this->fecha = CarbonImmutable::today()->toDateString();
        }
    }

    public function diaElegido(): CarbonImmutable
    {
        try {
            return CarbonImmutable::parse($this->fecha)->startOfDay();
        } catch (\Throwable) {
            return CarbonImmutable::today();
        }
    }

    /** @return Collection<int, VisitaEsperada> */
    #[Computed]
    public function visitas(): Collection
    {
        return $this->servicio->delDia($this->diaElegido());
    }

    #[Computed]
    public function esperadasProximas(): int
    {
        return $this->servicio->proximas()->count();
    }

    /** Cambiar la agenda es aparte de verla: quien solo puede ver entra, pero no toca nada. */
    protected function exigirGestion(): void
    {
        Gate::authorize('gestionar-visitas');
    }

    public function abrirAlta(): void
    {
        $this->exigirGestion();

        $this->reset('nombre', 'cedula', 'aQuienVisita', 'motivo', 'notas', 'aviso');
        $this->fechaEsperada = $this->diaElegido()->toDateString();
        $this->resetValidation();
        $this->creando = true;
    }

    public function cancelarAlta(): void
    {
        $this->reset('nombre', 'cedula', 'aQuienVisita', 'motivo', 'notas');
        $this->resetValidation();
        $this->creando = false;
    }

    public function agendar(): void
    {
        $this->exigirGestion();

        $this->resetValidation();

        try {
            $visita = $this->servicio->agendar(
                nombre: $this->nombre,
                cedula: $this->cedula ?: null,
                aQuienVisita: $this->aQuienVisita ?: null,
                motivo: $this->motivo ?: null,
                fechaEsperada: $this->fechaEsperada ?: null,
                notas: $this->notas ?: null,
            );
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        // Salta al día de la visita recién agendada, para verla en la lista.
        $this->fecha = $visita->fecha_esperada->toDateString();
        unset($this->visitas, $this->esperadasProximas);
        $this->cancelarAlta();
        $this->aviso = 'Visita agendada.';
    }

    public function marcarLlegada(int $id): void
    {
        $this->exigirGestion();

        $this->servicio->marcarLlegada(VisitaEsperada::findOrFail($id));
        unset($this->visitas, $this->esperadasProximas);
        $this->aviso = 'Marcada como llegada.';
    }

    public function cancelar(int $id): void
    {
        $this->exigirGestion();

        $this->servicio->cancelar(VisitaEsperada::findOrFail($id));
        unset($this->visitas, $this->esperadasProximas);
        $this->aviso = 'Visita cancelada.';
    }

    public function render()
    {
        return view('livewire.visitas.agenda');
    }
}
