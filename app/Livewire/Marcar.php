<?php

namespace App\Livewire;

use App\Models\EntregaDePase;
use App\Models\Movimiento;
use App\Models\Pase;
use App\Models\Persona;
use App\Models\Vehiculo;
use App\Models\VehiculoDeFlota;
use App\Models\VehiculoFijo;
use App\Services\Auditoria\Auditoria;
use App\Services\Carnets\Verificador;
use App\Services\DatosVehiculo;
use App\Services\Estacionamiento\Flota;
use App\Services\Estacionamiento\VehiculoEnLaPuerta;
use App\Services\Marcaje;
use App\Services\Pases\Pases;
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
    /** Lo que vale «vehiculoEntrada» cuando la placa se teclea en vez de elegirse de su ficha. */
    public const VEHICULO_OTRO = 'otro';

    /**
     * Entrar a pie: sin vehículo. Es lo de por omisión —la mayoría llega caminando— pero tiene su
     * propio botón igual que los demás.
     *
     * No basta con dejarlo sin elegir. Al lado de unos botones con placas, «no toques nada» no se
     * lee como una opción: se lee como que falta algo por hacer, y quien no está seguro acaba
     * tocando cualquier cosa. Con su botón, entrar a pie es una elección que se ve tomada.
     */
    public const VEHICULO_A_PIE = '';

    /**
     * Lo que precede al id cuando se elige un vehículo de la empresa: «flota:7».
     *
     * Los de la empresa no son de nadie —no están clavados a un conductor—, así que no salen de la
     * ficha de la persona sino del catálogo de la flota. Cualquiera puede traerlos y cualquiera
     * puede llevárselos; lo que queda anotado es quién lo hizo cada vez.
     */
    public const PREFIJO_FLOTA = 'flota:';

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

    /**
     * Con qué vehículo entra: la placa de uno de los suyos, «otro» para teclear una, o vacío
     * para entrar a pie —que es lo normal y por eso es lo de por omisión—.
     *
     * El vehículo se anota AQUÍ, en el mismo gesto de marcar a la persona, y no en un segundo
     * formulario donde había que volver a teclear su cédula. Así el conductor no se teclea: es
     * quien se está marcando.
     */
    public string $vehiculoEntrada = '';

    /** La placa tecleada, cuando el vehículo no es ninguno de los suyos. */
    public string $placaNueva = '';

    public string $tipoNuevo = DatosVehiculo::CARRO;

    /**
     * El vehículo de otro que se está eligiendo en el desplegable, antes de añadirlo.
     *
     * Se llega en el carro propio y se sale en la moto de un compañero más de lo que parece, y
     * hay que poder anotarlo aquí: si no, la estadía se queda abierta y ese vehículo figura
     * dentro sin estar.
     */
    public string $otroVehiculoSalida = '';

    /**
     * Los vehículos que se lleva al salir: los ids de las estadías abiertas.
     *
     * Empieza VACÍO a propósito, aunque tenga el carro dentro. Se sale a pie muchas veces al día
     * —el almuerzo, un trámite— y marcarle el carro por omisión cerraría estadías de vehículos
     * que siguen ahí. Incluirlo cuesta un toque; deshacer una salida falsa, no.
     *
     * @var list<int>
     */
    public array $vehiculosSalida = [];

    /**
     * El pase que se le entrega al visitante (su id), o vacío para no darle ninguno.
     *
     * Solo para invitados: el trabajador tiene su carnet. Se entrega en el mismo gesto de marcar
     * la entrada —igual que el vehículo— porque en la puerta no hay un segundo momento para nada.
     */
    public string $paseEntrada = '';

    /** Si al marcar la salida se recupera el pase que lleva. Empieza en sí: es lo que debe pasar. */
    public bool $devuelvePase = true;

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

    /**
     * Llega el contenido de un QR escaneado del carnet. Se verifica contra el sistema de carnets
     * (por su API interna) y, si es personal activo, se actualiza/da de alta la ficha con lo que
     * carnets dice y se sigue el flujo normal, como si se hubiera tecleado la cédula.
     *
     * El QR trae un token, no la cédula: solo carnets sabe a quién pertenece. Por eso aquí no se
     * confía en el token pelado, sino en el veredicto de carnets.
     */
    public function carnetEscaneado(string $contenido): void
    {
        $this->confirmacion = '';
        $this->resetValidation();
        $this->olvidarPersona();

        $veredicto = app(Verificador::class)->verificar(config('carnets.url'), $contenido);

        if (! ($veredicto['ok'] ?? false)) {
            $this->addError('cedula', $veredicto['mensaje'] ?? 'No se pudo consultar el carnet.');

            return;
        }

        $datos = $veredicto['datos'] ?? [];

        if (($datos['activo'] ?? false) !== true) {
            $this->addError('cedula', 'Ese carnet no es de personal activo. Si va a pasar, regístralo como invitado.');

            return;
        }

        $cedula = Persona::normalizarCedula((string) ($datos['cedula'] ?? ''));

        if ($cedula === '') {
            $this->addError('cedula', 'El carnet no trajo una cédula válida.');

            return;
        }

        $nacionalidad = Persona::normalizarNacionalidad($datos['nacionalidad'] ?? Persona::VENEZOLANO);

        // Se corrobora y se RELLENA contra el carnets, que es la autoridad sobre quién es personal:
        // si existe se actualizan sus datos; si no, se da de alta. Y si estaba como INVITADO pero el
        // carnet dice que es trabajador activo, se corrige a trabajador —justamente para eso se
        // escanea—. La puerta es el único sitio por donde entra gente que aún no estaba.
        Persona::updateOrCreate(
            ['cedula' => $cedula],
            [
                'tipo' => Persona::TRABAJADOR,
                'nombre' => mb_strtoupper(trim((string) ($datos['nombre'] ?? ''))),
                'nacionalidad' => $nacionalidad,
                'dependencia' => $datos['gerencia'] ?? null,
                'activo' => true,
            ],
        );

        // Se muestra como si se hubiera tecleado la cédula: el flujo de marcaje sigue igual.
        $this->nacionalidad = $nacionalidad;
        $this->cedula = $cedula;
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
            }

            $this->personaId = null;
            $this->invitadoNuevo = true;

            // Cédula nueva, aviso nuevo: lo que se cerró antes era para el invitado anterior.
            $this->avisoInvitado = true;
            unset(
                $this->persona, $this->sugerido, $this->esperaHasta, $this->esperaSalidaHasta,
                $this->motivoEspera, $this->susVehiculos, $this->susVehiculosDentro, $this->otrosVehiculosDentro,
                $this->susPlacasDentro, $this->pasesLibres, $this->paseQueLleva, $this->hayPasesCargados,
                $this->flotaParaEntrar, $this->hayFlotaCargada,
            );

            return;
        }

        $this->personaId = $persona->id;
        $this->invitadoNuevo = false;

        // Los vehículos son de quien acaba de aparecer, no de quien estaba antes.
        $this->reset(['vehiculoEntrada', 'placaNueva', 'tipoNuevo', 'vehiculosSalida', 'otroVehiculoSalida', 'paseEntrada', 'devuelvePase']);

        unset(
            $this->persona, $this->sugerido, $this->esperaHasta, $this->esperaSalidaHasta,
            $this->motivoEspera, $this->susVehiculos, $this->susVehiculosDentro, $this->otrosVehiculosDentro,
            $this->susPlacasDentro, $this->pasesLibres, $this->paseQueLleva, $this->hayPasesCargados,
            $this->flotaParaEntrar, $this->hayFlotaCargada,
        );

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

    /** Deja de mostrar a nadie, sin tocar la cédula que se está teclando. */
    protected function olvidarPersona(): void
    {
        $this->personaId = null;
        $this->invitadoNuevo = false;

        // Lo elegido era de la persona anterior: el carro de uno no puede quedarse marcado
        // cuando en pantalla ya hay otro.
        $this->reset(['vehiculoEntrada', 'placaNueva', 'tipoNuevo', 'vehiculosSalida', 'otroVehiculoSalida', 'paseEntrada', 'devuelvePase']);

        unset(
            $this->persona, $this->sugerido, $this->esperaHasta, $this->esperaSalidaHasta,
            $this->motivoEspera, $this->susVehiculos, $this->susVehiculosDentro, $this->otrosVehiculosDentro,
            $this->susPlacasDentro, $this->pasesLibres, $this->paseQueLleva, $this->hayPasesCargados,
            $this->flotaParaEntrar, $this->hayFlotaCargada,
        );
    }

    /** Da de alta al invitado nuevo y lo deja listo para marcar, sin teclear la cédula otra vez. */
    public function guardarInvitado(): void
    {
        // Se limpia lo de antes: si no, quien corrige el dato que faltaba y vuelve a pulsar se
        // queda mirando el aviso rojo del intento anterior, ya resuelto.
        $this->resetValidation();

        try {
            $persona = $this->marcaje->registrarInvitado(
                $this->cedula,
                $this->nombre,
                $this->motivo,
                $this->piso,
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
    }

    /**
     * Los vehículos guardados de quien se está marcando: los de un toque.
     *
     * @return Collection<int, Vehiculo>
     */
    #[Computed]
    public function susVehiculos(): Collection
    {
        $persona = $this->persona();

        return $persona ? app(VehiculoEnLaPuerta::class)->suyos($persona) : collect();
    }

    /**
     * De sus vehículos, cuáles están ya dentro: no se puede volver a entrar con ellos.
     *
     * @return list<string>
     */
    #[Computed]
    public function susPlacasDentro(): array
    {
        $persona = $this->persona();

        return $persona ? app(VehiculoEnLaPuerta::class)->suyosQueEstanDentro($persona)->all() : [];
    }

    /**
     * Los vehículos suyos que están DENTRO: lo único que se le puede ofrecer al salir.
     *
     * @return Collection<int, VehiculoFijo>
     */
    #[Computed]
    public function susVehiculosDentro(): Collection
    {
        $persona = $this->persona();

        return $persona ? app(VehiculoEnLaPuerta::class)->dentroASuNombre($persona) : collect();
    }

    /**
     * Los vehículos de la empresa que se pueden traer ahora: los del catálogo que no están dentro.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function flotaParaEntrar(): array
    {
        return app(Flota::class)->disponibles()
            ->mapWithKeys(fn (VehiculoDeFlota $v) => [self::PREFIJO_FLOTA.$v->id => $v->descripcion()])
            ->all();
    }

    /**
     * Si la empresa tiene vehículos cargados, aunque ahora mismo no se pueda traer ninguno.
     *
     * Sirve para distinguir dos silencios que se ven igual y no lo son: que no haya catálogo —hay
     * que cargarlo en Estacionamiento— y que estén todos dentro, que es lo normal a media mañana.
     * Sin decirlo, la pantalla simplemente no enseña nada y parece que lo de la flota no va.
     */
    #[Computed]
    public function hayFlotaCargada(): bool
    {
        return VehiculoDeFlota::query()->activos()->exists();
    }

    /**
     * Los pases que se pueden entregar ahora. Vacío si no hay catálogo o están todos fuera.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function pasesLibres(): array
    {
        return app(Pases::class)->libres()
            ->mapWithKeys(fn (Pase $pase) => [(string) $pase->id => $pase->descripcion()])
            ->all();
    }

    /** El pase que lleva encima quien se está marcando, si lleva alguno. */
    #[Computed]
    public function paseQueLleva(): ?EntregaDePase
    {
        $persona = $this->persona();

        return $persona ? app(Pases::class)->deLaPersona($persona) : null;
    }

    /** Si hay pases cargados en el catálogo, aunque ahora mismo no quede ninguno libre. */
    #[Computed]
    public function hayPasesCargados(): bool
    {
        return Pase::query()->activos()->exists();
    }

    /** Elegir con qué entra: a pie, uno de los suyos, u «otro» para teclear una placa. */
    public function elegirVehiculo(string $cual): void
    {
        // Volver a tocar el que ya estaba puesto vuelve a «a pie»: así se deshace sin buscar un
        // botón de deshacer, que es como se comporta todo lo demás que se elige tocando.
        $this->vehiculoEntrada = $this->vehiculoEntrada === $cual ? self::VEHICULO_A_PIE : $cual;
        $this->resetValidation();
    }

    /**
     * Los demás vehículos que están dentro, para el desplegable de «otro vehículo».
     *
     * @return array<string, string>
     */
    #[Computed]
    public function otrosVehiculosDentro(): array
    {
        $persona = $this->persona();

        if (! $persona) {
            return [];
        }

        return app(VehiculoEnLaPuerta::class)->otrosDentro($persona)
            ->mapWithKeys(fn ($estadia) => [
                (string) $estadia->id => trim(
                    $estadia->placa
                    .' · '.$estadia->etiquetaTipo()
                    .($estadia->puesto ? ' · '.$estadia->puesto->codigo : '')
                    .($estadia->flota_id ? ' · empresa' : '')
                ),
            ])
            ->all();
    }

    /**
     * Elegir un vehículo que no es suyo lo añade a la salida en el acto.
     *
     * Antes había que elegirlo y ADEMÁS pulsar «Añadir». Quien elegía la moto y pulsaba SALIDA
     * directamente —lo natural— salía a pie y la moto se quedaba dentro sin que nada lo avisara.
     * Un paso que se puede olvidar en la puerta es un paso que sobra.
     */
    public function updatedOtroVehiculoSalida(): void
    {
        $this->llevarseOtro();
    }

    /** Añadir a la salida un vehículo que no es suyo: de la empresa o de otra persona. */
    public function llevarseOtro(): void
    {
        $this->resetValidation();

        if ($this->otroVehiculoSalida === '') {
            return;
        }

        $id = (int) $this->otroVehiculoSalida;

        if (! in_array($id, $this->vehiculosSalida, true)) {
            $this->vehiculosSalida[] = $id;
        }

        $this->otroVehiculoSalida = '';
        unset($this->otrosVehiculosDentro);
    }

    /** Sale a pie: deja sin marcar todos sus vehículos, que se quedan dentro. */
    public function salirAPie(): void
    {
        $this->vehiculosSalida = [];
        $this->otroVehiculoSalida = '';
        $this->resetValidation();
    }

    /** Marcar o desmarcar un vehículo suyo que se lleva al salir. */
    public function alternarVehiculoSalida(int $estadiaId): void
    {
        $this->vehiculosSalida = in_array($estadiaId, $this->vehiculosSalida, true)
            ? array_values(array_diff($this->vehiculosSalida, [$estadiaId]))
            : [...$this->vehiculosSalida, $estadiaId];

        $this->resetValidation();
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
                // Lo que el vigilante eligió en «¿Cómo entra?» / «¿Cómo sale?». Se guarda para que
                // el registro pueda decir «a pie» en vez de un guion, que no se lee como una
                // respuesta sino como que falta algo.
                aPie: $this->vaAPie($tipo),
            );
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        // El vehículo y el pase van DESPUÉS del asiento y nunca antes: si algo falla al anotarlos,
        // la persona ya está marcada —que es lo que no puede perderse— y lo demás se arregla desde
        // su pantalla. Al revés, un fallo del carro dejaría a alguien sin marcar.
        try {
            $vehiculos = $this->anotarVehiculo($persona, $tipo);
            $pase = $this->moverPase($persona, $tipo);
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

        // Se dice la placa en la confirmación: es la única prueba que ve el guardia de que el
        // vehículo quedó anotado, y sin ella tendría que irse al estacionamiento a comprobarlo.
        if ($vehiculos !== []) {
            $confirmacion .= ' · '.($tipo === Movimiento::ENTRADA ? 'entró con ' : 'se llevó ').implode(', ', $vehiculos);
        }

        // El pase también se dice: es lo único que le prueba al guardia que quedó anotado, y de un
        // pase entregado hay que acordarse al final del turno.
        if ($pase !== null) {
            $confirmacion .= ' · '.($tipo === Movimiento::ENTRADA ? 'pase ' : 'devolvió el pase ').$pase;
        }

        $this->limpiar();
        $this->confirmacion = $confirmacion;
    }

    /**
     * Entrega el pase al entrar o lo recupera al salir. Devuelve el código movido, o null.
     *
     * Al salir se recupera aunque el visitante no lleve pase de este sistema: si no lleva ninguno
     * no hay nada que hacer y no se dice nada.
     *
     * @throws ValidationException
     */
    private function moverPase(Persona $persona, string $tipo): ?string
    {
        $pases = app(Pases::class);

        if ($tipo === Movimiento::SALIDA) {
            $entrega = $pases->deLaPersona($persona);

            if ($entrega === null || ! $this->devuelvePase) {
                return null;
            }

            $pases->devolver($entrega);

            return $entrega->pase?->codigo;
        }

        if ($this->paseEntrada === '') {
            return null;
        }

        $pase = Pase::find((int) $this->paseEntrada);

        if (! $pase) {
            throw ValidationException::withMessages([
                'pase' => 'Ese pase ya no está en el catálogo. Vuelve a elegirlo.',
            ]);
        }

        $pases->entregar($pase, $persona);

        return $pase->codigo;
    }

    /** Si lo elegido en pantalla dice que va a pie: sin vehículo en este sentido. */
    private function vaAPie(string $tipo): bool
    {
        return $tipo === Movimiento::ENTRADA
            ? $this->vehiculoEntrada === self::VEHICULO_A_PIE
            : $this->vehiculosSalida === [] && $this->otroVehiculoSalida === '';
    }

    /**
     * Anota el vehículo del mismo gesto: abre su estadía al entrar, se la cierra al salir.
     *
     * Devuelve las placas movidas, para decirlas en la confirmación. Vacío = se marcó a pie, que
     * es lo normal y no cambia nada de lo de siempre.
     *
     * @return list<string>
     *
     * @throws ValidationException
     */
    private function anotarVehiculo(Persona $persona, string $tipo): array
    {
        $puerta = app(VehiculoEnLaPuerta::class);

        if ($tipo === Movimiento::SALIDA) {
            // Por si quedó uno elegido en el desplegable sin llegar a añadirse: se lo lleva igual.
            // Lo que el vigilante ve elegido es lo que tiene que pasar.
            $this->llevarseOtro();

            if ($this->vehiculosSalida === []) {
                return [];
            }

            return $puerta->sale($persona, $this->vehiculosSalida)->pluck('placa')->all();
        }

        if ($this->vehiculoEntrada === '') {
            return [];
        }

        // Uno de la empresa: la placa sale del catálogo, no de la ficha de nadie.
        if (str_starts_with($this->vehiculoEntrada, self::PREFIJO_FLOTA)) {
            $deLaFlota = VehiculoDeFlota::find((int) substr($this->vehiculoEntrada, strlen(self::PREFIJO_FLOTA)));

            if (! $deLaFlota) {
                throw ValidationException::withMessages([
                    'placaEntrada' => 'Ese vehículo de la empresa ya no está en el catálogo. Vuelve a elegirlo.',
                ]);
            }

            return [$puerta->entra(
                persona: $persona,
                placa: $deLaFlota->placa,
                tipo: $deLaFlota->tipo_vehiculo,
                marca: $deLaFlota->marca,
                color: $deLaFlota->color,
            )->placa];
        }

        // «otro» es teclear una placa; cualquier otra cosa es la placa de uno de los suyos, que
        // ya viene limpia de su ficha.
        $esNuevo = $this->vehiculoEntrada === self::VEHICULO_OTRO;
        $suyo = $esNuevo ? null : $persona->vehiculoConPlaca($this->vehiculoEntrada);

        if (! $esNuevo && $suyo === null) {
            // La ficha cambió entre que se pintó la pantalla y se tocó el botón.
            throw ValidationException::withMessages([
                'placaEntrada' => 'Ese vehículo ya no está en su ficha. Vuelve a elegirlo.',
            ]);
        }

        $estadia = $puerta->entra(
            persona: $persona,
            placa: $esNuevo ? $this->placaNueva : $suyo->placa,
            tipo: $esNuevo ? $this->tipoNuevo : $suyo->tipo,
            marca: $suyo?->marca,
            color: $suyo?->color,
        );

        return [$estadia->placa];
    }

    /** Vuelve al estado inicial: campo vacío y listo para teclear. */
    public function limpiar(): void
    {
        // La letra vuelve a «V» como todo lo demás: la pantalla queda igual para el siguiente, y
        // el siguiente casi siempre es venezolano. Dejarla puesta sería peor que reiniciarla —el
        // vigilante no se acordaría de que quedó en «E» del anterior—.
        $this->reset([
            'cedula', 'nacionalidad', 'personaId', 'invitadoNuevo', 'avisoInvitado', 'nombre',
            'motivo', 'piso', 'nivel', 'pisoAMano', 'confirmacion',
            'vehiculoEntrada', 'placaNueva', 'tipoNuevo', 'vehiculosSalida', 'otroVehiculoSalida',
            'paseEntrada', 'devuelvePase',
        ]);
        $this->resetValidation();

        // Los dos contadores se olvidan juntos: acaba de registrarse un movimiento, así que lo
        // que decían ya no es cierto. Olvidar solo el total dejaría el desglose de antes en
        // pantalla, y el vigilante vería «41 y 6» debajo de un total que ya cambió.
        unset(
            $this->persona, $this->sugerido, $this->esperaHasta,
            $this->esperaSalidaHasta, $this->dentro, $this->dentroPorTipo,
            $this->susVehiculos, $this->susVehiculosDentro, $this->otrosVehiculosDentro,
            $this->susPlacasDentro, $this->pasesLibres, $this->paseQueLleva, $this->hayPasesCargados,
            $this->flotaParaEntrar, $this->hayFlotaCargada,
        );
    }

    public function render()
    {
        return view('livewire.marcar');
    }
}
