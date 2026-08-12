<?php

namespace App\Livewire;

use App\Models\Movimiento;
use App\Models\Persona;
use App\Services\Marcaje;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * La pantalla que el vigilante tiene abierta todo el turno.
 *
 * El recorrido es siempre el mismo: se teclea una cédula, el sistema dice quién es y propone
 * entrada o salida, se pulsa el botón y la pantalla se limpia sola para el siguiente.
 *
 * Si la cédula no aparece, es un invitado: se pide nombre y a quién viene a ver, y de ahí sigue
 * igual. Si ese invitado vuelve otro día, con teclear la cédula ya salen sus datos.
 *
 * Esta clase no decide nada por su cuenta: todo se lo pregunta al servicio Marcaje, que es donde
 * se valida en el servidor.
 */
class Marcar extends Component
{
    /** Lo único que el vigilante teclea. */
    public string $cedula = '';

    /** La persona encontrada, si ya se buscó. */
    public ?int $personaId = null;

    /** Se enciende cuando la cédula no está en el sistema: hay que dar de alta un invitado. */
    public bool $invitadoNuevo = false;

    /** Los dos campos del formulario de invitado. */
    public string $nombre = '';

    public string $visita = '';

    /** Lo que se le dice al vigilante después de marcar. */
    public string $confirmacion = '';

    /** No es una propiedad de Livewire: al ser protegida no viaja al navegador. */
    protected Marcaje $marcaje;

    public function boot(): void
    {
        // Cada petición de Livewire es un ciclo nuevo, así que el servicio se resuelve en cada uno.
        $this->marcaje = app(Marcaje::class);
    }

    #[Computed]
    public function persona(): ?Persona
    {
        return $this->personaId ? Persona::find($this->personaId) : null;
    }

    /** Cuál de los dos botones va resaltado. */
    #[Computed]
    public function sugerido(): ?string
    {
        $persona = $this->persona();

        return $persona ? $this->marcaje->movimientoSugerido($persona) : null;
    }

    #[Computed]
    public function dentro(): int
    {
        return $this->marcaje->cuantosDentro();
    }

    /**
     * Se dispara al pulsar Enter en el campo de la cédula, que es como llega el carnet del lector.
     */
    public function buscar(): void
    {
        $this->confirmacion = '';
        $this->resetValidation();
        $this->personaId = null;
        $this->invitadoNuevo = false;

        try {
            $cedula = $this->marcaje->exigirCedulaValida($this->cedula);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        $persona = $this->marcaje->buscarPorCedula($cedula);

        if (! $persona) {
            // No está en la lista del personal: es un invitado.
            $this->invitadoNuevo = true;
            $this->nombre = '';
            $this->visita = '';

            return;
        }

        $this->personaId = $persona->id;

        // Un invitado que vuelve ya trae sus datos: se muestran para poder confirmarlos o cambiarlos.
        if ($persona->esInvitado()) {
            $this->visita = (string) $persona->visita;
        }
    }

    /** Da de alta al invitado nuevo y lo deja listo para marcar, sin teclear la cédula otra vez. */
    public function guardarInvitado(): void
    {
        try {
            $persona = $this->marcaje->registrarInvitado($this->cedula, $this->nombre, $this->visita);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        $this->personaId = $persona->id;
        $this->invitadoNuevo = false;
    }

    public function marcarEntrada(): void
    {
        $this->registrar(Movimiento::ENTRADA);
    }

    public function marcarSalida(): void
    {
        $this->registrar(Movimiento::SALIDA);
    }

    /**
     * Deja el asiento y limpia la pantalla. El vigilante no tiene que tocar nada más:
     * queda lista para la siguiente persona.
     */
    protected function registrar(string $tipo): void
    {
        $persona = $this->persona();

        if (! $persona) {
            return;
        }

        try {
            $this->marcaje->registrar(
                persona: $persona,
                tipo: $tipo,
                // La parte 3 pondrá aquí el usuario que tiene la sesión abierta.
                usuarioId: auth()->id(),
                visita: $persona->esInvitado() ? $this->visita : null,
            );
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        $verbo = $tipo === Movimiento::ENTRADA ? 'Entrada' : 'Salida';
        $confirmacion = "{$verbo} registrada · {$persona->nombre}";

        $this->limpiar();
        $this->confirmacion = $confirmacion;
    }

    /** Vuelve al estado inicial: campo vacío y listo para teclear. */
    public function limpiar(): void
    {
        $this->reset(['cedula', 'personaId', 'invitadoNuevo', 'nombre', 'visita', 'confirmacion']);
        $this->resetValidation();
        unset($this->persona, $this->sugerido, $this->dentro);
    }

    public function render()
    {
        return view('livewire.marcar');
    }
}
