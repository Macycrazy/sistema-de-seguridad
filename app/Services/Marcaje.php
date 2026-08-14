<?php

namespace App\Services;

use App\Models\Movimiento;
use App\Models\Persona;
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
     * Cuántos dígitos puede tener una cédula. Es la única definición: la pantalla la usa para
     * no dejar teclear de más y el servidor para validar, así no se pueden desajustar.
     */
    public const DIGITOS_MINIMOS = 6;

    public const DIGITOS_MAXIMOS = 9;

    /**
     * Busca a quién pertenece una cédula. Devuelve null si no está en el sistema, que es la
     * señal de que estamos ante un invitado nuevo.
     */
    public function buscarPorCedula(string $cedula): ?Persona
    {
        $cedula = Persona::normalizarCedula($cedula);

        if ($cedula === '') {
            return null;
        }

        return Persona::where('cedula', $cedula)->first();
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
     * @param  Vehiculo|null  $vehiculo  El carro en el que llegó, si llegó en uno. Va aparte de
     *                                   los otros datos porque es opcional de verdad: quien
     *                                   entra caminando lo deja vacío y no pasa nada.
     *
     * @throws ValidationException si la cédula ya pertenece a alguien
     */
    public function registrarInvitado(
        string $cedula,
        string $nombre,
        string $motivo,
        ?Vehiculo $vehiculo = null,
    ): Persona {
        $cedula = Persona::normalizarCedula($cedula);
        $nombre = trim($nombre);
        $motivo = trim($motivo);
        $vehiculo ??= Vehiculo::desde();

        $this->exigirCedulaValida($cedula);

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

        // Un vehículo a medias no se guarda: o no hay carro, o al menos se sabe la placa.
        $vehiculo->exigirValido();

        if (Persona::where('cedula', $cedula)->exists()) {
            throw ValidationException::withMessages([
                'cedula' => 'Esa cédula ya está registrada en el sistema.',
            ]);
        }

        return Persona::create([
            'cedula' => $cedula,
            'tipo' => Persona::INVITADO,
            'nombre' => $nombre,
            'motivo' => $motivo,
            'activo' => true,
            ...$vehiculo->paraGuardar(),
        ]);
    }

    /**
     * Deja constancia de una entrada o una salida.
     *
     * @param  string|null  $motivo  El motivo de la visita, si es un invitado que vuelve y lo
     *                               actualiza. Si va nulo se conserva el que ya tenía.
     * @param  Vehiculo|null  $vehiculo  El vehículo de HOY. Nulo significa «no me lo preguntes,
     *                                   deja el que ya tenía la ficha». Un Vehiculo vacío es
     *                                   distinto: significa «hoy vino sin carro», y borra el que
     *                                   tuviera anotado. Sin esa diferencia, quien un día vino
     *                                   en carro arrastraría esa placa para siempre.
     *
     * @throws ValidationException si el tipo no es entrada ni salida, o la persona está inactiva
     */
    public function registrar(
        Persona $persona,
        string $tipo,
        ?int $usuarioId = null,
        ?string $motivo = null,
        ?Vehiculo $vehiculo = null,
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

        $vehiculo?->exigirValido();

        // La ficha y el asiento se guardan juntos o no se guarda ninguno: si falla la
        // actualización del invitado, no queremos un movimiento suelto apuntando a un dato viejo.
        return DB::transaction(function () use ($persona, $tipo, $usuarioId, $motivo, $vehiculo) {
            // Doble pulsación del botón, o el lector de carnets leyendo dos veces el mismo
            // carnet: se devuelve el asiento que ya existe en vez de crear otro igual.
            // Como los movimientos no se borran, un duplicado se quedaría en el histórico
            // para siempre y habría que corregirlo con un movimiento más.
            if ($repetido = $this->movimientoRecienRegistrado($persona, $tipo)) {
                return $repetido;
            }

            $motivo = $motivo !== null ? trim($motivo) : null;

            if ($persona->esInvitado() && $motivo !== null && $motivo !== '') {
                $persona->update(['motivo' => $motivo]);
            }

            // Aquí sí se guarda el vacío: es como se anota que hoy vino sin carro.
            if ($persona->esInvitado() && $vehiculo !== null) {
                $persona->update($vehiculo->paraGuardar());
            }

            return Movimiento::create([
                'persona_id' => $persona->id,
                'tipo' => $tipo,
                'ocurrio_en' => now(),
                'usuario_id' => $usuarioId,
                // El asiento de un trabajador no lleva motivo: viene a trabajar.
                'motivo' => $persona->esInvitado() ? $persona->motivo : null,
                // Ni vehículo: el carro se le pregunta al invitado, no al personal.
                ...($persona->esInvitado() ? $persona->vehiculo() : Vehiculo::desde())->paraGuardar(),
            ]);
        });
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

    /** Cuántas personas están dentro en este momento: su último movimiento fue una entrada. */
    public function cuantosDentro(): int
    {
        $ultimos = DB::table('movimientos')
            ->selectRaw('persona_id, max(id) as ultimo_id')
            ->groupBy('persona_id');

        return DB::table('movimientos')
            ->joinSub($ultimos, 'u', fn ($union) => $union->on('movimientos.id', '=', 'u.ultimo_id'))
            ->where('movimientos.tipo', Movimiento::ENTRADA)
            ->count();
    }

    /**
     * Se revisa aquí, en el servidor, para que no entre basura al sistema por mucho que la
     * pantalla diga otra cosa. El campo ya no deja teclear letras ni pasar del máximo, pero eso
     * es comodidad para quien teclea, no seguridad: cualquiera puede enviar lo que quiera.
     *
     * @throws ValidationException
     */
    public function exigirCedulaValida(string $cedula): string
    {
        $cedula = Persona::normalizarCedula($cedula);

        if ($cedula === '') {
            throw ValidationException::withMessages([
                'cedula' => 'Hace falta la cédula.',
            ]);
        }

        if (strlen($cedula) < self::DIGITOS_MINIMOS || strlen($cedula) > self::DIGITOS_MAXIMOS) {
            throw ValidationException::withMessages([
                'cedula' => sprintf(
                    'Esa cédula no parece válida: debe tener entre %d y %d dígitos.',
                    self::DIGITOS_MINIMOS,
                    self::DIGITOS_MAXIMOS,
                ),
            ]);
        }

        return $cedula;
    }
}
