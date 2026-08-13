<?php

namespace App\Livewire\Registro;

use App\Exports\MovimientosDelDia;
use App\Services\Registro\Ente;
use App\Services\Registro\FuenteDelRegistro;
use App\Services\Registro\Movimiento;
use App\Services\Registro\Persona;
use App\Services\Registro\TipoDePersona;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * La pantalla que reemplaza a la hoja de cálculo.
 *
 * Es de solo lectura a propósito: los movimientos no se editan ni se borran. Un error se
 * corrige con un movimiento nuevo, y eso lo hace la pantalla de marcar (parte 1).
 */
class RegistroDelDia extends Component
{
    use WithPagination;

    private const POR_PAGINA = 25;

    /** Los filtros van en la URL: así el reporte del día se puede compartir o recargar. */
    #[Url]
    public string $fecha = '';

    #[Url]
    public string $tipo = '';

    #[Url]
    public string $ente = '';

    /** La búsqueda no va a la URL: es de usar y tirar, y arrastra datos de una persona. */
    public string $busqueda = '';

    /**
     * A quién se le está viendo el histórico.
     *
     * Se guarda el id de la persona y no su cédula: en el listado real hay gente sin
     * documento registrado, así que la cédula no sirve para identificar a nadie.
     */
    public ?string $personaEnPanel = null;

    public function mount(): void
    {
        if ($this->fecha === '') {
            $this->fecha = CarbonImmutable::today()->toDateString();
        }
    }

    public function updatedFecha(): void
    {
        $this->resetPage();
    }

    public function updatedTipo(): void
    {
        $this->resetPage();
    }

    public function updatedEnte(): void
    {
        $this->resetPage();
    }

    public function verHoy(): void
    {
        $this->fecha = CarbonImmutable::today()->toDateString();
        $this->resetPage();
    }

    public function abrirPanel(string $personaId): void
    {
        $this->personaEnPanel = $personaId;
        $this->busqueda = '';
    }

    public function cerrarPanel(): void
    {
        $this->personaEnPanel = null;
    }

    public function exportar(): BinaryFileResponse
    {
        // Se lleva lo que está viendo, no todo el histórico.
        $archivo = 'registro-'.$this->diaElegido()->toDateString().'.xlsx';

        return Excel::download(new MovimientosDelDia($this->delDia()), $archivo);
    }

    /** El día que se está mirando; si la URL trae basura, hoy. */
    public function diaElegido(): CarbonImmutable
    {
        try {
            return CarbonImmutable::parse($this->fecha)->startOfDay();
        } catch (\Throwable) {
            return CarbonImmutable::today();
        }
    }

    public function esHoy(): bool
    {
        return $this->diaElegido()->isSameDay(CarbonImmutable::today());
    }

    /**
     * La fecha viaja en la URL, así que puede llegar cualquier cosa. Cuando no se
     * entiende se cae a hoy, pero hay que decirlo: mostrar otro día sin avisar es peor
     * que no mostrar nada.
     */
    #[Computed]
    public function fechaIlegible(): bool
    {
        if (trim($this->fecha) === '') {
            return false;
        }

        try {
            CarbonImmutable::parse($this->fecha);

            return false;
        } catch (\Throwable) {
            return true;
        }
    }

    #[Computed]
    public function dentro(): int
    {
        return $this->fuente()->dentroEn($this->diaElegido());
    }

    /** «Dentro ahora» solo es cierto si se está mirando hoy. */
    #[Computed]
    public function leyendaDelContador(): string
    {
        return $this->esHoy() ? 'Dentro ahora' : 'Quedaron dentro';
    }

    #[Computed]
    public function movimientos(): LengthAwarePaginator
    {
        $todos = $this->delDia();
        $pagina = $this->getPage();

        // No hay consulta que paginar: la fuente devuelve una colección en memoria, así
        // que el paginador se arma a mano para poder seguir usando ->links().
        return new LengthAwarePaginator(
            $todos->forPage($pagina, self::POR_PAGINA)->values(),
            $todos->count(),
            self::POR_PAGINA,
            $pagina,
            ['path' => Paginator::resolveCurrentPath(), 'pageName' => 'page'],
        );
    }

    /** @return Collection<int, Persona> */
    #[Computed]
    public function sugerencias(): Collection
    {
        return $this->fuente()->buscarPersonas($this->busqueda);
    }

    #[Computed]
    public function personaDelPanel(): ?Persona
    {
        return $this->personaEnPanel ? $this->fuente()->persona($this->personaEnPanel) : null;
    }

    /** @return Collection<int, Movimiento> */
    #[Computed]
    public function historico(): Collection
    {
        return $this->personaEnPanel
            ? $this->fuente()->historicoDe($this->personaEnPanel)
            : collect();
    }

    public function render()
    {
        return view('livewire.registro.registro-del-dia');
    }

    /** @return Collection<int, Movimiento> */
    private function delDia(): Collection
    {
        return $this->fuente()->movimientosDelDia(
            $this->diaElegido(),
            $this->tipoElegido(),
            $this->enteElegido(),
        );
    }

    private function tipoElegido(): ?TipoDePersona
    {
        return TipoDePersona::tryFrom($this->tipo);
    }

    private function enteElegido(): ?Ente
    {
        return Ente::tryFrom($this->ente);
    }

    private function fuente(): FuenteDelRegistro
    {
        return app(FuenteDelRegistro::class);
    }
}
