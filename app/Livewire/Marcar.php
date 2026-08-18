<?php

namespace App\Livewire;

use App\Models\Movimiento;
use App\Models\Persona;
use App\Services\Auditoria\Auditoria;
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

    /**
     * Si el aviso de «esta cédula no está en el sistema» sigue en pantalla.
     *
     * Se puede cerrar con la equis: al vigilante que ya entendió de qué va, el aviso solo le
     * quita sitio a las casillas que tiene que rellenar. Vuelve a salir con cada cédula nueva,
     * porque entonces es información y no un estorbo.
     */
    public bool $avisoInvitado = true;

    /** El piso —solo el número— que se escogió primero, antes de elegir la oficina. */
    public string $nivel = '';

    /**
     * Si hay que escribir el sitio a mano, porque no está en la lista del edificio.
     *
     * Por omisión no: se toca el piso, se toca la oficina y ya está dicho. La casilla solo
     * aparece cuando de verdad hace falta —un sitio que no consta— y no como una segunda forma de
     * hacer lo que los botones acaban de hacer.
     */
    public bool $pisoAMano = false;

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
            ? $this->marcaje->puedeEntrarDesde($persona)?->format(Movimiento::FORMATO_HORA)
            : null;
    }

    /**
     * Por qué todavía no se le puede marcar la entrada, ya redactado por el servicio.
     *
     * La pantalla no arma esta frase: hay dos plazos —uno desde su entrada anterior y otro desde
     * su salida— y solo el servicio sabe cuál de los dos manda en este momento.
     */
    #[Computed]
    public function motivoEspera(): ?string
    {
        $persona = $this->persona();

        return $persona ? $this->marcaje->motivoDeLaEsperaParaEntrar($persona) : null;
    }

    /** Los minutos que tienen que pasar entre la entrada y su salida. Otro plazo, otro número. */
    public function minutosEntreEntradaYSalida(): int
    {
        return $this->marcaje->minutosEntreEntradaYSalida();
    }

    /**
     * A qué hora se le podrá marcar la SALIDA, si es que hay que esperar.
     *
     * Null cuando puede salir ya, que es lo normal: solo hay espera si acaba de entrar.
     */
    #[Computed]
    public function esperaSalidaHasta(): ?string
    {
        $persona = $this->persona();

        return $persona
            ? $this->marcaje->puedeSalirDesde($persona)?->format(Movimiento::FORMATO_HORA)
            : null;
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

            // Cédula nueva, aviso nuevo: lo que se cerró antes era para el invitado anterior.
            $this->avisoInvitado = true;
            unset($this->persona, $this->sugerido, $this->vehiculos, $this->esperaHasta, $this->esperaSalidaHasta, $this->motivoEspera);

            return;
        }

        $this->personaId = $persona->id;
        $this->invitadoNuevo = false;
        unset($this->persona, $this->sugerido, $this->vehiculos, $this->esperaHasta, $this->esperaSalidaHasta, $this->motivoEspera);

        // Que el vigilante haya sacado la ficha de esta cédula queda anotado. Con dedup: el tecleo
        // dispara esta búsqueda varias veces, y para la auditoría fue una sola consulta.
        app(Auditoria::class)->consultoCedula($cedula);

        // Un invitado que vuelve ya trae su motivo y el piso de la última vez: se muestran para
        // confirmarlos o cambiarlos, que para eso se le pregunta cada visita.
        if ($persona->esInvitado()) {
            $this->motivo = (string) $persona->motivo;
            $this->piso = (string) $persona->piso;

            // Para que la lista de oficinas salga ya abierta por el piso de la última visita.
            $this->nivel = self::nivelDe($this->piso);

            // Si la última vez fue a un sitio que no consta en la lista —uno viejo, o tecleado a
            // mano—, la casilla sale abierta con lo que hay: si no, el dato quedaría invisible.
            $this->pisoAMano = $this->piso !== '' && ! $this->pisoEstaEnLaLista();
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
     * Las oficinas del edificio agrupadas por piso, con la gerencia que hay en cada una.
     *
     *     ['2' => ['2-1' => 'Tecnología', '2-2' => 'Planificación y Presupuesto'], ...]
     *
     * No hace falta ninguna tabla nueva para esto: la asociación entre el piso y la gerencia YA
     * está en las fichas del personal —el código «2-1» es piso 2, oficina 1, y quien labora ahí
     * dice de qué gerencia es—. Se lee de ellas, así que se mantiene sola: cuando entre el
     * personal de verdad, el edificio entero aparece sin tocar código.
     *
     * Solo mira a los TRABAJADORES a propósito: las oficinas son de quien labora aquí. El piso de
     * un invitado es a dónde fue de visita, y tomarlo por oficina llenaría la lista de sitios que
     * no existen.
     *
     * @return array<string, array<string, string>>
     */
    #[Computed]
    public function oficinasPorPiso(): array
    {
        // La LISTA sale del catálogo del edificio (config/edificio.php): hay sitios donde no
        // labora nadie —el LOBBY— y aun así se va de visita a ellos.
        $catalogo = collect(config('edificio.oficinas', []))
            ->map(fn ($codigo) => Persona::normalizarPiso($codigo))
            ->filter()
            ->unique();

        // La GERENCIA sale de las fichas del personal, que es donde ya consta quién labora dónde.
        // Así no puede contradecirlas, y una oficina vacía se ofrece igual, solo que sin nombre.
        $gerencias = Persona::query()
            ->where('tipo', Persona::TRABAJADOR)
            ->whereNotNull('piso')
            ->where('piso', '!=', '')
            ->orderBy('piso')
            ->get(['piso', 'dependencia'])
            // Si dos fichas de la misma oficina dicen gerencias distintas, se queda la primera:
            // aquí no se arregla un dato mal puesto, solo se pone un nombre debajo de un botón.
            ->reduce(function (array $lista, Persona $ficha) {
                $lista[(string) $ficha->piso] ??= trim((string) $ficha->dependencia);

                return $lista;
            }, []);

        // El respaldo para las oficinas donde todavía no labora nadie. Las claves se pasan a texto
        // porque PHP convierte en número las que lo parecen: «9» llegaría aquí como int y no
        // encontraría a su oficina, que es una cadena.
        $nombres = [];

        foreach ((array) config('edificio.nombres', []) as $codigo => $nombre) {
            $nombres[(string) $codigo] = trim((string) $nombre);
        }

        $mapa = [];

        foreach ($catalogo as $codigo) {
            // Manda la ficha; el nombre del catálogo solo se usa si no hay nadie anotado ahí.
            $mapa[self::nivelDe($codigo)][$codigo] = ($gerencias[$codigo] ?? '') ?: ($nombres[$codigo] ?? '');
        }

        // Los pisos con nombre —LOBBY, PB— van primero: son la planta de abajo, por donde se
        // entra. Después los numerados, en orden de verdad: sin esto, «10» iría antes que «7».
        uksort($mapa, function ($uno, $otro) {
            $unoEsNumero = ctype_digit((string) $uno);
            $otroEsNumero = ctype_digit((string) $otro);

            if ($unoEsNumero !== $otroEsNumero) {
                return $unoEsNumero <=> $otroEsNumero;
            }

            return $unoEsNumero
                ? (int) $uno <=> (int) $otro
                : strcmp((string) $uno, (string) $otro);
        });

        foreach ($mapa as $nivel => $oficinas) {
            ksort($oficinas, SORT_NATURAL);
            $mapa[$nivel] = $oficinas;
        }

        return $mapa;
    }

    /**
     * El nombre con el que se conoce a un piso entero, para ponerlo en su botón.
     *
     *     ['9' => 'Presidencia']
     *
     * Solo lo tienen los pisos de UNA SOLA oficina —los que no llegan a enseñar la lista de
     * oficinas, así que no tendrían dónde enseñar su nombre—, y solo si ese nombre está en el
     * catálogo del edificio.
     *
     * Que venga del catálogo y no de las fichas no es un detalle: el catálogo nombra el SITIO
     * —«el 9 es Presidencia», que es como se le llama antes que por su número— mientras que la
     * ficha dice qué gerencia labora en una oficina. Poner gerencias en los botones de los pisos
     * llenaría la fila de nombres que solo valen para un despacho.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function nombresDePiso(): array
    {
        $delCatalogo = [];

        foreach ((array) config('edificio.nombres', []) as $codigo => $nombre) {
            $delCatalogo[(string) $codigo] = trim((string) $nombre);
        }

        $nombres = [];

        foreach ($this->oficinasPorPiso() as $nivel => $oficinas) {
            if (count($oficinas) !== 1) {
                continue;
            }

            $nombre = $delCatalogo[array_key_first($oficinas)] ?? '';

            if ($nombre !== '') {
                $nombres[$nivel] = $nombre;
            }
        }

        return $nombres;
    }

    /** El piso al que pertenece un código de oficina: de «2-1» sale «2». */
    public static function nivelDe(?string $piso): string
    {
        return explode('-', (string) $piso, 2)[0];
    }

    /**
     * Se escogió el piso: falta la oficina, así que el código anterior deja de valer.
     *
     * Salvo cuando ese piso tiene UNA SOLA oficina —el LOBBY, el 7, el PB-1, el 8-2—. Ahí no hay
     * nada que escoger: preguntar «¿a qué oficina?» para ofrecer una sola respuesta es pedir un
     * toque para nada, y en la puerta se marca de pie y apurado. Queda anotada de una vez.
     *
     * Se mira cuántas hay y no cómo se llaman: el día que al piso 7 le pongan una segunda
     * oficina, la pantalla vuelve sola a preguntar, sin que nadie tenga que acordarse de esto.
     */
    public function elegirNivel(string $nivel): void
    {
        $this->nivel = $nivel;

        $oficinas = array_keys($this->oficinasPorPiso()[$nivel] ?? []);

        $this->piso = count($oficinas) === 1 ? $oficinas[0] : '';

        // Se escogió de la lista, así que la casilla de escribir deja de hacer falta.
        $this->pisoAMano = false;
    }

    /** Se escogió una oficina de la lista: se anota y se cierra la casilla de escribir. */
    public function elegirOficina(string $codigo): void
    {
        $this->piso = $codigo;
        $this->pisoAMano = false;
    }

    /** El sitio no está en la lista del edificio: hay que teclearlo. */
    public function escribirPisoAMano(): void
    {
        $this->pisoAMano = true;
        $this->piso = '';
        $this->nivel = '';
    }

    /** Si el sitio que hay puesto consta en la lista del edificio. */
    public function pisoEstaEnLaLista(): bool
    {
        foreach ($this->oficinasPorPiso() as $oficinas) {
            if (array_key_exists($this->piso, $oficinas)) {
                return true;
            }
        }

        return false;
    }

    /** Si se teclea el código a mano, el piso de arriba se pone al día solo. */
    public function updatedPiso(): void
    {
        $this->nivel = self::nivelDe($this->piso);
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
        unset($this->persona, $this->sugerido, $this->vehiculos, $this->esperaHasta, $this->esperaSalidaHasta, $this->motivoEspera);
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
        $hora = $movimiento->ocurrio_en->format(Movimiento::FORMATO_HORA);
        $confirmacion = "{$verbo} registrada a las {$hora} · {$persona->nombre}";

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
            'cedula', 'nacionalidad', 'personaId', 'invitadoNuevo', 'avisoInvitado', 'nombre',
            'motivo', 'piso', 'nivel', 'pisoAMano', 'confirmacion', 'traeHoy', 'tipoVehiculo',
            'marca', 'modelo', 'color', 'placa',
        ]);
        $this->resetValidation();

        // Los dos contadores se olvidan juntos: acaba de registrarse un movimiento, así que lo
        // que decían ya no es cierto. Olvidar solo el total dejaría el desglose de antes en
        // pantalla, y el vigilante vería «41 y 6» debajo de un total que ya cambió.
        unset(
            $this->persona, $this->sugerido, $this->vehiculos, $this->esperaHasta,
            $this->esperaSalidaHasta, $this->dentro, $this->dentroPorTipo,
        );
    }

    public function render()
    {
        return view('livewire.marcar');
    }
}
