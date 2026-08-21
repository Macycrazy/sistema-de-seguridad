<?php

namespace App\Services;

use App\Models\User;
use App\Services\Auditoria\Auditoria;
use App\Usuarios\Rol;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Crear, renombrar y borrar roles. Solo el administrador.
 *
 * Los tres roles base (vigilante, supervisor, administrador) NO se tocan aquí: son la columna
 * vertebral de la jerarquía y viven en código (ver App\Usuarios\Rol). Este servicio solo maneja los
 * roles que el administrador agrega, cada uno anclado a un nivel (1, 2 o 3) que decide a quién puede
 * tocar —lo mismo que decide el nivel de los base—.
 *
 * Lo que este servicio NO deja hacer, y sostiene la seguridad:
 * - Dar un nivel por encima del tope (3): nadie por encima del administrador.
 * - Conceder «gestionar-permisos» a un rol nuevo: es intocable (ver Permiso), así que un rol nuevo
 *   jamás puede abrir esta misma pantalla ni ascender a otros.
 * - Borrar un rol que alguien esté usando: dejaría usuarios apuntando a un rol que ya no existe.
 */
class GestionDeRoles
{
    private const NOMBRE_MAXIMO = 60;

    public function __construct(private Permisos $permisos) {}

    /** Da de alta un rol nuevo, sin permisos: el administrador los marca luego en la matriz. */
    public function crear(string $nombre, int $nivel, User $quienLoHace): Rol
    {
        $this->exigirAdministrador($quienLoHace);

        $nombre = $this->nombreValido($nombre);
        $this->exigirNivelValido($nivel);
        $this->exigirNombreLibre($nombre);

        $slug = $this->slugLibre($nombre);
        $ahora = now();

        DB::table('roles')->insert([
            'slug' => $slug,
            'nombre' => $nombre,
            'nivel' => $nivel,
            'base' => false,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);

        Rol::olvidar();
        app(Auditoria::class)->cambioRoles("creó el rol «{$nombre}» (nivel {$nivel})");

        return Rol::from($slug);
    }

    /**
     * Renombra un rol o le cambia el nivel. El slug NO cambia —es lo que guardan los usuarios y los
     * permisos—, así que renombrar no arrastra ninguna migración. Los roles base no se editan.
     */
    public function editar(Rol $rol, string $nombre, int $nivel, User $quienLoHace): void
    {
        $this->exigirAdministrador($quienLoHace);
        $this->exigirNoBase($rol);

        $nombre = $this->nombreValido($nombre);
        $this->exigirNivelValido($nivel);
        $this->exigirNombreLibre($nombre, $rol);

        DB::table('roles')->where('slug', $rol->value)->update([
            'nombre' => $nombre,
            'nivel' => $nivel,
            'updated_at' => now(),
        ]);

        Rol::olvidar();
        app(Auditoria::class)->cambioRoles("editó el rol «{$nombre}» (nivel {$nivel})");
    }

    /** Borra un rol. No se puede si es base o si algún usuario lo tiene. Se lleva sus permisos. */
    public function eliminar(Rol $rol, User $quienLoHace): void
    {
        $this->exigirAdministrador($quienLoHace);
        $this->exigirNoBase($rol);

        if (User::query()->where('rol', $rol->value)->exists()) {
            throw ValidationException::withMessages([
                'rol' => 'Hay usuarios con ese rol. Cámbiaselos antes de borrarlo.',
            ]);
        }

        DB::transaction(function () use ($rol) {
            DB::table('permisos_de_rol')->where('rol', $rol->value)->delete();
            DB::table('roles')->where('slug', $rol->value)->delete();
        });

        Rol::olvidar();
        $this->permisos->olvidar();
        app(Auditoria::class)->cambioRoles("borró el rol «{$rol->nombre}»");
    }

    private function nombreValido(string $nombre): string
    {
        $nombre = trim(preg_replace('/\s+/', ' ', $nombre) ?? '');

        if ($nombre === '') {
            throw ValidationException::withMessages(['nombre' => 'Ponle un nombre al rol.']);
        }

        if (mb_strlen($nombre) > self::NOMBRE_MAXIMO) {
            throw ValidationException::withMessages(['nombre' => 'El nombre es muy largo.']);
        }

        return $nombre;
    }

    private function exigirNivelValido(int $nivel): void
    {
        if ($nivel < 1 || $nivel > Rol::NIVEL_MAXIMO) {
            throw ValidationException::withMessages([
                'nivel' => 'El nivel tiene que ser 1 (como vigilante), 2 (supervisor) o 3 (administrador).',
            ]);
        }
    }

    /** El nombre no puede chocar con otro rol (ni base ni creado), sin distinguir mayúsculas. */
    private function exigirNombreLibre(string $nombre, ?Rol $excepto = null): void
    {
        foreach (Rol::cases() as $otro) {
            if ($excepto !== null && $otro->es($excepto)) {
                continue;
            }

            if (mb_strtolower($otro->nombre) === mb_strtolower($nombre)) {
                throw ValidationException::withMessages(['nombre' => 'Ya hay un rol con ese nombre.']);
            }
        }
    }

    private function exigirNoBase(Rol $rol): void
    {
        if ($rol->esBase()) {
            throw ValidationException::withMessages([
                'rol' => 'Los roles base (vigilante, supervisor, administrador) no se editan ni se borran.',
            ]);
        }
    }

    private function exigirAdministrador(User $quienLoHace): void
    {
        // Cinturón además del gate de la pantalla: este servicio también lo puede llamar otra cosa.
        if (! $quienLoHace->esAdministrador()) {
            throw ValidationException::withMessages([
                'rol' => 'Solo un administrador maneja los roles.',
            ]);
        }
    }

    /** Un slug único a partir del nombre, sin chocar con los base ni con los ya creados. */
    private function slugLibre(string $nombre): string
    {
        $raiz = Str::slug($nombre);

        if ($raiz === '') {
            $raiz = 'rol';
        }

        $raiz = mb_substr($raiz, 0, 36);
        $slug = $raiz;
        $n = 2;

        // Contra la base directamente (que ya trae los tres base sembrados), no contra la caché de
        // Rol: la caché puede tener roles de una sesión anterior que ya no están en la tabla.
        while (DB::table('roles')->where('slug', $slug)->exists()) {
            $slug = $raiz.'-'.$n;
            $n++;
        }

        return $slug;
    }
}
