<?php

namespace App\Services;

use App\Models\Movimiento;
use App\Models\Persona;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * La lógica de la puerta. La pantalla solo pregunta y muestra; aquí se decide.
 *
 * Todo lo que valida esta clase se valida en el servidor. Si la pantalla ya lo validó, da igual:
 * se vuelve a revisar aquí, porque esconder un botón no es seguridad.
 *
 * Punto de enganche para la parte 3 (auditoría): «buscarPorCedula» es el único sitio por donde
 * se consulta una cédula, y «registrar» el único por donde se escribe un movimiento. Cuando haya
 * que dejar rastro de quién consultó qué, se agrega ahí y queda cubierta toda la parte 1.
 */
class Marcaje
{
    /**
     * Ventana para considerar que un movimiento es una repetición del anterior y no uno nuevo.
     *
     * Diez segundos: de sobra para absorber una doble pulsación o una doble lectura del carnet,
     * y demasiado poco para tapar un movimiento de verdad — nadie entra y vuelve a entrar en
     * diez segundos.
     */
    public const SEGUNDOS_ANTIDUPLICADO = 10;

    /**
     * Cuánto tiene que pasar entre dos entradas de la misma persona.
     *
     * Se cuenta desde su ENTRADA anterior, haya salido en el medio o no: es lo que evita que
     * alguien que entra y sale a cada rato llene el histórico de movimientos.
     *
     * Efecto que hay que conocer: a quien baje un momento a la calle y vuelva no se le podrá
     * marcar el regreso hasta que se cumplan estos minutos. La pantalla le dice al vigilante la
     * hora exacta a partir de la cual puede.
     */
    public const MINUTOS_ENTRE_ENTRADAS = 10;

    /**
     * Cuántos dígitos puede tener una cédula. Es la única definición: la pantalla la usa para
     * no dejar teclear de más y el servidor para validar, así no se pueden desajustar.
     */
    public const DIGITOS_MINIMOS = 6;

    public const DIGITOS_MAXIMOS = 9;

    /**
     * La persona jurídica admite un dígito más: su número es un RIF, y esos llegan a diez.
     *
     * Va por letra y no subiendo el máximo de todas, porque una cédula V de diez dígitos no
     * existe y dejarla pasar sería abrir la puerta a un error de tecleo que nadie atajaría.
     */
    public const DIGITOS_MAXIMOS_JURIDICO = 10;

    /** Cuántos dígitos admite cada letra. */
    public static function digitosMaximos(?string $nacionalidad = null): int
    {
        return Persona::normalizarNacionalidad($nacionalidad) === Persona::JURIDICO
            ? self::DIGITOS_MAXIMOS_JURIDICO
            : self::DIGITOS_MAXIMOS;
    }

    /**
     * Busca a quién pertenece una cédula. Devuelve null si no está en el sistema, que es la
     * señal de que estamos ante un invitado nuevo.
     */
    public function buscarPorCedula(string $cedula, ?string $nacionalidad = null): ?Persona
    {
        $cedula = Persona::normalizarCedula($cedula);

        if ($cedula === '') {
            return null;
        }

        // La letra va SIEMPRE en la búsqueda, aunque no la manden: sin ella, «E-12345678» le
        // sacaría al vigilante la ficha del venezolano con ese número. Quien no la pase se lleva
        // «V», que es lo que se daba por sentado antes de preguntarla.
        return Persona::where('cedula', $cedula)
            ->where('nacionalidad', Persona::normalizarNacionalidad($nacionalidad))
            ->first();
    }

    /**
     * Qué botón corresponde: si la persona está dentro, lo que toca es la salida.
     * Es una propuesta, no una imposición — el vigilante puede pulsar el otro botón.
     */
    public function movimientoSugerido(Persona $persona): string
    {
        return $persona->estaDentro() ? Movimiento::SALIDA : Movimiento::ENTRADA;
    }

