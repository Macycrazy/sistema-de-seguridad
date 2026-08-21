<?php

namespace App\Services;

use App\Models\Persona;
use App\Models\User;
use App\Services\Auditoria\Auditoria;
use App\Usuarios\Rol;
use Illuminate\Validation\ValidationException;

/**
 * Dar de alta, desactivar y cambiar claves y roles.
 *
 * La clave que pone quien gestiona es la clave, sin más: quien entre con ella sigue trabajando y
 * no se le pide cambiarla. Si su dueño quiere una suya, la cambia cuando le parezca en /clave.
 *
 * La pantalla no decide nada: pregunta aquí, igual que la de marcar le pregunta todo a Marcaje.
 * Y aquí se valida en el servidor aunque la pantalla ya haya validado, porque cualquiera puede
 * mandar una petición sin pasar por la pantalla.
 *
 * LA REGLA QUE ATRAVIESA TODO ESTE SERVICIO: nadie toca a quien esté por encima de su propio rol,
 * ni crea ni asciende a nadie por encima de sí mismo. Desde que la gestión de usuarios se le abrió
 * al supervisor, sin esa regla el sistema se regalaría solo: bastaría con crearse un administrador
 * —o ponerle otra clave a uno que ya exista— para tenerlo todo.
 *
 * La vía preferida para quitar a un usuario es DESACTIVARLO: así el rastro de la auditoría sigue
 * probando quién hizo qué. Borrar (eliminar) existe para las cuentas creadas por error; anula ese
 * rastro (las FKs de movimientos y bitácora son «nullOnDelete»), por eso lleva las mismas
 * salvaguardas que desactivar.
 */
class GestionDeUsuarios
{
    /** Lo mínimo que se le acepta a cualquier clave. */
    public const MINIMO_DE_LA_CLAVE = 8;

    /**
     * Da de alta a alguien, con la clave y el rol que le ponga quien lo crea.
     *
     * @param  User|null  $quienLoHace  Nulo solo desde la consola: quien tiene acceso al servidor
     *                                  ya lo tiene todo, y ahí no hay rol que respetar.
     *
     * @throws ValidationException
     */
    public function crear(
        string $usuario,
        string $nombre,
        ?string $cedula,
        Rol $rol,
        string $clave,
        ?User $quienLoHace = null,
    ): User {
        $usuario = mb_strtolower(trim($usuario));
        $nombre = trim($nombre);
        $cedula = $this->cedulaValida($cedula);

        $this->exigirNoAscenderPorEncima($rol, $quienLoHace);
        $this->exigirUsuarioValido($usuario);
        $this->exigirClaveSuficiente($clave);

        if ($nombre === '') {
            throw ValidationException::withMessages([
                'nombre' => 'Hace falta el nombre de la persona.',
            ]);
        }

        if (User::where('usuario', $usuario)->exists()) {
            throw ValidationException::withMessages([
                'usuario' => 'Ese nombre de usuario ya está tomado.',
            ]);
        }

        // La cédula puede repetirse entre usuarios: aquí es un dato de contacto, no la identidad
        // (esa es «usuario», que sí es único). No se comprueba ni se bloquea por repetida.

        $creado = User::create([
            'usuario' => $usuario,
            'nombre' => $nombre,
            'cedula' => $cedula,
            'rol' => $rol,
            'activo' => true,
            'password' => $clave,
        ]);

        app(Auditoria::class)->creoUsuario($creado);

        return $creado;
    }

    /**
     * Corrige los datos de un usuario: su nombre, su nombre de usuario y su cédula. No toca la
     * clave ni el rol —esos tienen su propia vía—. El «usuario» sigue siendo único; la cédula no.
     *
     * @throws ValidationException
     */
    public function editar(
        User $usuario,
        string $nombre,
        string $nombreDeUsuario,
        ?string $cedula,
        User $quienLoHace,
    ): User {
        $this->exigirAlcance($usuario, $quienLoHace);

        $nombre = trim($nombre);
        $nombreDeUsuario = mb_strtolower(trim($nombreDeUsuario));
        $cedula = $this->cedulaValida($cedula);

        $this->exigirUsuarioValido($nombreDeUsuario);

        if ($nombre === '') {
            throw ValidationException::withMessages([
                'nombre' => 'Hace falta el nombre de la persona.',
            ]);
        }

        // El «usuario» es único: no puede pisar el de otro (el suyo propio sí se conserva).
        if (User::where('usuario', $nombreDeUsuario)->whereKeyNot($usuario->getKey())->exists()) {
            throw ValidationException::withMessages([
                'usuario' => 'Ese nombre de usuario ya está tomado.',
            ]);
        }

        $usuario->update([
            'nombre' => $nombre,
            'usuario' => $nombreDeUsuario,
            'cedula' => $cedula,
        ]);

        app(Auditoria::class)->editoUsuario($usuario);

        return $usuario;
    }

