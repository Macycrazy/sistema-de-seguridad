<?php

namespace App\Livewire\Auditoria;

use App\Auditoria\Accion;
use App\Models\Auditoria;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * El rastro: quién hizo qué y cuándo.
 *
 * Es de SOLO LECTURA, y no por falta de tiempo: un rastro que se puede editar o borrar desde una
 * pantalla no prueba nada. Aquí no hay ninguna acción que escriba, y el modelo tampoco la ofrece.
 *
 * La pregunta para la que existe es la del README: «quién consultó los datos de una persona y en
 * qué momento». Hoy se responde mirando la columna «Sobre qué», acotando por acción, por usuario o
 * por fechas: no hay filtro que busque dentro del detalle.
 */
class ElRastro extends Component
{
    use WithPagination;

    private const POR_PAGINA = 40;

    /** Los filtros van en la URL: así una consulta se puede compartir o recargar. */
    #[Url]
    public string $accion = '';

    #[Url]
    public string $usuario = '';

    #[Url]
    public string $desde = '';

    #[Url]
    public string $hasta = '';

    public function boot(): void
    {
        // En «boot» y no en «mount»: las acciones posteriores rehidratan el componente sin volver
        // a montarlo, y a quien le quiten el permiso con la pantalla abierta se le corta aquí.
        Gate::authorize('ver-auditoria');
    }

    public function updatedAccion(): void
    {
        $this->resetPage();
    }

    public function updatedUsuario(): void
    {
        $this->resetPage();
    }

    public function updatedDesde(): void
    {
        $this->resetPage();
    }

    public function updatedHasta(): void
    {
        $this->resetPage();
    }

    public function limpiar(): void
    {
        $this->accion = '';
        $this->usuario = '';
        $this->desde = '';
        $this->hasta = '';
        $this->resetPage();
    }

    /** @return LengthAwarePaginator<int, Auditoria> */
    #[Computed]
    public function asientos(): LengthAwarePaginator
    {
        return Auditoria::query()
            ->with(['usuario', 'persona'])
            ->when($this->accionElegida(), fn ($q, $accion) => $q->where('accion', $accion))
            ->when($this->usuario !== '', fn ($q) => $q->where('usuario_id', (int) $this->usuario))
            ->when($this->fecha($this->desde), fn ($q, $dia) => $q->where('ocurrio_en', '>=', $dia->startOfDay()))
            ->when($this->fecha($this->hasta), fn ($q, $dia) => $q->where('ocurrio_en', '<=', $dia->endOfDay()))
            ->masReciente()
            ->paginate(self::POR_PAGINA);
    }

    /**
     * Quién aparece en el rastro, para el selector.
     *
     * Sale de la tabla de usuarios y no de los asientos: así el selector no cambia de contenido
     * según lo que se esté filtrando.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function usuarios(): Collection
    {
        return User::query()->orderBy('nombre')->get(['id', 'nombre', 'usuario']);
    }

    /** @return array<string, string> */
    #[Computed]
    public function acciones(): array
    {
        return collect(Accion::cases())
            ->mapWithKeys(fn (Accion $accion) => [$accion->value => $accion->etiqueta()])
            ->all();
    }

    #[Computed]
    public function hayFiltros(): bool
    {
        return $this->accion !== ''
            || $this->usuario !== ''
            || $this->desde !== ''
            || $this->hasta !== '';
    }

    public function render()
    {
        return view('livewire.auditoria.el-rastro');
    }

    protected function accionElegida(): ?Accion
    {
        return Accion::tryFrom($this->accion);
    }

    /** Las fechas viajan en la URL, así que puede llegar cualquier cosa. Lo ilegible no filtra. */
    protected function fecha(string $valor): ?CarbonImmutable
    {
        if (trim($valor) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($valor);
        } catch (\Throwable) {
            return null;
        }
    }
}