    /**
     * Dos personas distintas nunca comparten cédula, así que un invitado nuevo se crea aquí
     * con lo mínimo: nombre y motivo de la visita.
     *
     * @param  DatosVehiculo|null  $vehiculo  El vehículo en el que llegó, si llegó en uno. Va
     *                                        aparte de los otros datos porque es opcional de
     *                                        verdad: quien entra caminando lo deja vacío.
     *
     * @throws ValidationException si la cédula ya pertenece a alguien
     */
    public function registrarInvitado(
        string $cedula,
        string $nombre,
        string $motivo,
        ?string $piso = null,
        ?DatosVehiculo $vehiculo = null,
        ?string $nacionalidad = null,
    ): Persona {
        $cedula = Persona::normalizarCedula($cedula);
        $nacionalidad = Persona::normalizarNacionalidad($nacionalidad);
        $nombre = trim($nombre);
        $motivo = trim($motivo);
        $piso = Persona::normalizarPiso($piso);
        $vehiculo ??= DatosVehiculo::desde();

        $this->exigirCedulaValida($cedula, $nacionalidad);

        if ($nombre === '') {
            throw ValidationException::withMessages([
                'nombre' => 'Hace falta el nombre del invitado.',
            ]);
        }

        if ($motivo === '') {
            throw ValidationException::withMessages([
                'motivo' => 'Hace falta el motivo de la visita.',
            ]);
        }

        // Al invitado se le pregunta SIEMPRE a dónde va: es lo que permite saber quién hay en
        // cada piso, que es media razón de ser de este registro.
        if ($piso === null) {
            throw ValidationException::withMessages([
                'piso' => 'Hace falta el piso al que se dirige.',
            ]);
        }

        // Un vehículo a medias no se guarda: o no hay carro, o al menos se sabe la placa.
        $vehiculo->exigirValido();

        // La pareja entera, no solo el número: el mismo número con otra letra es otra persona.
        if (Persona::where('cedula', $cedula)->where('nacionalidad', $nacionalidad)->exists()) {
            throw ValidationException::withMessages([
                'cedula' => 'Esa cédula ya está registrada en el sistema.',
            ]);
        }

        // La ficha y su vehículo se crean juntos o no se crea ninguno.
        return DB::transaction(function () use ($cedula, $nacionalidad, $nombre, $motivo, $piso, $vehiculo) {
            $persona = Persona::create([
                'cedula' => $cedula,
                'nacionalidad' => $nacionalidad,
                'tipo' => Persona::INVITADO,
                'nombre' => $nombre,
                'motivo' => $motivo,
                'piso' => $piso,
                'activo' => true,
            ]);

            if (! $vehiculo->vacio()) {
                $persona->vehiculos()->create($vehiculo->paraGuardarEnLaTabla());
            }

            return $persona;
        });
    }

    /**
     * Deja constancia de una entrada o una salida.
     *
     * @param  string|null  $motivo  El motivo de la visita, si es un invitado que vuelve y lo
     *                               actualiza. Si va nulo se conserva el que ya tenía.
     * @param  DatosVehiculo|null  $vehiculo  En qué llegó HOY, sea invitado o trabajador. Nulo y
     *                                        vacío significan lo mismo aquí —que no trajo
     *                                        ninguno—, porque el asiento anota lo de ESTE día y
     *                                        no arrastra nada del anterior.
     *
     *                                        Si el vehículo no está entre los suyos, se le suma
     *                                        a la ficha para que la próxima vez ya salga en la
     *                                        lista y no haya que teclearlo otra vez.
     *
     * @throws ValidationException si el tipo no es entrada ni salida, o la persona está inactiva
     */
    public function registrar(
        Persona $persona,
        string $tipo,
        ?int $usuarioId = null,
        ?string $motivo = null,
        ?string $piso = null,
        ?DatosVehiculo $vehiculo = null,
    ): Movimiento {
        if (! in_array($tipo, [Movimiento::ENTRADA, Movimiento::SALIDA], true)) {
            throw ValidationException::withMessages([
                'tipo' => 'Un movimiento solo puede ser una entrada o una salida.',
            ]);
        }

        if (! $persona->activo) {
            throw ValidationException::withMessages([
                'cedula' => 'Esa persona está desactivada: no se le puede marcar.',
            ]);
        }

        $vehiculo ??= DatosVehiculo::desde();
        $vehiculo->exigirValido();
        $this->exigirQueElVehiculoNoCambieDeClase($persona, $vehiculo);

        // La ficha y el asiento se guardan juntos o no se guarda ninguno: si falla la
        // actualización del invitado, no queremos un movimiento suelto apuntando a un dato viejo.
        $piso = Persona::normalizarPiso($piso);

        return DB::transaction(function () use ($persona, $tipo, $usuarioId, $motivo, $piso, $vehiculo) {
            // Doble pulsación del botón, o el lector de carnets leyendo dos veces el mismo
            // carnet: se devuelve el asiento que ya existe en vez de crear otro igual.
            // Como los movimientos no se borran, un duplicado se quedaría en el histórico
            // para siempre y habría que corregirlo con un movimiento más.
            if ($repetido = $this->movimientoRecienRegistrado($persona, $tipo)) {
                return $repetido;
            }

            // Va DESPUÉS del antiduplicado a propósito: una doble pulsación no es un error del
            // vigilante y no debe sacarle un aviso en pantalla, se resuelve sola arriba.
            $this->exigirQueElMovimientoTengaSentido($persona, $tipo);

            $motivo = $motivo !== null ? trim($motivo) : null;

            if ($persona->esInvitado() && $motivo !== null && $motivo !== '') {
                $persona->update(['motivo' => $motivo]);
            }

            // El piso del invitado cambia de una visita a otra: se le pregunta y se guarda. El
            // del trabajador es fijo y viene de su ficha, así que no se toca.
            if ($persona->esInvitado() && $piso !== null) {
                $persona->update(['piso' => $piso]);
            }

            // Si trajo uno que no tenía anotado, se le suma a la ficha: la próxima vez ya sale
            // en la lista y el vigilante solo lo señala en vez de teclearlo entero.
            if (! $vehiculo->vacio() && ! $persona->vehiculoConPlaca($vehiculo->placa)) {
                $persona->vehiculos()->create($vehiculo->paraGuardarEnLaTabla());
            }

            return Movimiento::create([
                'persona_id' => $persona->id,
                'tipo' => $tipo,
                'ocurrio_en' => now(),
                'usuario_id' => $usuarioId,
                // El asiento de un trabajador no lleva motivo: viene a trabajar.
                'motivo' => $persona->esInvitado() ? $persona->motivo : null,
                // El piso sí lo llevan los dos: el suyo si labora aquí, aquel al que va si visita.
                'piso' => $persona->piso,
                // Copia congelada de lo que trajo HOY, no un enlace a la tabla: el asiento tiene
                // que seguir diciendo la verdad de ese día aunque el vehículo se corrija o se
                // borre después.
                ...$vehiculo->paraGuardar(),
            ]);
        });
    }

