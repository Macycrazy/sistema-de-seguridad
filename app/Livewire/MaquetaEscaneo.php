<?php

namespace App\Livewire;

use App\Models\Persona;
use App\Services\Marcaje;
use Livewire\Component;

/**
 * MAQUETA · escanear la cédula con la cámara del teléfono.
 *
 * Sirve para enseñar la idea y discutirla, no para usar en la puerta. Lo que aquí se simula —leer
 * los dígitos de una cédula desde la imagen— es lo único que falta por resolver de verdad; todo lo
 * demás (buscar a la persona, proponer entrada o salida, registrar) ya funciona y es el mismo
 * servicio Marcaje que usa la pantalla real.
 *
 * Por eso la maqueta NO registra movimientos: enseña el camino hasta el botón y ahí se detiene.
 * Así nadie la confunde con el sistema ni ensucia el registro probando.
 */
class MaquetaEscaneo extends Component
{
    /** La cédula que «leyó» la cámara. */
    public string $cedula = '';

    public ?int $personaId = null;

    /** Se enciende cuando la cédula leída no está en el sistema. */
    public bool $desconocida = false;

    public function persona(): ?Persona
    {
        return $this->personaId ? Persona::find($this->personaId) : null;
    }

    public function sugerido(): ?string
    {
        $persona = $this->persona();

        return $persona ? app(Marcaje::class)->movimientoSugerido($persona) : null;
    }

    /**
     * Lo llama el navegador cuando la «cámara» termina de leer.
     *
     * De aquí en adelante es exactamente lo que hace la pantalla real: se le pregunta al servicio
     * quién es esa cédula.
     */
    public function leida(string $cedula): void
    {
        $this->cedula = $cedula;
        $this->desconocida = false;
        $this->personaId = null;

        $persona = app(Marcaje::class)->buscarPorCedula($cedula);

        if (! $persona) {
            $this->desconocida = true;

            return;
        }

        $this->personaId = $persona->id;
    }

    public function otraVez(): void
    {
        $this->reset(['cedula', 'personaId', 'desconocida']);
    }

    public function render()
    {
        return view('livewire.maqueta-escaneo');
    }
}