    /**
     * Borra un usuario de verdad.
     *
     * El sistema prefiere DESACTIVAR —así el rastro sigue diciendo quién hizo qué—, y esa sigue
     * siendo la vía normal. Pero el administrador puede querer quitar una cuenta creada por error.
     * Las FKs de «movimientos» y «bitácora» son «nullOnDelete»: el histórico no se cae, pero ese
     * usuario deja de aparecer como autor de lo que hizo. Por eso, con las mismas salvaguardas que
     * desactivar: no borrarse a uno mismo, ni al último administrador activo, ni a quien está por
     * encima del propio rol.
     *
     * @throws ValidationException
     */
    public function eliminar(User $usuario, User $quienLoHace): void
    {
        $this->exigirAlcance($usuario, $quienLoHace);

        if ($usuario->is($quienLoHace)) {
            throw ValidationException::withMessages([
                'usuario' => 'No puedes borrarte a ti mismo.',
            ]);
        }

        if ($usuario->esAdministrador() && $usuario->activo && $this->administradoresActivos() <= 1) {
            throw ValidationException::withMessages([
                'usuario' => 'Es el único administrador activo: el sistema se quedaría sin quien lo administre.',
            ]);
        }

        $nombreDeUsuario = $usuario->usuario;
        $usuario->delete();
        app(Auditoria::class)->borroUsuario($nombreDeUsuario);
    }

    /**
     * Le quita el acceso a alguien. No lo borra.
     *
     * @throws ValidationException
     */
    public function desactivar(User $usuario, User $quienLoHace): void
    {
        $this->exigirAlcance($usuario, $quienLoHace);

        // Desactivarse a uno mismo es un clic que deja fuera a quien lo dio, sin nadie que lo
        // arregle si además era el único administrador.
        if ($usuario->is($quienLoHace)) {
            throw ValidationException::withMessages([
                'usuario' => 'No puedes desactivarte a ti mismo.',
            ]);
        }

        /*
         * Sin administradores activos no hay quien cree usuarios ni reinicie claves, y desde la
         * pantalla no habría forma de volver: haría falta entrar a la base a mano.
         *
         * Desde la pantalla de usuarios a esta regla no se llega hoy, y es normal: quien la usa
         * es siempre un administrador activo, así que si desactiva a otro quedan dos, y si se
         * desactiva a sí mismo lo corta la regla de arriba. Está aquí porque este servicio lo usa
         * también el comando de consola, y porque la invariante es del sistema, no de la pantalla
         * que hoy resulta ser la única puerta.
         */
        if ($usuario->esAdministrador() && $this->administradoresActivos() <= 1) {
            throw ValidationException::withMessages([
                'usuario' => 'Es el único administrador activo: el sistema se quedaría sin quien lo administre.',
            ]);
        }

        $usuario->update(['activo' => false]);
        app(Auditoria::class)->desactivoUsuario($usuario);
    }

    public function reactivar(User $usuario, User $quienLoHace): void
    {
        $this->exigirAlcance($usuario, $quienLoHace);

        $usuario->update(['activo' => true]);
        app(Auditoria::class)->reactivoUsuario($usuario);
    }

    /**
     * Le cambia el rol a alguien.
     *
     * El README no pide esta pantalla, pero sin ella un ascenso o un cambio de puesto se arregla
     * entrando a la base a mano, y eso lo acaba haciendo cualquiera de cualquier manera.
     *
     * @throws ValidationException
     */
    public function cambiarRol(User $usuario, Rol $nuevo, User $quienLoHace): void
    {
        $this->exigirAlcance($usuario, $quienLoHace);
        $this->exigirNoAscenderPorEncima($nuevo, $quienLoHace);

        // Cambiarse el rol a uno mismo es la escalada más corta que hay: me subo y ya está.
        if ($usuario->is($quienLoHace)) {
            throw ValidationException::withMessages([
                'rol' => 'No puedes cambiarte el rol a ti mismo.',
            ]);
        }

        if ($usuario->rol === $nuevo) {
            return;
        }

        // Bajar de rol al último administrador deja al sistema sin quien lo administre, igual que
        // desactivarlo.
        if ($usuario->esAdministrador() && $usuario->activo && $this->administradoresActivos() <= 1) {
            throw ValidationException::withMessages([
                'rol' => 'Es el único administrador activo: el sistema se quedaría sin quien lo administre.',
            ]);
        }

        $antes = $usuario->rol->value;
        $usuario->update(['rol' => $nuevo]);
        app(Auditoria::class)->cambioRol($usuario, $antes, $nuevo->value);
    }

