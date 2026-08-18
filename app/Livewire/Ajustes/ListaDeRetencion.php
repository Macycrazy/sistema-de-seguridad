<?php

namespace App\Livewire\Ajustes;

use App\Services\Auditoria\Auditoria;
use App\Services\Retencion\RetencionDeDatos;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * La parte de Ajustes para la política de retención de datos.
 *
 * Solo fija cuánto se guarda; NO borra nada. El borrado lo hace el comando `registro:depurar`, a
 * mano. Aquí en 0 significa «guardar para siempre», que es como viene de fábrica. Gemela de
 * ListaDeTiempos y ListaDeUmbrales; todo pasa por RetencionDeDatos, donde se valida.
 */
class ListaDeRetencion extends Component
{
    public array $valores = [];

    public string $aviso = '';

    protected RetencionDeDatos $servicio;

    public function boot(): void
    {
        Gate::authorize('gestionar-ajustes');

        $this->servicio = app(RetencionDeDatos::class);
    }

    public function mount(): void
    {
        foreach ($this->servicio->todos() as $periodo) {
            $this->valores[$periodo['clave']] = $periodo['valor'];
        }
    }

    #[Computed]
    public function periodos(): Collection
    {
        return $this->servicio->todos();
    }

    public function guardar(): void
    {
        $this->resetValidation();
        $huboError = false;

        foreach ($this->valores as $clave => $valor) {
            try {
                $this->servicio->guardar($clave, (int) $valor);
            } catch (ValidationException $e) {
                $this->addError("valores.$clave", $e->validator->errors()->first());
                $huboError = true;
            }
        }

        if (! $huboError) {
            unset($this->periodos);
            app(Auditoria::class)->cambioReglas();
            $this->aviso = 'Política de retención guardada. El borrado se hace aparte, con el comando de depuración.';
        }
    }

    public function render()
    {
        return view('livewire.ajustes.lista-de-retencion');
    }
}
