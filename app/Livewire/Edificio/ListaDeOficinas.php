<?php

namespace App\Livewire\Edificio;

use App\Models\Oficina;
use App\Services\Auditoria\Auditoria;
use App\Services\Edificio\CatalogoDelEdificio;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * La pantalla del administrador para el catálogo de oficinas del edificio.
 *
 * Es la lista que la puerta ofrece al marcar el piso de un invitado. No decide nada: se lo pregunta
 * todo a CatalogoDelEdificio, donde se valida en el servidor.
 */
class ListaDeOficinas extends Component
{
    public bool $creando = false;

    /** Cuando se está editando una existente, su id; null cuando es una nueva. */
    public ?int $editando = null;

    public string $codigo = '';

    public string $nombre = '';

    public string $aviso = '';

    protected CatalogoDelEdificio $catalogo;

    public function boot(): void
    {
        Gate::authorize('gestionar-edificio');

        $this->catalogo = app(CatalogoDelEdificio::class);
    }

    #[Computed]
    public function oficinas(): Collection
    {
        return $this->catalogo->todas();
    }

    public function abrirAlta(): void
    {
        $this->reset('codigo', 'nombre', 'editando', 'aviso');
        $this->resetValidation();
        $this->creando = true;
    }

    public function editar(int $id): void
    {
        $oficina = Oficina::findOrFail($id);
        $this->editando = $oficina->id;
        $this->codigo = $oficina->codigo;
        $this->nombre = (string) $oficina->nombre;
        $this->resetValidation();
        $this->creando = true;
    }

    public function cancelar(): void
    {
        $this->reset('codigo', 'nombre', 'editando');
        $this->resetValidation();
        $this->creando = false;
    }

    public function guardar(): void
    {
        // Al editar se conserva su orden; al crear, va al final (lo decide el servicio).
        $orden = $this->editando ? Oficina::find($this->editando)?->orden : null;

        $oficina = $this->catalogo->guardar($this->codigo, $this->nombre, $orden);

        $this->creando = false;
        $this->reset('codigo', 'nombre', 'editando');
        $this->aviso = $oficina->wasRecentlyCreated ? 'Oficina agregada.' : 'Oficina actualizada.';
        app(Auditoria::class)->cambioOficinas(($oficina->wasRecentlyCreated ? 'agregó' : 'renombró').' '.$oficina->codigo);
    }

    public function eliminar(int $id): void
    {
        $oficina = Oficina::findOrFail($id);
        $this->catalogo->eliminar($oficina);
        app(Auditoria::class)->cambioOficinas('quitó '.$oficina->codigo);
        $this->aviso = 'Oficina quitada del catálogo.';
    }

    public function render()
    {
        return view('livewire.edificio.lista-de-oficinas');
    }
}
