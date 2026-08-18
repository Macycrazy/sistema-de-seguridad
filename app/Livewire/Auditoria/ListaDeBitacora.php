<?php

namespace App\Livewire\Auditoria;

use App\Models\Bitacora;
use App\Models\User;
use App\Services\Auditoria\Auditoria;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * La pantalla de auditoría: el rastro de quién hizo qué y cuándo.
 *
 * Solo lee. La bitácora es inmutable —se escribe desde App\Services\Auditoria\Auditoria y no se
 * edita ni se borra—, así que aquí no hay ninguna acción que la cambie: filtrar y mirar.
 */
class ListaDeBitacora extends Component
{
    use WithPagination;

    public string $accion = '';

    public string $usuario = '';

    public string $fecha = '';

    public function boot(): void
    {
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

    public function updatedFecha(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function entradas(): LengthAwarePaginator
    {
        return Bitacora::query()
            ->with('usuario')
            ->when($this->accion !== '', fn ($q) => $q->where('accion', $this->accion))
            ->when($this->usuario !== '', fn ($q) => $q->where('usuario_id', $this->usuario))
            ->when($this->fecha !== '', fn ($q) => $q->whereDate('ocurrio_en', $this->fecha))
            ->orderByDesc('ocurrio_en')
            ->orderByDesc('id')
            ->paginate(25);
    }

    /** Las acciones conocidas, para el filtro y para etiquetar cada fila. */
    #[Computed]
    public function acciones(): array
    {
        return Auditoria::ETIQUETAS;
    }

    /** Quiénes han dejado rastro, para el filtro por usuario. */
    #[Computed]
    public function usuarios(): array
    {
        return User::query()->orderBy('nombre')->pluck('nombre', 'id')->all();
    }

    public function render()
    {
        return view('livewire.auditoria.lista-de-bitacora');
    }
}
