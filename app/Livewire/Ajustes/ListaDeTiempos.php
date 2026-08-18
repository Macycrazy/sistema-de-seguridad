<?php

namespace App\Livewire\Ajustes;

use App\Services\Auditoria\Auditoria;
use App\Services\ReglasDeTiempo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * La pantalla del administrador para las reglas de tiempo del marcaje.
 *
 * Los plazos —entre entradas, entre la entrada y su salida, el antiduplicado— eran constantes.
 * Aquí se ajustan y valen desde el siguiente marcaje, sin volver a desplegar. No decide nada: se
 * lo pregunta todo a ReglasDeTiempo, donde se valida contra los límites de cada regla.
 */
class ListaDeTiempos extends Component
{
    /** clave => valor que se está editando. */
    public array $valores = [];

    public string $aviso = '';

    protected ReglasDeTiempo $servicio;

    public function boot(): void
    {
        Gate::authorize('gestionar-ajustes');

        $this->servicio = app(ReglasDeTiempo::class);
    }

    public function mount(): void
    {
        foreach ($this->servicio->todas() as $regla) {
            $this->valores[$regla['clave']] = $regla['valor'];
        }
    }

    #[Computed]
    public function reglas(): Collection
    {
        return $this->servicio->todas();
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
            unset($this->reglas);
            app(Auditoria::class)->cambioReglas();
            $this->aviso = 'Reglas guardadas. Valen desde el próximo marcaje.';
        }
    }

    public function render()
    {
        return view('livewire.ajustes.lista-de-tiempos');
    }
}