    /**
     * Le pone otra clave a alguien que perdió la suya.
     *
     * Con esa entra y sigue trabajando: no se le pide cambiarla. Es lo que el CIIP decidió, y
     * tiene una consecuencia que conviene tener escrita — quien la puso también la sabe, así que
     * mientras esa persona no la cambie por su cuenta, hay dos que pueden entrar con ese usuario.
     * El rastro del bloque D dirá «lo hizo Ana», y eso valdrá lo que valga esa clave compartida.
     *
     * @param  string  $campo  Bajo qué nombre viaja el error a la pantalla.
     *
     * @throws ValidationException
     */
    public function ponerClave(User $usuario, string $clave, User $quienLoHace, string $campo = 'clave'): void
    {
        // Ponerle la clave a alguien es poder entrar como esa persona. Por eso el alcance importa
        // más aquí que en ningún otro sitio: sin esta línea, un supervisor se pone la clave del
        // administrador y entra con ella.
        $this->exigirAlcance($usuario, $quienLoHace);

        $this->exigirClaveSuficiente($clave, $campo);

        $usuario->update(['password' => $clave]);
        app(Auditoria::class)->cambioClave($usuario);
    }

    /**
     * La cambia su propio dueño, cuando quiera. A partir de aquí la clave es suya y de nadie más.
     *
     * @throws ValidationException
     */
    public function cambiarClave(User $usuario, string $nueva, string $repetida): void
    {
        $this->exigirClaveSuficiente($nueva, 'nueva');

        if ($nueva !== $repetida) {
            throw ValidationException::withMessages([
                'repetida' => 'Las dos claves no coinciden.',
            ]);
        }

        $usuario->update(['password' => $nueva]);
        app(Auditoria::class)->cambioClave($usuario);
    }

    public function administradoresActivos(): int
    {
        return User::query()->activos()->where('rol', Rol::administrador())->count();
    }

    /**
     * No se toca a quien está por encima.
     *
     * Un supervisor gestiona vigilantes y supervisores; a un administrador no lo toca. Si pudiera,
     * la gestión de usuarios sería una escalera al rol de arriba: le pongo otra clave y entro como
     * él.
     *
     * @throws ValidationException
     */
    private function exigirAlcance(User $usuario, User $quienLoHace): void
    {
        if (! $quienLoHace->alcanza($usuario->rol)) {
            throw ValidationException::withMessages([
                'usuario' => 'Ese usuario tiene un rol por encima del tuyo: no puedes gestionarlo.',
            ]);
        }
    }

    /**
     * No se crea ni se asciende a nadie por encima de uno mismo.
     *
     * @throws ValidationException
     */
    private function exigirNoAscenderPorEncima(Rol $rol, ?User $quienLoHace): void
    {
        // Desde la consola no hay a quién respetar: quien puede correr artisan en el servidor ya
        // tiene el servidor. Es también la única forma de crear el primer administrador.
        if ($quienLoHace === null) {
            return;
        }

        if (! $quienLoHace->alcanza($rol)) {
            throw ValidationException::withMessages([
                'rol' => 'No puedes darle a nadie un rol por encima del tuyo.',
            ]);
        }
    }

    /**
     * El mínimo de una clave, esté quien esté al otro lado del teclado.
     *
     * Vale para la que teclea el administrador y para la que pone su dueño: si el mínimo dependiera
     * de quién la escribe, la puerta más floja sería la que valdría.
     *
     * @throws ValidationException
     */
    private function exigirClaveSuficiente(string $clave, string $campo = 'clave'): void
    {
        if ($clave === '') {
            throw ValidationException::withMessages([
                $campo => 'Hace falta la clave.',
            ]);
        }

        if (mb_strlen($clave) < self::MINIMO_DE_LA_CLAVE) {
            throw ValidationException::withMessages([
                $campo => 'La clave debe tener al menos '.self::MINIMO_DE_LA_CLAVE.' caracteres.',
            ]);
        }
    }

    /**
     * El nombre de usuario se teclea a diario y en un teclado de puesto de vigilancia: letras,
     * dígitos, punto, guion y guion bajo. Nada de espacios ni acentos.
     *
     * @throws ValidationException
     */
    private function exigirUsuarioValido(string $usuario): void
    {
        if ($usuario === '') {
            throw ValidationException::withMessages([
                'usuario' => 'Hace falta el nombre de usuario.',
            ]);
        }

        if (! preg_match('/^[a-z0-9._-]{3,40}$/', $usuario)) {
            throw ValidationException::withMessages([
                'usuario' => 'El usuario va entre 3 y 40 caracteres, con letras, números, punto, guion o guion bajo.',
            ]);
        }
    }

    /**
     * La cédula es opcional —quien opera el sistema puede no tener ficha en la puerta— pero si
     * viene, se guarda como en «personas»: solo dígitos, y dentro del rango de Marcaje.
     *
     * @throws ValidationException
     */
    private function cedulaValida(?string $cedula): ?string
    {
        $cedula = Persona::normalizarCedula($cedula);

        if ($cedula === '') {
            return null;
        }

        if (strlen($cedula) < Marcaje::DIGITOS_MINIMOS || strlen($cedula) > Marcaje::DIGITOS_MAXIMOS) {
            throw ValidationException::withMessages([
                'cedula' => sprintf(
                    'Esa cédula no parece válida: debe tener entre %d y %d dígitos.',
                    Marcaje::DIGITOS_MINIMOS,
                    Marcaje::DIGITOS_MAXIMOS,
                ),
            ]);
        }

        return $cedula;
    }
}
