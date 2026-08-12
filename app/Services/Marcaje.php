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
     * con lo mínimo: nombre y a quién viene a ver.
     *
     * @throws ValidationException si la cédula ya pertenece a alguien
     */
    public function registrarInvitado(string $cedula, string $nombre, string $visita): Persona
    {
        $cedula = Persona::normalizarCedula($cedula);
        $nombre = trim($nombre);
        $visita = trim($visita);

        $this->exigirCedulaValida($cedula);

        if ($nombre === '') {
            throw ValidationException::withMessages([
                'nombre' => 'Hace falta el nombre del invitado.',
            ]);
        }

        if ($visita === '') {
            throw ValidationException::withMessages([
                'visita' => 'Hace falta a quién viene a ver.',
            ]);
        }

        if (Persona::where('cedula', $cedula)->exists()) {
            throw ValidationException::withMessages([
                'cedula' => 'Esa cédula ya está registrada en el sistema.',
            ]);
        }

        return Persona::create([
            'cedula' => $cedula,
            'tipo' => Persona::INVITADO,
            'nombre' => $nombre,
            'visita' => $visita,
            'activo' => true,
        ]);
    }

    /**
     * Deja constancia de una entrada o una salida.
     *
     * @param  string|null  $visita  A quién viene a ver, si es un invitado que vuelve y lo
     *                               actualiza. Si va nulo se conserva el dato que ya tenía.
     *
     * @throws ValidationException si el tipo no es entrada ni salida, o la persona está inactiva
     */
    public function registrar(
        Persona $persona,
        string $tipo,
        ?int $usuarioId = null,
        ?string $visita = null,
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

        // La ficha y el asiento se guardan juntos o no se guarda ninguno: si falla la
        // actualización del invitado, no queremos un movimiento suelto apuntando a un dato viejo.
        return DB::transaction(function () use ($persona, $tipo, $usuarioId, $visita) {
            // Doble pulsación del botón, o el lector de carnets leyendo dos veces el mismo
            // carnet: se devuelve el asiento que ya existe en vez de crear otro igual.
            // Como los movimientos no se borran, un duplicado se quedaría en el histórico
            // para siempre y habría que corregirlo con un movimiento más.
            if ($repetido = $this->movimientoRecienRegistrado($persona, $tipo)) {
                return $repetido;
            }

            $visita = $visita !== null ? trim($visita) : null;

            if ($persona->esInvitado() && $visita !== null && $visita !== '') {
                $persona->update(['visita' => $visita]);
            }

            return Movimiento::create([
                'persona_id' => $persona->id,
                'tipo' => $tipo,
                'ocurrio_en' => now(),
                'usuario_id' => $usuarioId,
                // Del asiento de un trabajador no cuelga ninguna visita.
                'visita' => $persona->esInvitado() ? $persona->visita : null,
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
     * Una cédula venezolana tiene entre 6 y 9 dígitos. Se revisa aquí, en el servidor, para que
     * no entre basura al sistema por mucho que la pantalla diga otra cosa.
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

        if (strlen($cedula) < 6 || strlen($cedula) > 9) {
            throw ValidationException::withMessages([
                'cedula' => 'Esa cédula no parece válida: debe tener entre 6 y 9 dígitos.',
            ]);
        }

        return $cedula;
    }
}
