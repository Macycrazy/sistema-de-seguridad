<?php

namespace App\Livewire;

use App\Models\Movimiento;
use App\Models\Persona;
use App\Services\Marcaje;
use App\Services\Vehiculo;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * La pantalla que el vigilante tiene abierta todo el turno.
 *
 * El recorrido es siempre el mismo: se teclea una cédula, el sistema dice quién es y propone
 * entrada o salida, se pulsa el botón y la pantalla se limpia sola para el siguiente.
 *
 * Si la cédula no aparece, es un invitado: se pide nombre y motivo de la visita, y de ahí sigue
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

    /** Los dos campos obligatorios del formulario de invitado. */
    public string $nombre = '';

    public string $motivo = '';

    /**
     * El vehículo en el que llega, si llega en uno. Vale igual para un invitado que para un
     * trabajador: el personal también estaciona aquí. Van vacíos cuando entra caminando, que es
     * lo más común: no son obligatorios. Van sueltos y no como un objeto porque cada uno es una
     * casilla de la pantalla y Livewire ata cada casilla a una propiedad.
     *
     * El tipo empieza en «carro» porque siempre hay uno de los dos botones marcado. No significa
     * que haya vehículo: mientras las demás casillas estén vacías, no se guarda nada.
     */
    public string $tipoVehiculo = Vehiculo::CARRO;

    public string $marca = '';

    public string $modelo = '';

    public string $color = '';

    public string $placa = '';

    /**
     * Se enciende al pulsar «Otro vehículo». Mientras esté apagada, a quien ya tiene un vehículo
     * anotado no se le deja cambiar carro por moto: sería un error de tecleo, no un dato nuevo.
     */
    public bool $cambiandoVehiculo = false;

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
     * A qué hora se le podrá volver a marcar la entrada, si es que hay que esperar.
     *
     * Null cuando puede entrar ya. Se muestra en pantalla para que el vigilante sepa hasta
     * cuándo, en vez de pulsar un botón y toparse con un error que no explica nada.
     */
    #[Computed]
    public function esperaHasta(): ?string
    {
        $persona = $this->persona();

        return $persona
            ? $this->marcaje->puedeEntrarDesde($persona)?->format('H:i')
            : null;
    }

    /** Los minutos que tienen que pasar entre dos entradas. Lo decide el servicio. */
    public function minutosEntreEntradas(): int
    {
        return Marcaje::MINUTOS_ENTRE_ENTRADAS;
    }

    /** Cuántos dígitos deja teclear el campo. Lo decide el servicio, no la pantalla. */
    public function maximoDigitos(): int
    {
        return Marcaje::DIGITOS_MAXIMOS;
    }

    /**
     * Se dispara sola al dejar de teclear, sin pulsar nada.
     *
     * Aquí NO se muestran errores de validación: mientras se teclea, una cédula a medias no es un
     * error, es una cédula a medias. Regañar a alguien por el segundo dígito sería absurdo.
     *
     * Por debajo del mínimo de dígitos no se busca nada, y esa es la clave para que el aviso de
     * invitado no salte a media cédula: al teclear «25375258» se pasa por «253752», que no existe
     * en el sistema, pero no se llega a consultar.
     */
    public function updatedCedula(): void
    {
        $this->confirmacion = '';
        $this->resetValidation();

        $digitos = strlen(Persona::normalizarCedula($this->cedula));

        // Fuera del rango de una cédula no se busca. Por arriba tampoco: el campo ya no deja
        // teclear de más, pero esto no depende de que el navegador se porte bien.
        if ($digitos < Marcaje::DIGITOS_MINIMOS || $digitos > Marcaje::DIGITOS_MAXIMOS) {
            $this->olvidarPersona();

            return;
        }

        $this->localizar(Persona::normalizarCedula($this->cedula));
    }

    /**
     * Se dispara al pulsar Enter, y es también como llega el carnet del lector.
     *
     * Sigue existiendo aunque la búsqueda ya sea automática: el lector termina con un Enter, y
     * quien está acostumbrado a pulsarlo no tiene por qué cambiar de costumbre. La diferencia es
     * que aquí sí se valida y se avisa, porque pulsar Enter es decir «ya terminé».
     */
    public function buscar(): void
    {
        $this->confirmacion = '';
        $this->resetValidation();
        $this->olvidarPersona();

        try {
            $cedula = $this->marcaje->exigirCedulaValida($this->cedula);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        $this->localizar($cedula);
    }

    /** A quién pertenece esta cédula, y qué se le muestra al vigilante. */
    protected function localizar(string $cedula): void
    {
        $persona = $this->marcaje->buscarPorCedula($cedula);

        if (! $persona) {
            // No está en la lista del personal: es un invitado.
            // Si ya se estaba escribiendo su ficha no se borra lo escrito.
            if (! $this->invitadoNuevo) {
                $this->nombre = '';
                $this->motivo = '';
                $this->olvidarVehiculo();
            }

            $this->personaId = null;
            $this->invitadoNuevo = true;
            unset($this->persona, $this->sugerido, $this->esperaHasta);

            return;
        }

        $this->personaId = $persona->id;
        $this->invitadoNuevo = false;
        unset($this->persona, $this->sugerido, $this->esperaHasta);

        // Un invitado que vuelve ya trae su motivo: se muestra para confirmarlo o cambiarlo.
        if ($persona->esInvitado()) {
            $this->motivo = (string) $persona->motivo;
        }

        // El vehículo de la última vez, sea invitado o trabajador. Casi siempre es el mismo —
        // pero si hoy viene caminando, el vigilante vacía las casillas y así queda anotado.
        $this->cambiandoVehiculo = false;
        $this->tipoVehiculo = Vehiculo::normalizarTipo($persona->tipo_vehiculo);
        $this->marca = (string) $persona->marca;
        $this->modelo = (string) $persona->modelo;
        $this->color = (string) $persona->color;
        $this->placa = (string) $persona->placa;

        unset($this->tipoFijado);
    }

    /** El vehículo tal y como está escrito ahora mismo en la pantalla. */
    protected function vehiculo(): Vehiculo
    {
        return Vehiculo::desde($this->tipoVehiculo, $this->marca, $this->modelo, $this->color, $this->placa);
    }

    /**
     * El tipo que la persona en pantalla ya tiene anotado, cuando no se puede cambiar.
     *
     * Un vehículo no cambia de clase: la moto de José es una moto todos los días. Si un día
     * llega en otra cosa, eso es OTRO vehículo y para eso está «Otro vehículo», que vacía las
     * casillas y devuelve la elección.
     *
     * Devuelve null cuando el tipo sí se puede elegir: nadie en pantalla, sin vehículo anotado,
     * o el vigilante ya pulsó «Otro vehículo».
     */
    #[Computed]
    public function tipoFijado(): ?string
    {
        if ($this->cambiandoVehiculo) {
            return null;
        }

        $persona = $this->persona();

        return $persona?->tieneVehiculo() ? $persona->vehiculo()->tipo : null;
    }

    /** Vacía el vehículo anotado para poder poner otro, de la clase que sea. */
    public function cambiarVehiculo(): void
    {
        $this->olvidarVehiculo();
        $this->cambiandoVehiculo = true;
        $this->resetValidation();
        unset($this->tipoFijado);
    }

    /** Vacía las casillas del vehículo y deja el tipo como empieza. */
    protected function olvidarVehiculo(): void
    {
        $this->tipoVehiculo = Vehiculo::CARRO;
        $this->marca = '';
        $this->modelo = '';
        $this->color = '';
        $this->placa = '';
    }

    /** Deja de mostrar a nadie, sin tocar la cédula que se está teclando. */
    protected function olvidarPersona(): void
    {
        $this->personaId = null;
        $this->invitadoNuevo = false;
        unset($this->persona, $this->sugerido, $this->tipoFijado, $this->esperaHasta);
    }

    /** Da de alta al invitado nuevo y lo deja listo para marcar, sin teclear la cédula otra vez. */
    public function guardarInvitado(): void
    {
        try {
            $persona = $this->marcaje->registrarInvitado(
                $this->cedula,
                $this->nombre,
                $this->motivo,
                $this->vehiculo(),
            );
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
                motivo: $persona->esInvitado() ? $this->motivo : null,
                // El vehículo se le pregunta a todos: el personal también estaciona aquí.
                vehiculo: $this->vehiculo(),
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
        $this->reset([
            'cedula', 'personaId', 'invitadoNuevo', 'nombre', 'motivo', 'confirmacion',
            'tipoVehiculo', 'marca', 'modelo', 'color', 'placa', 'cambiandoVehiculo',
        ]);
        $this->resetValidation();
        unset($this->persona, $this->sugerido, $this->dentro, $this->tipoFijado, $this->esperaHasta);
    }

    public function render()
    {
        return view('livewire.marcar');
    }
}
