<?php

namespace App\Livewire\Ajustes;

use App\Services\Alertas\UmbralesDeAlerta;
use App\Services\Auditoria\Auditoria;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * La parte de Ajustes para los umbrales de las alertas.
 *
 * Gemela de ListaDeTiempos: los umbrales —cuántas horas dentro avisan, qué aforo dispara el
 * aviso— eran constantes; aquí se ajustan y valen en la siguiente lectura de alertas. No decide
 * nada: se lo pregunta todo a UmbralesDeAlerta, donde se valida contra los límites.
 */
class ListaDeUmbrales extends Component
{
    /** clave => valor que se está editando. */
    public array $valores = [];

    public string $aviso = '';

    protected UmbralesDeAlerta $servicio;

    public function boot(): void
    {
        Gate::authorize('gestionar-ajustes');

        $this->servicio = app(UmbralesDeAlerta::class);
    }

    public function mount(): void
    {
        foreach ($this->servicio->todos() as $umbral) {
            $this->valores[$umbral['clave']] = $umbral['valor'];
        }
    }

    #[Computed]
    public function umbrales(): Collection
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
            unset($this->umbrales);
            app(Auditoria::class)->cambioReglas();
            $this->aviso = 'Umbrales guardados. Valen desde ya.';
        }
    }

    public function render()
    {
        return view('livewire.ajustes.lista-de-umbrales');
    }
}
