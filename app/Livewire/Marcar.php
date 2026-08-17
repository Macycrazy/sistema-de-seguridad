<?php

namespace App\Livewire;

use App\Models\Movimiento;
use App\Models\Persona;
use App\Services\DatosVehiculo;
use App\Services\Marcaje;
use Illuminate\Support\Collection;
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

    /**
     * La letra de la cédula: V, E o J. Se escoge en un desplegable, no se teclea.
     *
     * Empieza en «V» porque es lo que se presenta en la puerta el noventa y tantos por ciento de
     * las veces. Cambiarla vuelve a buscar: el mismo número con otra letra es otra persona.
     */
    public string $nacionalidad = Persona::VENEZOLANO;

    /** La persona encontrada, si ya se buscó. */
    public ?int $personaId = null;

    /** Se enciende cuando la cédula no está en el sistema: hay que dar de alta un invitado. */
    public bool $invitadoNuevo = false;

    /** Cuántos pisos se ofrecen como atajo antes de dejarlo solo en la casilla de escribir. */
    public const PISOS_COMO_MUCHO = 12;

    /** Los dos campos obligatorios del formulario de invitado. */
    public string $nombre = '';

    public string $motivo = '';

    /**
     * A qué piso se dirige el invitado, con el código del edificio: «2-1», «2-2» y así.
     *
     * Se le pregunta SIEMPRE, porque puede cambiar de una visita a otra. Al trabajador no: el
     * suyo es fijo, viene de su ficha y en la pantalla solo se muestra.
     */
    public string $piso = '';

    /** Se marcó «a pie»: hoy no trajo ningún vehículo. Es lo más común. */
    public const A_PIE = '';

    /** Se marcó «otro vehículo»: hay que teclearlo, y se le suma a su ficha al marcar. */
    public const OTRO = 'otro';

    /**
     * Qué trae HOY: la placa de uno de sus vehículos, «a pie», u «otro».
     *
     * Una persona puede tener varios —carro y moto, por ejemplo— y en la puerta se señala cuál
     * de ellos trae ese día. Por eso es una casilla y no unos campos que se rellenan cada vez.
     */
    public string $traeHoy = self::A_PIE;

    /**
     * Las casillas para teclear un vehículo que no está en su lista. Solo se usan cuando se
     * marcó «otro», y en el alta de un invitado, que todavía no tiene ninguno.
     *
     * Van sueltas y no como un objeto porque cada una es una casilla y Livewire ata cada casilla
     * a una propiedad.
     *
     * El tipo empieza en «carro» porque siempre hay uno de los dos botones marcado. No significa
     * que haya vehículo: mientras las demás casillas estén vacías, no se guarda nada.
     */
    public string $tipoVehiculo = DatosVehiculo::CARRO;

    public string $marca = '';

    public string $modelo = '';

    public string $color = '';

    public string $placa = '';

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
     * Quién hay dentro, separado en trabajadores e invitados.
     *
     * @return array{trabajador: int, invitado: int}
     */
    #[Computed]
    public function dentroPorTipo(): array
    {
        return $this->marcaje->cuantosDentroPorTipo();
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

    /**
     * Cuántos dígitos deja teclear el campo. Lo decide el servicio, no la pantalla, y depende de
     * la letra: la jurídica admite uno más porque su número es un RIF.
     */
    public function maximoDigitos(): int
    {
        return Marcaje::digitosMaximos($this->nacionalidad);
    }

    /**
     * Cambiar la letra vuelve a buscar con lo que ya está tecleado.
     *
     * Sin esto, escoger «E» después de teclear el número dejaría en pantalla la ficha del
     * venezolano: el vigilante creería que es esa persona y le marcaría la entrada a otro.
     */
    public function updatedNacionalidad(): void
    {
        $this->nacionalidad = Persona::normalizarNacionalidad($this->nacionalidad);

        $this->updatedCedula();
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
        if ($digitos < Marcaje::DIGITOS_MINIMOS || $digitos > $this->maximoDigitos()) {
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
            $cedula = $this->marcaje->exigirCedulaValida($this->cedula, $this->nacionalidad);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        $this->localizar($cedula);
    }

    /** A quién pertenece esta cédula, y qué se le muestra al vigilante. */
    protected function localizar(string $cedula): void
    {
        $persona = $this->marcaje->buscarPorCedula($cedula, $this->nacionalidad);

        if (! $persona) {
            // No está en la lista del personal: es un invitado.
            // Si ya se estaba escribiendo su ficha no se borra lo escrito.
            if (! $this->invitadoNuevo) {
                $this->nombre = '';
                $this->motivo = '';
                $this->piso = '';
                $this->olvidarVehiculo();
            }

            $this->personaId = null;
            $this->invitadoNuevo = true;
            unset($this->persona, $this->sugerido, $this->vehiculos, $this->esperaHasta);

            return;
        }

        $this->personaId = $persona->id;
        $this->invitadoNuevo = false;
        unset($this->persona, $this->sugerido, $this->vehiculos, $this->esperaHasta);

        // Un invitado que vuelve ya trae su motivo y el piso de la última vez: se muestran para
        // confirmarlos o cambiarlos, que para eso se le pregunta cada visita.
        if ($persona->esInvitado()) {
            $this->motivo = (string) $persona->motivo;
            $this->piso = (string) $persona->piso;
        }

        // Se propone lo mismo que trajo la última vez que entró, que casi siempre acierta. Si
        // ese vehículo ya no está en su ficha, o si vino a pie, queda marcado «a pie».
        $this->olvidarVehiculo();
        $ultima = $persona->placaDeLaUltimaEntrada();
        $this->traeHoy = $persona->vehiculoConPlaca($ultima) ? $ultima : self::A_PIE;

        unset($this->vehiculos);
    }

    /** Los vehículos que la persona en pantalla tiene anotados. */
    #[Computed]
    public function vehiculos(): Collection
    {
        return $this->persona()?->vehiculos ?? collect();
    }

    /**
     * Los pisos que ya se usan en el edificio, para ofrecerlos como atajos.
     *
     * NO son una lista fija en el código: salen de las fichas que hay en la base. Hoy son pocos
     * porque los datos son de prueba; cuando se cargue el personal de verdad, la lista aparece
     * sola y sin tocar nada. Por eso tampoco sustituyen a la casilla de escribir: un piso al que
     * nadie ha ido todavía no está en la lista, y aun así tiene que poder anotarse.
     *
     * El tope está para que un dato sucio no llene la pantalla de botones: si algún día hay más
     * de estos, se teclea y ya.
     */
    #[Computed]
    public function pisosConocidos(): Collection
    {
        return Persona::query()
            ->whereNotNull('piso')
            ->where('piso', '!=', '')
            ->distinct()
            ->orderBy('piso')
            ->limit(self::PISOS_COMO_MUCHO)
            ->pluck('piso');
    }

    /**
     * Qué trae hoy, ya limpio y listo para guardar.
     *
     * Tres casos: no trajo nada, trajo uno de los suyos, o trajo uno que hay que teclear. Los
     * tres salen de la misma casilla, así que aquí se decide una sola vez y no en cada sitio
     * que necesite el dato.
     */
    protected function vehiculo(): DatosVehiculo
    {
        // En el alta de un invitado no hay lista todavía: lo que valga es lo tecleado.
        if ($this->invitadoNuevo || $this->traeHoy === self::OTRO) {
            return DatosVehiculo::desde($this->tipoVehiculo, $this->marca, $this->modelo, $this->color, $this->placa);
        }

        if ($this->traeHoy === self::A_PIE) {
            return DatosVehiculo::desde();
        }

        return $this->vehiculos()
            ->firstWhere('placa', $this->traeHoy)
            ?->datos()
            // La placa marcada ya no está entre las suyas: se trata como que vino a pie, que es
            // lo prudente. No se inventa un vehículo que no consta.
            ?? DatosVehiculo::desde();
    }

    /** Vuelve a «a pie» y vacía las casillas de teclear. */
    protected function olvidarVehiculo(): void
    {
        $this->traeHoy = self::A_PIE;
        $this->tipoVehiculo = DatosVehiculo::CARRO;
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
        unset($this->persona, $this->sugerido, $this->vehiculos, $this->esperaHasta);
    }

    /** Da de alta al invitado nuevo y lo deja listo para marcar, sin teclear la cédula otra vez. */
    public function guardarInvitado(): void
    {
        // Se limpia lo de antes: si no, quien corrige el dato que faltaba y vuelve a pulsar se
        // queda mirando el aviso rojo del intento anterior, ya resuelto.
        $this->resetValidation();

        $vehiculo = $this->vehiculo();

        try {
            $persona = $this->marcaje->registrarInvitado(
                $this->cedula,
                $this->nombre,
                $this->motivo,
                $this->piso,
                $vehiculo,
                $this->nacionalidad,
            );
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        $this->personaId = $persona->id;
        $this->invitadoNuevo = false;

        // Se relee de la ficha, ya normalizado: si el vigilante tecleó «2 - 1» y se guardó
        // «2-1», la pantalla tiene que enseñar lo que quedó guardado y no lo que él escribió.
        $this->piso = (string) $persona->piso;

        // Ya tiene ficha, así que a partir de aquí manda la casilla y no lo tecleado. Se deja
        // marcado el vehículo que se acaba de anotar: si no, el invitado que llegó en carro
        // quedaría con la entrada registrada a pie, y el vigilante ni se enteraría.
        $this->traeHoy = $vehiculo->placa ?? self::A_PIE;

        unset($this->vehiculos);
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

        // Igual que en el alta: el aviso del intento anterior no puede quedarse colgado.
        $this->resetValidation();

        try {
            $movimiento = $this->marcaje->registrar(
                persona: $persona,
                tipo: $tipo,
                // La parte 3 pondrá aquí el usuario que tiene la sesión abierta.
                usuarioId: auth()->id(),
                motivo: $persona->esInvitado() ? $this->motivo : null,
                // Al trabajador no se le pregunta: su piso ya está en la ficha.
                piso: $persona->esInvitado() ? $this->piso : null,
                // El vehículo se le pregunta a todos: el personal también estaciona aquí.
                vehiculo: $this->vehiculo(),
            );
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        $verbo = $tipo === Movimiento::ENTRADA ? 'Entrada' : 'Salida';

        /*
         * La hora sale del ASIENTO, no de now(). Parece lo mismo y no lo es: ante una doble
         * pulsación, Marcaje::registrar() devuelve el movimiento que ya existía en vez de crear
         * otro, y entonces la hora buena es la de aquel, no la de este segundo toque. Con now()
         * la pantalla diría una hora que no está guardada en ninguna parte.
         */
        $confirmacion = "{$verbo} registrada a las {$movimiento->ocurrio_en->format('H:i')} · {$persona->nombre}";

        $this->limpiar();
        $this->confirmacion = $confirmacion;
    }

    /** Vuelve al estado inicial: campo vacío y listo para teclear. */
    public function limpiar(): void
    {
        // La letra vuelve a «V» como todo lo demás: la pantalla queda igual para el siguiente, y
        // el siguiente casi siempre es venezolano. Dejarla puesta sería peor que reiniciarla —el
        // vigilante no se acordaría de que quedó en «E» del anterior—.
        $this->reset([
            'cedula', 'nacionalidad', 'personaId', 'invitadoNuevo', 'nombre', 'motivo', 'piso',
            'confirmacion', 'traeHoy', 'tipoVehiculo', 'marca', 'modelo', 'color', 'placa',
        ]);
        $this->resetValidation();
        unset($this->persona, $this->sugerido, $this->dentro, $this->vehiculos, $this->esperaHasta);
    }

    public function render()
    {
        return view('livewire.marcar');
    }
}
