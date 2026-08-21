<?php

namespace App\Livewire\Estacionamiento;

use App\Models\Puesto;
use App\Services\Auditoria\Auditoria;
use App\Services\Estacionamiento\CatalogoDePuestos;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * La pantalla del administrador para el catálogo de puestos del estacionamiento.
 *
 * No decide nada: se lo pregunta todo a CatalogoDePuestos, donde se valida en el servidor. Un
 * puesto no se borra a la ligera —se puede desactivar—, pero borrarlo existe porque el histórico
 * no se cae (la FK es «nullOnDelete»).
 */
class ListaDePuestos extends Component
{
    public bool $creando = false;

    /** Cuando se edita uno existente, su id; null si es nuevo. */
    public ?int $editando = null;

    public string $codigo = '';

    public string $tipo = '';

    public string $zona = '';

    public string $aviso = '';

    protected CatalogoDePuestos $catalogo;

    public function boot(): void
    {
        // Para ENTRAR basta con ver; lo que cambia datos exige «gestionar» aparte.
        Gate::authorize('ver-puestos');

        $this->catalogo = app(CatalogoDePuestos::class);
    }

    /** @return Collection<int, Puesto> */
    #[Computed]
    public function puestos(): Collection
    {
        return $this->catalogo->todos();
    }

    /** @return array<string, string> */
    #[Computed]
    public function tipos(): array
    {
        return CatalogoDePuestos::TIPOS;
    }

    /** Cambiar es aparte de ver: quien solo puede ver entra, pero no toca nada. */
    protected function exigirGestion(): void
    {
        Gate::authorize('gestionar-puestos');
    }

    public function abrirAlta(): void
    {
        $this->exigirGestion();

        $this->reset('codigo', 'tipo', 'zona', 'editando', 'aviso');
        $this->resetValidation();
        $this->creando = true;
    }

    public function editar(int $id): void
    {
        $this->exigirGestion();

        $puesto = Puesto::findOrFail($id);
        $this->editando = $puesto->id;
        $this->codigo = $puesto->codigo;
        $this->tipo = (string) $puesto->tipo;
        $this->zona = (string) $puesto->zona;
        $this->resetValidation();
        $this->creando = true;
    }

    public function cancelar(): void
    {
        $this->reset('codigo', 'tipo', 'zona', 'editando');
        $this->resetValidation();
        $this->creando = false;
    }

    public function guardar(): void
    {
        $this->exigirGestion();

        $orden = $this->editando ? Puesto::find($this->editando)?->orden : null;

        try {
            $puesto = $this->catalogo->guardar($this->codigo, $this->tipo, $this->zona, $orden);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        $this->creando = false;
        $this->reset('codigo', 'tipo', 'zona', 'editando');
        $this->aviso = $puesto->wasRecentlyCreated ? 'Puesto agregado.' : 'Puesto actualizado.';
        app(Auditoria::class)->cambioOficinas(($puesto->wasRecentlyCreated ? 'agregó' : 'editó').' el puesto '.$puesto->codigo);
    }

    public function activar(int $id, bool $activo): void
    {
        $this->exigirGestion();

        $this->catalogo->activar(Puesto::findOrFail($id), $activo);
        $this->aviso = $activo ? 'Puesto habilitado.' : 'Puesto deshabilitado.';
    }

    public function eliminar(int $id): void
    {
        $this->exigirGestion();

        $puesto = Puesto::findOrFail($id);
        $this->catalogo->eliminar($puesto);
        app(Auditoria::class)->cambioOficinas('quitó el puesto '.$puesto->codigo);
        $this->aviso = 'Puesto quitado del catálogo.';
    }

    public function render()
    {
        return view('livewire.estacionamiento.lista-de-puestos');
    }
}
