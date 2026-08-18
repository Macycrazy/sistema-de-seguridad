<?php

namespace App\Livewire\Estacionamiento;

use App\Services\Estacionamiento\Estacionamiento;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * El estacionamiento visto desde el portón: qué vehículos hay dentro ahora.
 *
 * Es para el guardia, así que no pide permiso propio —como la pantalla de marcar—: cualquiera con
 * sesión la ve. Solo lee lo que el marcaje ya guarda; no marca nada. Un «Actualizar» explícito
 * recalcula, sin sondeo automático.
 */
class Panel extends Component
{
    /** @return Collection<int, object> */
    #[Computed]
    public function vehiculos(): Collection
    {
        return app(Estacionamiento::class)->vehiculosDentro();
    }

    #[Computed]
    public function porTipo(): array
    {
        return app(Estacionamiento::class)->porTipoDentro();
    }

    #[Computed]
    public function aforo(): int
    {
        return app(Estacionamiento::class)->aforo();
    }

    public function actualizar(): void
    {
        unset($this->vehiculos, $this->porTipo);
    }

    public function render()
    {
        return view('livewire.estacionamiento.panel');
    }
}
