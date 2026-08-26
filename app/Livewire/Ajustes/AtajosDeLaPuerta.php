<?php

namespace App\Livewire\Ajustes;

use App\Services\Puerta\AjustesDeLaPuerta;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Qué ofrece la puerta además de lo básico: teclear la cédula, escanear el carnet, buscar la cara.
 *
 * Los tres juntos y en un solo sitio a propósito. Estaban repartidos —el carnet en Asociación, la
 * cara en Reconocimiento facial— y eso obligaba a recordar dónde estaba cada uno para responder a
 * una pregunta que siempre es la misma: «¿qué se le ofrece al vigilante?».
 *
 * No se pueden apagar todos: ver AjustesDeLaPuerta::sePuedeApagar().
 */
class AtajosDeLaPuerta extends Component
{
    public bool $cedula = true;

    public bool $escaner = true;

    public bool $rostro = false;

    public string $aviso = '';

    public function boot(): void
    {
        Gate::authorize('ver-ajustes');
    }

    public function mount(): void
    {
        $ajustes = app(AjustesDeLaPuerta::class);

        $this->cedula = $ajustes->tecleoDeCedula();
        $this->escaner = $ajustes->escanerDeCarnet();
        $this->rostro = $ajustes->reconocimientoFacial();
    }

    public function alternar(string $cual): void
    {
        Gate::authorize('gestionar-ajustes');

        $ajustes = app(AjustesDeLaPuerta::class);
        $encendiendo = $this->{$cual};

        // Apagar el último deja la puerta sin forma de marcar a nadie. Se dice y se deshace, en vez
        // de guardarlo y que alguien lo descubra en el turno.
        if (! $encendiendo && ! $ajustes->sePuedeApagar($cual)) {
            $this->{$cual} = true;
            $this->aviso = 'Ese no se puede apagar: la puerta se quedaría sin ninguna forma de marcar. Enciende antes la otra.';

            return;
        }

        match ($cual) {
            'cedula' => $ajustes->activarTecleoDeCedula($encendiendo),
            'escaner' => $ajustes->activarEscanerDeCarnet($encendiendo),
            'rostro' => $ajustes->activarReconocimientoFacial($encendiendo),
        };

        $this->aviso = 'Guardado. La puerta lo usará en cuanto se recargue.';
    }

    public function render()
    {
        return view('livewire.ajustes.atajos-de-la-puerta');
    }
}
