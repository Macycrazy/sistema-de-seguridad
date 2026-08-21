<?php

namespace App\Livewire\Edificio;

use App\Models\Oficina;
use App\Models\Persona;
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

    /** La gerencia que ocupa este piso/oficina. Al asignar el piso a un trabajador se ofrecen estas. */
    public string $gerencia = '';

    public string $aviso = '';

    protected CatalogoDelEdificio $catalogo;

    public function boot(): void
    {
        // Para ENTRAR basta con ver; lo que cambia datos exige «gestionar» aparte.
        Gate::authorize('ver-edificio');

        $this->catalogo = app(CatalogoDelEdificio::class);
    }

    #[Computed]
    public function oficinas(): Collection
    {
        return $this->catalogo->todas();
    }

    /**
     * Las gerencias que ya hay —entre los trabajadores y entre las oficinas— para sugerirlas al
     * asociar, sin obligar: se puede escribir una nueva.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function gerencias(): array
    {
        $deTrabajadores = Persona::query()
            ->where('tipo', Persona::TRABAJADOR)
            ->whereNotNull('dependencia')->where('dependencia', '!=', '')
            ->distinct()->pluck('dependencia');

        $deOficinas = Oficina::query()
            ->whereNotNull('gerencia')->where('gerencia', '!=', '')
            ->distinct()->pluck('gerencia');

        return $deTrabajadores->merge($deOficinas)->unique()->sort()->values()->all();
    }

    /** Cambiar es aparte de ver: quien solo puede ver entra, pero no toca nada. */
    protected function exigirGestion(): void
    {
        Gate::authorize('gestionar-edificio');
    }

    public function abrirAlta(): void
    {
        $this->exigirGestion();

        $this->reset('codigo', 'nombre', 'gerencia', 'editando', 'aviso');
        $this->resetValidation();
        $this->creando = true;
    }

    public function editar(int $id): void
    {
        $this->exigirGestion();

        $oficina = Oficina::findOrFail($id);
        $this->editando = $oficina->id;
        $this->codigo = $oficina->codigo;
        $this->nombre = (string) $oficina->nombre;
        $this->gerencia = (string) $oficina->gerencia;
        $this->resetValidation();
        $this->creando = true;
    }

    public function cancelar(): void
    {
        $this->reset('codigo', 'nombre', 'gerencia', 'editando');
        $this->resetValidation();
        $this->creando = false;
    }

    public function guardar(): void
    {
        $this->exigirGestion();

        // Al editar se conserva su orden; al crear, va al final (lo decide el servicio).
        $orden = $this->editando ? Oficina::find($this->editando)?->orden : null;

        $oficina = $this->catalogo->guardar($this->codigo, $this->nombre, $orden, $this->gerencia);

        $this->creando = false;
        $this->reset('codigo', 'nombre', 'gerencia', 'editando');
        $this->aviso = $oficina->wasRecentlyCreated ? 'Oficina agregada.' : 'Oficina actualizada.';
        app(Auditoria::class)->cambioOficinas(($oficina->wasRecentlyCreated ? 'agregó' : 'renombró').' '.$oficina->codigo);
    }

    public function eliminar(int $id): void
    {
        $this->exigirGestion();

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