    /**
     * No se entra dos veces seguidas, ni se sale sin haber entrado.
     *
     * Quien ya está dentro no puede volver a entrar: sería un asiento que no ocurrió, y como los
     * movimientos no se borran, se quedaría en el histórico para siempre. Lo mismo al revés.
     *
     * La pantalla ya apaga el botón que no toca, pero eso es comodidad: cualquiera puede enviar
     * una petición sin pasar por ahí.
     *
     * OJO si alguien se queda «dentro» de un día para otro porque olvidó marcar la salida: el
     * botón de entrada le aparecerá apagado. Se arregla como cualquier otro error en este
     * sistema, con un movimiento nuevo — se le marca la salida que faltaba y ya puede entrar.
     *
     * @throws ValidationException
     */
    protected function exigirQueElMovimientoTengaSentido(Persona $persona, string $tipo): void
    {
        $dentro = $persona->estaDentro();

        if ($tipo === Movimiento::ENTRADA && $dentro) {
            throw ValidationException::withMessages([
                'tipo' => 'Ya tiene la entrada marcada: lo que toca es la salida.',
            ]);
        }

        if ($tipo === Movimiento::SALIDA && ! $dentro) {
            throw ValidationException::withMessages([
                'tipo' => 'No tiene la entrada marcada: no se le puede marcar la salida.',
            ]);
        }

        // Ya salió, pero entró hace muy poco. Se cuenta desde la entrada anterior, no desde la
        // salida: si no, entrar y salir a cada rato seguiría llenando el histórico.
        if ($tipo === Movimiento::ENTRADA && $desde = $this->puedeEntrarDesde($persona)) {
            throw ValidationException::withMessages([
                'tipo' => sprintf(
                    'Entró hace menos de %d minutos. Se le puede marcar otra entrada a partir de las %s.',
                    self::MINUTOS_ENTRE_ENTRADAS,
                    $desde->format('H:i'),
                ),
            ]);
        }
    }

    /**
     * Un vehículo no cambia de clase: la moto de José es una moto todos los días.
     *
     * El tipo va pegado a la PLACA, no al día. Mientras siga siendo el mismo vehículo, marcar
     * «carro» sobre una moto solo puede ser un error de tecleo, y un error así ensucia el
     * histórico sin que nadie se entere. Si de verdad llegó en otra cosa, es otro vehículo: con
     * poner la placa nueva, el tipo se vuelve a poder elegir.
     *
     * La pantalla ya apaga el botón que no toca, pero eso es comodidad: esconder un botón no es
     * seguridad, y cualquiera puede enviar una petición sin pasar por ahí.
     *
     * @throws ValidationException
     */
    protected function exigirQueElVehiculoNoCambieDeClase(Persona $persona, DatosVehiculo $vehiculo): void
    {
        if ($vehiculo->vacio()) {
            return;
        }

        $anotado = $persona->vehiculoConPlaca($vehiculo->placa);

        if (! $anotado || $anotado->tipo === $vehiculo->tipo) {
            return;
        }

        throw ValidationException::withMessages([
            'tipoVehiculo' => sprintf(
                'La placa %s ya está anotada como %s. Si hoy llegó en otro vehículo, cambia la placa.',
                $anotado->placa,
                mb_strtolower($anotado->datos()->etiquetaTipo()),
            ),
        ]);
    }

