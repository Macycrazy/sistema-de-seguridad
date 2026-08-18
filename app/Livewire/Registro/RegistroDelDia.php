<?php

namespace App\Livewire\Registro;

use App\Auditoria\Accion;
use App\Exports\MovimientosDelDia;
use App\Services\Rastro;
use App\Services\Registro\Ente;
use App\Services\Registro\FuenteDelRegistro;
use App\Services\Registro\Movimiento;
use App\Services\Registro\Persona;
use App\Services\Registro\TipoDePersona;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
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

    /**
     * El permiso se revisa aquí además de en la ruta.
     *
     * Va en «boot» y no en «mount» a propósito: «mount» corre una sola vez, en la primera carga.
     * Livewire manda cada acción posterior a su propia ruta, y ahí el componente se rehidrata sin
     * volver a montarse — un permiso comprobado solo al montar dejaría todas las acciones
     * siguientes sin revisar. «boot» corre en todas.
     */
    public function boot(): void
    {
        Gate::authorize('ver-registro');
    }

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

    /**
     * El campo busca solo, con espera de 300 ms, así que esto salta en cada pausa del tecleo.
     * Rastro agrupa las repeticiones de la misma búsqueda; ver Accion::seRepiteSola().
     */
    public function updatedBusqueda(): void
    {
        $texto = trim($this->busqueda);

        if ($texto === '') {
            return;
        }

        app(Rastro::class)->deja(Accion::REGISTRO_BUSQUEDA, detalle: $texto);
    }

    public function abrirPanel(string $personaId): void
    {
        $this->personaEnPanel = $personaId;
        $this->busqueda = '';

        // Abrir el histórico de alguien es ver dónde estuvo y a qué hora, que es lo más delicado
        // que guarda el sistema. Queda anotado con nombre y apellido.
        $persona = $this->fuente()->persona($personaId);

        app(Rastro::class)->deja(
            Accion::REGISTRO_HISTORICO,
            detalle: $persona?->nombre ?? "persona {$personaId}",
        );
    }

    public function cerrarPanel(): void
    {
        $this->personaEnPanel = null;
    }

    public function exportar(): BinaryFileResponse
    {
        // Su propio permiso, aparte de «ver-registro»: sacar el día entero a un archivo que se
        // lleva en un pendrive no es lo mismo que mirarlo en pantalla.
        Gate::authorize('exportar-registro');

        // Se lleva lo que está viendo, no todo el histórico.
        $archivo = 'registro-'.$this->diaElegido()->toDateString().'.xlsx';

        // Un archivo que sale del sistema y se va en un pendrive. Se anota con el día y los
        // filtros, porque lo que se llevó depende de ellos.
        app(Rastro::class)->deja(Accion::REGISTRO_EXPORTADO, detalle: implode(' · ', array_filter([
            $this->diaElegido()->toDateString(),
            $this->tipo !== '' ? "tipo: {$this->tipo}" : null,
            $this->ente !== '' ? "ente: {$this->ente}" : null,
        ])));

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
