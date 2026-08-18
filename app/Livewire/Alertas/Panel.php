<?php

namespace App\Livewire\Alertas;

use App\Services\Alertas\Alerta;
use App\Services\Alertas\Alertas;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * La pantalla de alertas: lo que ahora mismo merece que alguien mire.
 *
 * Solo lee. Comparte el permiso «ver-registro» con el registro y los reportes —es la misma
 * información, mirada por lo que se sale de lo normal—, así que no suma permiso ni casilla nueva.
 *
 * No se refresca sola: un «Actualizar» explícito recalcula. Poner un sondeo automático es una
 * decisión de operación (cada cuánto, a costa de qué carga) que no se toma desde el código.
 */
class Panel extends Component
{
    public function boot(): void
    {
        Gate::authorize('ver-registro');
    }

    /** @return Collection<int, Alerta> */
    #[Computed]
    public function alertas(): Collection
    {
        return app(Alertas::class)->activas();
    }

    public function actualizar(): void
    {
        unset($this->alertas);
    }

    public function render()
    {
        return view('livewire.alertas.panel');
    }
}