    /**
     * El mismo movimiento, para la misma persona, registrado hace un instante.
     *
     * Solo mira si el ÚLTIMO asiento es del mismo tipo y está dentro de la ventana. Así no
     * estorba a la regla de que un error se corrige con un movimiento nuevo: marcar una salida
     * después de una entrada equivocada pasa siempre, porque el tipo es distinto.
     */
    protected function movimientoRecienRegistrado(Persona $persona, string $tipo): ?Movimiento
    {
        $ultimo = $persona->ultimoMovimiento();

        if (! $ultimo || $ultimo->tipo !== $tipo) {
            return null;
        }

        return $ultimo->ocurrio_en->diffInSeconds(now()) < self::SEGUNDOS_ANTIDUPLICADO
            ? $ultimo
            : null;
    }

    /**
     * A partir de qué hora se le puede volver a marcar la entrada.
     *
     * Devuelve null cuando puede entrar ya —que es lo normal—, y la hora exacta cuando todavía
     * hay que esperar. La pantalla la usa para decírselo al vigilante en vez de dejarle pulsar
     * un botón que va a fallar.
     */
    public function puedeEntrarDesde(Persona $persona): ?CarbonInterface
    {
        $ultima = $persona->ultimaEntrada();

        if (! $ultima) {
            return null;
        }

        $desde = $ultima->ocurrio_en->copy()->addMinutes(self::MINUTOS_ENTRE_ENTRADAS);

        return $desde->isFuture() ? $desde : null;
    }

    /** Cuántas personas están dentro en este momento: su último movimiento fue una entrada. */
    public function cuantosDentro(): int
    {
        return array_sum($this->cuantosDentroPorTipo());
    }

    /**
     * Lo mismo, pero separado en trabajadores e invitados.
     *
     * No es un adorno del contador: en una emergencia, «hay 47 personas dentro» no sirve igual
     * que «41 trabajadores y 6 invitados». A los de casa se les localiza por su dependencia; a
     * los invitados no los conoce nadie y hay que ir a buscarlos al piso que visitaban.
     *
     * Devuelve SIEMPRE las dos claves, aunque alguna esté en cero: quien lo use no tiene por qué
     * comprobar si existen.
     *
     * @return array{trabajador: int, invitado: int}
     */
    public function cuantosDentroPorTipo(): array
    {
        $ultimos = DB::table('movimientos')
            ->selectRaw('persona_id, max(id) as ultimo_id')
            ->groupBy('persona_id');

        $cuenta = DB::table('movimientos')
            ->joinSub($ultimos, 'u', fn ($union) => $union->on('movimientos.id', '=', 'u.ultimo_id'))
            ->join('personas', 'personas.id', '=', 'movimientos.persona_id')
            ->where('movimientos.tipo', Movimiento::ENTRADA)
            ->groupBy('personas.tipo')
            ->pluck(DB::raw('count(*)'), 'personas.tipo');

        return [
            Persona::TRABAJADOR => (int) $cuenta->get(Persona::TRABAJADOR, 0),
            Persona::INVITADO => (int) $cuenta->get(Persona::INVITADO, 0),
        ];
    }

    /**
     * Se revisa aquí, en el servidor, para que no entre basura al sistema por mucho que la
     * pantalla diga otra cosa. El campo ya no deja teclear letras ni pasar del máximo, pero eso
     * es comodidad para quien teclea, no seguridad: cualquiera puede enviar lo que quiera.
     *
     * @throws ValidationException
     */
    public function exigirCedulaValida(string $cedula, ?string $nacionalidad = null): string
    {
        $cedula = Persona::normalizarCedula($cedula);
        $maximo = self::digitosMaximos($nacionalidad);

        if ($cedula === '') {
            throw ValidationException::withMessages([
                'cedula' => 'Hace falta la cédula.',
            ]);
        }

        if (strlen($cedula) < self::DIGITOS_MINIMOS || strlen($cedula) > $maximo) {
            throw ValidationException::withMessages([
                'cedula' => sprintf(
                    'Esa cédula no parece válida: debe tener entre %d y %d dígitos.',
                    self::DIGITOS_MINIMOS,
                    $maximo,
                ),
            ]);
        }

        return $cedula;
    }
}
