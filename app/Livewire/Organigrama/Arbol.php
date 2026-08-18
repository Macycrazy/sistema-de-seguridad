<?php

namespace App\Livewire\Organigrama;

use App\Models\Departamento;
use App\Services\Auditoria\Auditoria;
use App\Services\Organigrama\Organigrama;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * La pantalla del organigrama: dar de alta unidades y colgarlas unas de otras.
 *
 * Es parte de gestionar el personal —quién es de qué unidad—, así que va bajo «gestionar-personal»
 * y no suma permiso nuevo. No decide nada: se lo pregunta todo a Organigrama, donde se valida.
 */
class Arbol extends Component
{
    public bool $creando = false;

    /** Al editar, el id de la unidad; null cuando es nueva. */
    public ?int $editando = null;

    public string $nombre = '';

    public string $codigo = '';

    public string $ente = '';

    /** La unidad madre elegida; vacío = raíz. */
    public ?string $parentId = '';

    public string $aviso = '';

    protected Organigrama $organigrama;

    public function boot(): void
    {
        Gate::authorize('gestionar-personal');

        $this->organigrama = app(Organigrama::class);
    }

    #[Computed]
    public function unidades(): Collection
    {
        return $this->organigrama->enOrden();
    }

    #[Computed]
    public function entes(): array
    {
        return Organigrama::ENTES;
    }

    /** Las madres que se pueden elegir sin hacer un bucle. */
    #[Computed]
    public function madres(): array
    {
        $excluir = $this->editando ? Departamento::find($this->editando) : null;

        return $this->organigrama->posiblesMadres($excluir);
    }

    public function abrirAlta(): void
    {
        $this->reset('nombre', 'codigo', 'ente', 'parentId', 'editando', 'aviso');
        $this->parentId = '';
        $this->resetValidation();
        $this->creando = true;
    }

    public function editar(int $id): void
    {
        $dep = Departamento::findOrFail($id);
        $this->editando = $dep->id;
        $this->nombre = $dep->nombre;
        $this->codigo = (string) $dep->codigo;
        $this->ente = (string) $dep->ente;
        $this->parentId = $dep->parent_id ? (string) $dep->parent_id : '';
        $this->resetValidation();
        $this->creando = true;
    }

    public function cancelar(): void
    {
        $this->reset('nombre', 'codigo', 'ente', 'parentId', 'editando');
        $this->parentId = '';
        $this->resetValidation();
        $this->creando = false;
    }

    public function guardar(): void
    {
        $this->resetValidation();
        $parent = $this->parentId === '' || $this->parentId === null ? null : (int) $this->parentId;

        try {
            if ($this->editando) {
                $dep = Departamento::findOrFail($this->editando);
                $this->organigrama->editar($dep, $this->nombre, $this->ente ?: null, $this->codigo ?: null);
                $this->organigrama->mover($dep, $parent);
                $detalle = 'ajustó '.$dep->nombre;
            } else {
                $dep = $this->organigrama->crear($this->nombre, $this->ente ?: null, $parent, $this->codigo ?: null);
                $detalle = 'agregó '.$dep->nombre;
            }
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        unset($this->unidades, $this->madres);
        app(Auditoria::class)->cambioOrganigrama($detalle);
        $this->aviso = $this->editando ? 'Unidad actualizada.' : 'Unidad agregada.';
        $this->cancelar();
    }

    public function activar(int $id, bool $activo): void
    {
        $dep = Departamento::findOrFail($id);
        $this->organigrama->activar($dep, $activo);
        unset($this->unidades);
        app(Auditoria::class)->cambioOrganigrama(($activo ? 'reactivó ' : 'desactivó ').$dep->nombre);
    }

    public function eliminar(int $id): void
    {
        $dep = Departamento::findOrFail($id);

        try {
            $this->organigrama->eliminar($dep);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        unset($this->unidades, $this->madres);
        app(Auditoria::class)->cambioOrganigrama('quitó '.$dep->nombre);
        $this->aviso = 'Unidad quitada. Su gente vuelve a mostrarse por su texto.';
    }

    public function render()
    {
        return view('livewire.organigrama.arbol');
    }
}
